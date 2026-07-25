<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\ChallengeMode;
use App\Enums\ChallengeStatus;
use App\Enums\UserRole;
use App\Events\ChallengeUnderReview;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Jobs\ChallengeOfferExpiredJob;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBalance;
use App\Notifications\ChallengeAcceptedNotification;
use App\Notifications\ChallengeApprovedNotification;
use App\Notifications\ChallengeCreatedAdminNotification;
use App\Notifications\ChallengeOfferNotification;
use App\Notifications\ChallengeRejectedNotification;
use App\Notifications\ChallengeSubmittedForReviewNotification;
use App\Services\ChallengeEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChallengeController extends Controller
{
    public function __construct(private ChallengeEscrowService $escrow) {}

    /**
     * Whether the authenticated user is allowed to create challenges.
     */
    public function canCreate()
    {
        $user = auth('api')->user();

        return $this->sendResponse([
            'can_create_challenge' => $this->userCanCreate($user),
        ]);
    }

    /**
     * Create a challenge offer and reserve the challenger's stake.
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();

        if (! $this->userCanCreate($user)) {
            return $this->sendError('You are not allowed to create challenges.', [], 403);
        }

        $request->validate([
            'game_id' => 'required|exists:games,id',
            'amount' => 'required|numeric|min:1',
            'match_date' => 'required|date|after_or_equal:today',
            'match_time' => 'required|date_format:H:i',
            'mode' => ['required', Rule::in([ChallengeMode::UNIQUE->value, ChallengeMode::GLOBAL->value])],
            'target_player_id' => [
                Rule::requiredIf($request->mode === ChallengeMode::UNIQUE->value),
                'nullable',
                'exists:users,id',
                Rule::notIn([$user->id]),
            ],
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'show_real_name' => 'nullable|boolean',
            'memo' => 'nullable|string',
            'duration_hours' => 'nullable|integer|min:1',
        ]);

        $durationHours = (int) ($request->duration_hours ?? 24);
        $amount = (float) $request->amount;
        $autoOffer = Setting::isEnabled('auto_offer_challenges');

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('logos')
            : null;

        $challenge = DB::transaction(function () use ($request, $user, $amount, $durationHours, $logoPath, $autoOffer) {

            $challenge = Challenge::create([
                'challenge_no' => $this->generateChallengeNo(),
                'challenger_id' => $user->id,
                'mode' => $request->mode,
                'target_player_id' => $request->mode === ChallengeMode::UNIQUE->value
                    ? $request->target_player_id
                    : null,
                'game_id' => $request->game_id,
                'amount' => $amount,
                'logo' => $logoPath,
                'memo' => $request->memo,
                'show_real_name' => $request->boolean('show_real_name', true),
                'match_date' => $request->match_date,
                'match_time' => $request->match_time,
                'status' => $autoOffer ? ChallengeStatus::OFFERED : ChallengeStatus::PENDING,
                'duration_hours' => $durationHours,
                'offer_expires_at' => now()->addHours($durationHours),
                'approved_at' => $autoOffer ? now() : null,
            ]);

            $this->escrow->hold($user->id, $amount, $challenge);

            return $challenge;
        });

        ChallengeOfferExpiredJob::dispatch($challenge->id)->delay($challenge->offer_expires_at);

        if ($autoOffer) {
            $challenge->challenger?->notify(new ChallengeApprovedNotification($challenge));

            if ($challenge->mode === ChallengeMode::UNIQUE && $challenge->targetPlayer) {
                $challenge->targetPlayer->notify(new ChallengeOfferNotification($challenge));
            }
        } else {
            $this->notifyAdmins(new ChallengeCreatedAdminNotification($challenge));
        }

        $remaining = UserBalance::where('user_id', $user->id)->value('total_balance');

        return $this->sendResponse([
            'challenge_no' => $challenge->challenge_no,
            'amount_deducted' => $amount,
            'remaining_balance' => $remaining,
            'duration' => $durationHours.' Hours',
        ], $autoOffer
            ? 'Challenge created and is now live.'
            : 'Challenge created and is awaiting admin approval.', 201);
    }

    /**
     * Accept an approved (offered) challenge by matching the stake.
     */
    public function accept(Request $request, $id)
    {
        $request->validate([
            'terms_accepted' => 'required|boolean',
        ]);

        if (! $request->boolean('terms_accepted')) {
            abort(400, 'You must accept the terms to continue.');
        }

        $user = auth('api')->user();

        $challenge = DB::transaction(function () use ($user, $id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if ($challenge->status !== ChallengeStatus::OFFERED) {
                abort(400, 'This challenge is not open for acceptance.');
            }

            if ($challenge->isExpired()) {
                abort(400, 'This challenge offer has expired.');
            }

            if ($challenge->challenger_id === $user->id) {
                abort(400, 'You cannot accept your own challenge.');
            }

            if (
                $challenge->mode === ChallengeMode::UNIQUE
                && $challenge->target_player_id !== $user->id
            ) {
                abort(403, 'This challenge is reserved for another player.');
            }

            $this->escrow->hold($user->id, (float) $challenge->amount, $challenge);

            $challenge->update([
                'status' => ChallengeStatus::ACCEPTED,
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            return $challenge;
        });

        $challenge->challenger?->notify(new ChallengeAcceptedNotification($challenge));

        $remaining = UserBalance::where('user_id', $user->id)->value('total_balance');

        return $this->sendResponse([
            'challenge_no' => $challenge->challenge_no,
            'amount_deducted' => $challenge->amount,
            'remaining_balance' => $remaining,
            'duration' => $challenge->duration_hours.' Hours',
        ], 'Challenge accepted successfully.');
    }

    /**
     * Target player declines a unique challenge; the challenger is refunded.
     */
    public function decline($id)
    {
        $user = auth('api')->user();

        $challenge = DB::transaction(function () use ($user, $id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if (
                $challenge->status !== ChallengeStatus::OFFERED
                || $challenge->mode !== ChallengeMode::UNIQUE
            ) {
                abort(400, 'This challenge cannot be declined.');
            }

            if ($challenge->target_player_id !== $user->id) {
                abort(403, 'This challenge is not addressed to you.');
            }

            $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            $challenge->update(['status' => ChallengeStatus::DECLINED]);

            return $challenge;
        });

        $challenge->challenger?->notify(new ChallengeRejectedNotification($challenge));

        return $this->sendResponse([], 'Challenge declined.');
    }

    /**
     * Player confirms their pre-match checklist. The challenge starts when both
     * accepted players are ready.
     */
    public function ready(Request $request, $id)
    {
        $request->validate([
            'battery_confirmed' => 'accepted',
            'internet_confirmed' => 'accepted',
            'camera_confirmed' => 'accepted',
            'rules_confirmed' => 'accepted',
        ]);

        $user = auth('api')->user();

        $challenge = DB::transaction(function () use ($user, $id) {

            $challenge = Challenge::with('publishedMatch')->lockForUpdate()->findOrFail($id);

            $this->ensureRegularAcceptedChallenge($challenge);

            $readyColumn = $this->readyColumnFor($challenge, $user->id);

            if (! $readyColumn) {
                abort(403, 'Only challenge players can mark ready.');
            }

            if (! $challenge->{$readyColumn}) {
                $challenge->{$readyColumn} = now();
            }

            if ($challenge->challenger_ready_at && $challenge->acceptor_ready_at && ! $challenge->started_at) {
                $challenge->started_at = now();
            }

            $challenge->save();

            return $challenge;
        });

        return $this->sendResponse(
            $this->challengeFlowPayload($challenge->fresh()),
            $challenge->started_at
                ? 'Both players are ready. Challenge match started.'
                : 'Ready confirmed. Waiting for the other player.'
        );
    }

    /**
     * Submit the player's result or report for admin review.
     */
    public function submitResult(Request $request, $id)
    {
        $request->validate([

            'score' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'evidence_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'evidence_video' => 'nullable|file|mimes:mp4,mov,webm,avi|max:51200',
        ]);

        if (
            ! $request->filled('score')
            && ! $request->filled('notes')
            && ! $request->hasFile('evidence_image')
            && ! $request->hasFile('evidence_video')
        ) {
            abort(422, 'Submit a score, note, photo, or video for admin review.');
        }

        $user = auth('api')->user();

        $challenge = DB::transaction(function () use ($request, $user, $id) {

            $challenge = Challenge::with('publishedMatch')->lockForUpdate()->findOrFail($id);

            if ($challenge->publishedMatch) {
                abort(400, 'This challenge is published as an official match. Use match management instead.');
            }

            if (! in_array($challenge->status, [ChallengeStatus::ACCEPTED, ChallengeStatus::UNDER_REVIEW], true)) {
                abort(400, 'This challenge is not ready for result submission.');
            }

            if (! $challenge->started_at) {
                abort(400, 'Both players must be ready before submitting a result.');
            }

            if (! $this->isChallengePlayer($challenge, $user->id)) {
                abort(403, 'Only challenge players can submit results.');
            }

            $existingSubmission = ChallengeSubmission::where('challenge_id', $challenge->id)
                ->where('user_id', $user->id)
                ->first();

            $data = [
                'submission_type' => 'result',
                'score' => $request->score,
                'notes' => $request->notes,
            ];

            if ($request->hasFile('evidence_image')) {
                $this->deleteEvidence($existingSubmission?->getRawOriginal('evidence_image'));
                $data['evidence_image'] = $request->file('evidence_image')
                    ->store('challenge-evidence');
            }

            if ($request->hasFile('evidence_video')) {
                $this->deleteEvidence($existingSubmission?->getRawOriginal('evidence_video'));
                $data['evidence_video'] = $request->file('evidence_video')
                    ->store('challenge-evidence');
            }

            $submission = ChallengeSubmission::updateOrCreate([
                'challenge_id' => $challenge->id,
                'user_id' => $user->id,
            ], $data);

            if ($challenge->status === ChallengeStatus::ACCEPTED) {
                $challenge->update(['status' => ChallengeStatus::UNDER_REVIEW]);
            }

            if (! $challenge->submitted_for_review_at) {
                $challenge->update(['submitted_for_review_at' => now()]);
            }

            DB::afterCommit(function () use ($challenge, $submission) {
                $this->notifyAdmins(new ChallengeSubmittedForReviewNotification($challenge, $submission));

                $adminIds = User::role('super_admin')->pluck('id')->toArray();
                broadcast(new ChallengeUnderReview($challenge, $adminIds))->toOthers();
            });

            return $challenge;
        });

        return $this->sendResponse(
            $this->challengeFlowPayload($challenge->fresh()),
            'Challenge result submitted for admin review.'
        );
    }

    /**
     * Challenger cancels their own offer before it is accepted.
     */
    public function cancel($id)
    {
        $user = auth('api')->user();

        DB::transaction(function () use ($user, $id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if ($challenge->challenger_id !== $user->id) {
                abort(403, 'You can only cancel your own challenge.');
            }

            if (! in_array($challenge->status, [ChallengeStatus::PENDING, ChallengeStatus::OFFERED], true)) {
                abort(400, 'This challenge can no longer be cancelled.');
            }

            $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            $challenge->update(['status' => ChallengeStatus::CANCELLED]);
        });

        return $this->sendResponse([], 'Challenge cancelled and refunded.');
    }

    /**
     * Public ranked list of challenge offers, ordered by amount desc.
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->where('status', ChallengeStatus::OFFERED->value)
            ->excludingExpiredOffers()
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->orderByAmountDesc()
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $offset = ($paginator->currentPage() - 1) * $paginator->perPage();

        $paginator->getCollection()->transform(function ($challenge, $index) use ($offset) {
            $challenge->rank = $offset + $index + 1;

            return $challenge;
        });

        return $this->sendResponse(
            ChallengeResource::collection($paginator),
            'Challenges retrieved successfully',
        );
    }

    /**
     * Full challenge detail page payload.
     */
    public function show($id)
    {
        $challenge = Challenge::query()
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->findOrFail($id);

        $challenge->rank = Challenge::query()
            ->where('status', ChallengeStatus::OFFERED->value)
            ->excludingExpiredOffers()
            ->where(function ($q) use ($challenge) {
                $q->where('amount', '>', $challenge->amount)
                    ->orWhere(function ($q) use ($challenge) {
                        $q->where('amount', $challenge->amount)
                            ->where('id', '>', $challenge->id);
                    });
            })
            ->count() + 1;

        $admin = User::role(UserRole::SUPER_ADMIN->value)->first();

        return $this->sendResponse(
            (new ChallengeResource($challenge))->withModel($admin),
            'Challenge retrieved successfully',
        );
    }

    /**
     * Challenges created by a given user (for their profile).
     */
    public function userChallenges(Request $request, $id)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->where('challenger_id', $id)
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->sendResponse(
            ChallengeResource::collection($paginator),
            'User challenges retrieved successfully',
        );
    }

    /**
     * Challenges accepted by a given user that are still in play (for their profile).
     */
    public function acceptedChallenges(Request $request, $id)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->where('accepted_by_user_id', $id)
            ->where('status', ChallengeStatus::ACCEPTED->value)
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->sendResponse(
            ChallengeResource::collection($paginator),
            'Accepted challenges retrieved successfully',
        );
    }

    /**
     * Challenges accepted by a given user that have since been completed (for their profile).
     */
    public function completedChallenges(Request $request, $id)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->where('accepted_by_user_id', $id)
            ->where('status', ChallengeStatus::COMPLETED->value)
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->sendResponse(
            ChallengeResource::collection($paginator),
            'Completed challenges retrieved successfully',
        );
    }

    /**
     * Challenges directed at the authenticated user (incoming offers).
     *
     * Defaults to live offers awaiting a response. Pass `?status=all` to see
     * every challenge ever addressed to the user, or a specific status value.
     */
    public function incoming(Request $request)
    {
        $user = auth('api')->user();
        $perPage = $request->per_page ?? 10;
        $status = $request->input('status', ChallengeStatus::OFFERED->value);

        $paginator = Challenge::query()
            ->where('target_player_id', $user->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->sendResponse(
            ChallengeResource::collection($paginator),
            'Incoming challenges retrieved successfully',
        );
    }

    /**
     * Top challengers by total staked amount.
     */
    public function leaderboard(Request $request)
    {
        $limit = $request->limit ?? 10;

        $leaders = Challenge::query()
            ->selectRaw('challenger_id, SUM(amount) as total_amount')
            ->groupBy('challenger_id')
            ->orderByDesc('total_amount')
            ->with('challenger:id,name,artist_name,first_name,image')
            ->limit($limit)
            ->get()
            ->values()
            ->map(function ($row, $index) {
                $user = $row->challenger;

                return [
                    'rank' => $index + 1,
                    'user_id' => $row->challenger_id,
                    'name' => $user?->artist_name ?: $user?->first_name,
                    'image' => $user?->image_url,
                    'total_amount' => $row->total_amount,
                ];
            });

        return $this->sendResponse($leaders, 'Big boss challengers retrieved successfully');
    }

    private function userCanCreate(User $user): bool
    {
        return (bool) $user->is_challenger;
    }

    private function generateChallengeNo(): string
    {
        do {
            $no = (string) random_int(100000, 999999);
        } while (Challenge::where('challenge_no', $no)->exists());

        return $no;
    }

    private function notifyAdmins($notification): void
    {
        User::role('super_admin')->get()
            ->each(fn ($admin) => $admin->notify($notification));
    }

    private function ensureRegularAcceptedChallenge(Challenge $challenge): void
    {
        if ($challenge->publishedMatch) {
            abort(400, 'This challenge is published as an official match. Use match management instead.');
        }

        if ($challenge->status !== ChallengeStatus::ACCEPTED) {
            abort(400, 'This challenge is not ready for match flow.');
        }
    }

    private function readyColumnFor(Challenge $challenge, int $userId): ?string
    {
        if ($challenge->challenger_id === $userId) {
            return 'challenger_ready_at';
        }

        if ($challenge->accepted_by_user_id === $userId) {
            return 'acceptor_ready_at';
        }

        return null;
    }

    private function isChallengePlayer(Challenge $challenge, int $userId): bool
    {
        return in_array($userId, [
            $challenge->challenger_id,
            $challenge->accepted_by_user_id,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function challengeFlowPayload(Challenge $challenge): array
    {
        return [
            'challenge_id' => $challenge->id,
            'status' => $challenge->status?->value,
            'challenger_ready_at' => $challenge->challenger_ready_at?->toIso8601String(),
            'acceptor_ready_at' => $challenge->acceptor_ready_at?->toIso8601String(),
            'both_players_ready' => $challenge->challenger_ready_at !== null && $challenge->acceptor_ready_at !== null,
            'started_at' => $challenge->started_at?->toIso8601String(),
            'submitted_for_review_at' => $challenge->submitted_for_review_at?->toIso8601String(),
        ];
    }

    private function deleteEvidence(?string $path): void
    {
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
    }
}
