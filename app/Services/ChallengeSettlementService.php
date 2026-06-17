<?php

namespace App\Services;

use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\CoinTransaction;
use App\Models\UserBalance;
use Illuminate\Support\Facades\DB;

class ChallengeSettlementService
{
    /**
     * Admin/platform account that collects the 15% fee.
     */
    private const ADMIN_USER_ID = 1;

    /**
     * Platform fee taken from the combined stake pool.
     */
    private const PLATFORM_FEE_RATE = 0.15;

    /**
     * Settle a challenge match: winner takes the combined stake pool minus 15%,
     * the admin keeps the 15%, and the loser's stake is forfeited into the pool.
     *
     * Both stakes were already moved out of spendable balance at create/accept,
     * so settlement only credits the winner and the admin.
     *
     * @return array{pool: float, winner_payout: float, admin_fee: float}
     */
    public function settle(Challenge $challenge, int $winnerId): array
    {
        return DB::transaction(function () use ($challenge, $winnerId) {

            $challenge = Challenge::lockForUpdate()->findOrFail($challenge->id);

            if ($challenge->status === ChallengeStatus::COMPLETED) {
                throw new \RuntimeException('Challenge has already been settled.');
            }

            if ($challenge->status !== ChallengeStatus::ACCEPTED) {
                throw new \RuntimeException('Challenge is not ready to be settled.');
            }

            $players = [$challenge->challenger_id, $challenge->accepted_by_user_id];

            if (! in_array($winnerId, $players, true)) {
                throw new \RuntimeException('Winner must be one of the two players.');
            }

            $stake = (float) $challenge->amount;
            $pool = $stake * 2;
            $adminFee = round($pool * self::PLATFORM_FEE_RATE, 2);
            $winnerPayout = round($pool - $adminFee, 2);

            $winnerBalance = UserBalance::where('user_id', $winnerId)
                ->lockForUpdate()
                ->firstOrFail();

            $winnerBalance->increment('total_balance', $winnerPayout);
            $winnerBalance->increment('total_earning', $winnerPayout);

            CoinTransaction::create([
                'user_id' => $winnerId,
                'type' => 'challenge-win',
                'amount' => $winnerPayout,
                'balance_after' => $winnerBalance->fresh()->total_balance,
                'reference' => 'Challenge Win #'.$challenge->challenge_no,
            ]);

            $adminBalance = UserBalance::where('user_id', self::ADMIN_USER_ID)
                ->lockForUpdate()
                ->first();

            if ($adminBalance) {
                $adminBalance->increment('total_balance', $adminFee);
                $adminBalance->increment('total_earning', $adminFee);

                CoinTransaction::create([
                    'user_id' => self::ADMIN_USER_ID,
                    'type' => 'challenge-fee',
                    'amount' => $adminFee,
                    'balance_after' => $adminBalance->fresh()->total_balance,
                    'reference' => 'Challenge Fee #'.$challenge->challenge_no,
                ]);
            }

            $challenge->update([
                'winner_id' => $winnerId,
                'status' => ChallengeStatus::COMPLETED,
                'settled_at' => now(),
            ]);

            return [
                'pool' => $pool,
                'winner_payout' => $winnerPayout,
                'admin_fee' => $adminFee,
            ];
        });
    }
}
