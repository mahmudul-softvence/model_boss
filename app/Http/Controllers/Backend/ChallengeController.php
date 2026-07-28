<?php

namespace App\Http\Controllers\Backend;

use App\Enums\ChallengeMode;
use App\Enums\ChallengeStatus;
use App\Events\MatchCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\ChallengeApprovedNotification;
use App\Notifications\ChallengeLostNotification;
use App\Notifications\ChallengeOfferNotification;
use App\Notifications\ChallengeRejectedNotification;
use App\Notifications\ChallengeWinnerDeclaredNotification;
use App\Notifications\ChallengeWonNotification;
use App\Services\ChallengeEscrowService;
use App\Services\ChallengeSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChallengeController extends Controller
{
    public function __construct(
        private ChallengeEscrowService $escrow,
        private ChallengeSettlementService $settlement,
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game', 'publishedMatch', 'submissions.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('challenge_no', 'like', "%{$request->search}%");
            })
            ->orderByStatusPriority()
            ->orderByAmountDesc()
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Challenges retrieved successfully',
            'data' => ChallengeResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'prev' => $paginator->currentPage() > 1,
                'next' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function approve($id)
    {
        $challenge = DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if ($challenge->status !== ChallengeStatus::PENDING) {
                abort(400, 'Only pending challenges can be approved.');
            }

            $challenge->update([
                'status' => ChallengeStatus::OFFERED,
                'approved_at' => now(),
            ]);

            return $challenge;
        });

        $challenge->challenger?->notify(new ChallengeApprovedNotification($challenge));

        if ($challenge->mode === ChallengeMode::UNIQUE && $challenge->targetPlayer) {
            $challenge->targetPlayer->notify(new ChallengeOfferNotification($challenge));
        }

        return response()->json([
            'status' => true,
            'message' => 'Challenge approved and is now live.',
        ]);
    }

    public function reject($id)
    {
        $challenge = DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if ($challenge->status !== ChallengeStatus::PENDING) {
                abort(400, 'Only pending challenges can be rejected.');
            }

            $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            $challenge->update(['status' => ChallengeStatus::REJECTED]);

            return $challenge;
        });

        $challenge->challenger?->notify(new ChallengeRejectedNotification($challenge));

        return response()->json([
            'status' => true,
            'message' => 'Challenge rejected and the challenger has been refunded.',
        ]);
    }

    public function winner(Request $request, $id)
    {
        $challenge = Challenge::with('publishedMatch')->findOrFail($id);

        if ($challenge->publishedMatch) {
            return response()->json([
                'status' => false,
                'message' => 'This challenge has been published as a match. Select the winner from the match management.',
            ], 400);
        }

        if (! in_array($challenge->status, [ChallengeStatus::ACCEPTED, ChallengeStatus::UNDER_REVIEW, ChallengeStatus::WINNER_PENDING], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Challenge must be accepted or under review to declare a winner.',
            ], 400);
        }

        $request->validate([
            'winner_id' => [
                'required',
                'exists:users,id',
                Rule::in([$challenge->challenger_id, $challenge->accepted_by_user_id]),
            ],
        ]);

        $winnerId = (int) $request->winner_id;

        $challenge->update([
            'winner_id' => $winnerId,
            'status' => ChallengeStatus::WINNER_PENDING,
            'admin_reviewed_at' => now(),
        ]);

        $loserId = $winnerId === $challenge->challenger_id
            ? $challenge->accepted_by_user_id
            : $challenge->challenger_id;

        $challenge->loadMissing(['challenger', 'acceptor']);

        $winner = User::find($winnerId);
        $loser = User::find($loserId);

        $winner?->notify(new ChallengeWinnerDeclaredNotification($challenge));
        $loser?->notify(new ChallengeLostNotification($challenge, (float) $challenge->amount));

        return response()->json([
            'status' => true,
            'message' => 'Winner declared',
        ]);
    }

    public function releasePayout($id)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->status !== ChallengeStatus::WINNER_PENDING) {
            return response()->json([
                'status' => false,
                'message' => 'Payout can only be released for challenges with a declared winner waiting for settlement.',
            ], 400);
        }

        if (! $challenge->winner_id) {
            return response()->json([
                'status' => false,
                'message' => 'No winner has been declared for this challenge.',
            ], 400);
        }

        try {
            $result = $this->settlement->settle($challenge, $challenge->winner_id);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        $challenge->loadMissing(['challenger', 'acceptor']);

        $winner = User::find($challenge->winner_id);

        $winner?->notify(new ChallengeWonNotification($challenge, (float) $result['winner_payout']));

        return response()->json([
            'status' => true,
            'message' => 'Payout released successfully.',
            'data' => $result,
        ]);
    }

    public function submissions($id)
    {
        $challenge = Challenge::query()
            ->with([
                'challenger',
                'acceptor',
                'game',
                'publishedMatch',
                'submissions.user',
            ])
            ->findOrFail($id);

        if ($challenge->publishedMatch) {
            return response()->json([
                'status' => false,
                'message' => 'This challenge is published as a match. Review it from match management.',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Challenge submissions retrieved successfully',
            'data' => new ChallengeResource($challenge),
        ]);
    }

    public function cancel($id)
    {
        DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if (! in_array($challenge->status, [
                ChallengeStatus::PENDING,
                ChallengeStatus::OFFERED,
                ChallengeStatus::ACCEPTED,
                ChallengeStatus::UNDER_REVIEW,
                ChallengeStatus::WINNER_PENDING,
            ], true)) {
                abort(400, 'This challenge can no longer be cancelled.');
            }

            if ($this->hasActivePublishedMatch($challenge)) {
                abort(400, 'This challenge has been published as a match. Manage it from the match management.');
            }

            $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            if (in_array($challenge->status, [ChallengeStatus::ACCEPTED, ChallengeStatus::UNDER_REVIEW, ChallengeStatus::WINNER_PENDING], true) && $challenge->accepted_by_user_id) {
                $this->escrow->refund($challenge->accepted_by_user_id, (float) $challenge->amount, $challenge);
            }

            $challenge->update(['status' => ChallengeStatus::CANCELLED]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Challenge cancelled and all stakes refunded.',
        ]);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if (in_array($challenge->status, [
                ChallengeStatus::PENDING,
                ChallengeStatus::OFFERED,
                ChallengeStatus::ACCEPTED,
                ChallengeStatus::UNDER_REVIEW,
                ChallengeStatus::WINNER_PENDING,
            ], true)) {
                if ($this->hasActivePublishedMatch($challenge)) {
                    abort(400, 'This challenge has been published as a match. Manage it from the match management.');
                }

                $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

                if (in_array($challenge->status, [ChallengeStatus::ACCEPTED, ChallengeStatus::UNDER_REVIEW, ChallengeStatus::WINNER_PENDING], true) && $challenge->accepted_by_user_id) {
                    $this->escrow->refund($challenge->accepted_by_user_id, (float) $challenge->amount, $challenge);
                }
            }

            $challenge->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Challenge deleted.',
        ]);
    }

    public function publishMatch(Request $request, $id)
    {
        $challenge = Challenge::with(['challenger', 'acceptor', 'game'])->findOrFail($id);

        if ($challenge->status !== ChallengeStatus::ACCEPTED) {
            return response()->json([
                'status' => false,
                'message' => 'Only accepted challenges can be published as a match.',
            ], 400);
        }

        if ($challenge->publishedMatch()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'This challenge has already been published as a match.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:upcoming',
            'player_one_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'player_two_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'winner_percentage' => 'nullable|in:0,1',
            'loser_percentage' => 'nullable|in:0,1',
            'tiktok_link' => 'nullable|url',
            'twitch_link' => 'nullable|url',
            'rules' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $betAmount = (float) $challenge->amount;

        $data = [
            'player_one_id' => $challenge->challenger_id,
            'player_two_id' => $challenge->accepted_by_user_id,
            'game_id' => $challenge->game_id,
            'match_type' => 'challenge',
            'challenge_id' => $challenge->id,
            'type' => $request->type ?? 'upcoming',
            'match_date' => $challenge->match_date,
            'match_time' => $challenge->match_time,
            'winner_percentage' => $request->winner_percentage ?? 0,
            'loser_percentage' => $request->loser_percentage ?? 0,
            'tiktok_link' => $request->tiktok_link,
            'twitch_link' => $request->twitch_link,
            'rules' => $request->rules,
            'pin_to_top' => 0,
            'confirmation_status' => 0,
            'remove_status' => 0,
            'player_one_bet' => $betAmount,
            'player_two_bet' => $betAmount,
            'player_one_total' => $betAmount,
            'player_two_total' => $betAmount,
        ];

        if ($request->hasFile('player_one_logo')) {
            $data['player_one_logo'] = $request->file('player_one_logo')->store('logos');
        }

        if ($request->hasFile('player_two_logo')) {
            $data['player_two_logo'] = $request->file('player_two_logo')->store('logos');
        }

        do {
            $matchNo = random_int(100000, 999999);
        } while (GameMatch::where('match_no', $matchNo)->exists());

        $data['match_no'] = $matchNo;

        try {
            $match = DB::transaction(function () use ($id, $data) {

                $challenge = Challenge::lockForUpdate()->find($id);

                if (! $challenge || $challenge->status !== ChallengeStatus::ACCEPTED) {
                    throw new \RuntimeException('Only accepted challenges can be published as a match.');
                }

                if ($challenge->publishedMatch()->exists()) {
                    throw new \RuntimeException('This challenge has already been published as a match.');
                }

                return GameMatch::create($data);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        $users = User::role(['user', 'artist'])->pluck('id')->toArray();

        $players = [
            $data['player_one_id'],
            $data['player_two_id'],
        ];

        $otherUsers = array_diff($users, $players);

        broadcast(new MatchCreated(
            $otherUsers,
            'New match available! Go to home to support your favorite player.',
            $players,
            null,
            $match->id
        ))->toOthers();

        broadcast(new MatchCreated(
            $players,
            'A match has been created and you have been selected as a player. Please review the rules carefully.',
            $players,
            $data['rules'] ?? null,
            $match->id
        ))->toOthers();

        return response()->json([
            'status' => true,
            'message' => 'Challenge published as match successfully',
            'data' => $match->load([
                'game:id,name',
                'playerOne:id,name',
                'playerTwo:id,name',
            ]),
        ], 201);
    }

    public function stats()
    {
        $pendingInvested = Challenge::holding()->sum('amount');

        $completedStakes = Challenge::where('status', ChallengeStatus::COMPLETED->value)->sum('amount');
        $totalWinnings = round($completedStakes * 2 * 0.85, 2);

        $biggest = Challenge::query()
            ->selectRaw('challenger_id, SUM(amount) as total_amount')
            ->groupBy('challenger_id')
            ->orderByDesc('total_amount')
            ->with('challenger:id,name,artist_name,first_name')
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Challenge stats retrieved successfully',
            'data' => [
                'pending_invested' => $pendingInvested,
                'total_winnings_paid' => $totalWinnings,
                'biggest_challenger' => $biggest ? [
                    'user_id' => $biggest->challenger_id,
                    'name' => $biggest->challenger?->artist_name ?: $biggest->challenger?->first_name,
                    'total_amount' => $biggest->total_amount,
                ] : null,
            ],
        ]);
    }

    public function grantAccess($userId)
    {
        $user = User::findOrFail($userId);

        $user->is_challenger = true;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Challenge creation access granted.',
        ]);
    }

    public function revokeAccess($userId)
    {
        $user = User::findOrFail($userId);

        $user->is_challenger = false;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Challenge creation access revoked.',
        ]);
    }

    private function hasActivePublishedMatch(Challenge $challenge): bool
    {
        $match = $challenge->publishedMatch()->first();

        return $match !== null && (int) $match->confirmation_status !== 2;
    }
}
