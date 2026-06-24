<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Enums\ChallengeMode;
use App\Enums\ChallengeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\User;
use App\Notifications\ChallengeApprovedNotification;
use App\Notifications\ChallengeLostNotification;
use App\Notifications\ChallengeOfferNotification;
use App\Notifications\ChallengeRejectedNotification;
use App\Notifications\ChallengeWonNotification;
use App\Services\ChallengeEscrowService;
use App\Services\ChallengeSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['challenger', 'targetPlayer', 'acceptor', 'game'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('challenge_no', 'like', "%{$request->search}%");
            })
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
        $challenge = Challenge::findOrFail($id);

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
