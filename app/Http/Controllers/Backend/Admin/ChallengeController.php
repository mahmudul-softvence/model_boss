<?php

namespace App\Http\Controllers\Backend\Admin;

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
use App\Notifications\ChallengeWonNotification;
use App\Services\ChallengeEscrowService;
use App\Services\ChallengeSettlementService;
use Carbon\Carbon;
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

    /**
     * List challenges for admin management.
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $paginator = Challenge::query()
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game', 'publishedMatch'])
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

    /**
     * Approve a pending challenge so it becomes visible/acceptable on the frontend.
     */
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

    /**
     * Reject a pending challenge and refund the challenger.
     */
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

    /**
     * Declare the winner of an accepted challenge and settle the pool.
     */
    public function winner(Request $request, $id)
    {
        $challenge = Challenge::with('publishedMatch')->findOrFail($id);

        if ($challenge->publishedMatch) {
            return response()->json([
                'status' => false,
                'message' => 'This challenge has been published as a match. Select the winner from the match management.',
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

        try {
            $result = $this->settlement->settle($challenge, $winnerId);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        $loserId = $winnerId === $challenge->challenger_id
            ? $challenge->accepted_by_user_id
            : $challenge->challenger_id;

        $challenge->loadMissing(['challenger', 'acceptor']);

        $winner = User::find($winnerId);
        $loser = User::find($loserId);

        $winner?->notify(new ChallengeWonNotification($challenge, (float) $result['winner_payout']));
        $loser?->notify(new ChallengeLostNotification($challenge, (float) $challenge->amount));

        return response()->json([
            'status' => true,
            'message' => 'Winner declared and pool settled successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Admin cancels a challenge and refunds every held stake.
     */
    public function cancel($id)
    {
        DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if (! in_array($challenge->status, [
                ChallengeStatus::PENDING,
                ChallengeStatus::OFFERED,
                ChallengeStatus::ACCEPTED,
            ], true)) {
                abort(400, 'This challenge can no longer be cancelled.');
            }

            $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            if ($challenge->status === ChallengeStatus::ACCEPTED && $challenge->accepted_by_user_id) {
                $this->escrow->refund($challenge->accepted_by_user_id, (float) $challenge->amount, $challenge);
            }

            $challenge->update(['status' => ChallengeStatus::CANCELLED]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Challenge cancelled and all stakes refunded.',
        ]);
    }

    /**
     * Delete a challenge, refunding any stakes still held.
     */
    public function destroy($id)
    {
        DB::transaction(function () use ($id) {

            $challenge = Challenge::lockForUpdate()->findOrFail($id);

            if (in_array($challenge->status, [
                ChallengeStatus::PENDING,
                ChallengeStatus::OFFERED,
                ChallengeStatus::ACCEPTED,
            ], true)) {
                $this->escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

                if ($challenge->status === ChallengeStatus::ACCEPTED && $challenge->accepted_by_user_id) {
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

    /**
     * Publish an accepted challenge as a match in game_matches.
     */
    public function publishMatch(Request $request, $id)
    {
        $challenge = Challenge::with(['challenger', 'acceptor', 'game'])->findOrFail($id);

        if ($challenge->status !== ChallengeStatus::ACCEPTED) {
            return response()->json([
                'status' => false,
                'message' => 'Only accepted challenges can be published as a match.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'player_one_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'player_two_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'type' => 'nullable|string|in:upcoming',
            'match_date' => 'nullable|date',
            'match_time' => 'nullable|date_format:H:i',
            'winner_percentage' => 'nullable|in:0,1',
            'loser_percentage' => 'nullable|in:0,1',
            'tiktok_link' => 'nullable|url',
            'twitch_link' => 'nullable|url',
            'rules' => 'nullable|string',
            'voting_time' => 'nullable|date',
            'confirmation_status' => 'nullable|in:0,1,2',
            'pin_to_top' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();

        $data['player_one_id'] = $challenge->challenger_id;
        $data['player_two_id'] = $challenge->accepted_by_user_id;
        $data['game_id'] = $challenge->game_id;
        $data['match_type'] = 'challenge';
        $data['type'] = $request->type ?? 'upcoming';
        $data['match_date'] = $request->match_date ?? $challenge->match_date;
        $data['match_time'] = $request->match_time ?? $challenge->match_time;
        $data['winner_percentage'] = $request->winner_percentage ?? 0;
        $data['loser_percentage'] = $request->loser_percentage ?? 0;
        $data['pin_to_top'] = $request->pin_to_top ?? 0;
        $data['confirmation_status'] = $request->confirmation_status ?? 0;
        $data['remove_status'] = 0;

        if ($request->hasFile('player_one_logo')) {
            $data['player_one_logo'] = $request->file('player_one_logo')->store('logos', 'public');
        }

        if ($request->hasFile('player_two_logo')) {
            $data['player_two_logo'] = $request->file('player_two_logo')->store('logos', 'public');
        }

        do {
            $matchNo = random_int(100000, 999999);
        } while (GameMatch::where('match_no', $matchNo)->exists());

        $data['challenge_id'] = $challenge->id;
        $data['match_no'] = $matchNo;

        $betAmount = (float) $challenge->amount;
        $data['player_one_bet'] = $betAmount;
        $data['player_two_bet'] = $betAmount;
        $data['player_one_total'] = $betAmount;
        $data['player_two_total'] = $betAmount;

        if ($request->filled('voting_time')) {
            $data['voting_time'] = Carbon::parse($request->voting_time);
        }

        $match = GameMatch::create($data);

        $match->load([
            'game:id,name',
            'playerOne:id,name',
            'playerTwo:id,name',
        ]);

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
            null
        ))->toOthers();

        broadcast(new MatchCreated(
            $players,
            'A match has been created and you have been selected as a player. Please review the rules carefully.',
            $players,
            $data['rules'] ?? null
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

    /**
     * Challenge dashboard stats for the admin.
     */
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

    /**
     * Grant a user permission to create challenges.
     */
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

    /**
     * Revoke a user's permission to create challenges.
     */
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
}
