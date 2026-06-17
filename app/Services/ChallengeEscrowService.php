<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\CoinTransaction;
use App\Models\UserBalance;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChallengeEscrowService
{
    /**
     * Reserve (escrow) a player's stake by moving it out of spendable balance.
     * Must be called inside a DB transaction.
     */
    public function hold(int $userId, float $amount, Challenge $challenge): void
    {
        $balance = UserBalance::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($balance->total_balance < $amount) {
            throw new HttpException(400, 'Insufficient balance');
        }

        $balance->decrement('total_balance', $amount);

        CoinTransaction::create([
            'user_id' => $userId,
            'type' => 'challenge-hold',
            'amount' => -$amount,
            'balance_after' => $balance->fresh()->total_balance,
            'reference' => 'Challenge stake #'.$challenge->challenge_no,
        ]);
    }

    /**
     * Release a held stake back to the player's spendable balance.
     * Must be called inside a DB transaction.
     */
    public function refund(int $userId, float $amount, Challenge $challenge): void
    {
        $balance = UserBalance::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();

        $balance->increment('total_balance', $amount);

        CoinTransaction::create([
            'user_id' => $userId,
            'type' => 'challenge-refund',
            'amount' => $amount,
            'balance_after' => $balance->fresh()->total_balance,
            'reference' => 'Challenge refund #'.$challenge->challenge_no,
        ]);
    }
}
