<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\UserBalance;

class CreditPointPurchase
{
    public function execute(User $user, float $amount, string $reference, ?string $invoicePdf = null): void
    {
        $balance = $user->userBalance()->lockForUpdate()->first();

        if (! $balance) {
            $balance = $user->userBalance()->create(['total_balance' => 0]);
        }

        $balance->increment('total_balance', $amount);
        $balance->increment('total_recharge', $amount);
        $balance->refresh();

        $adminBalance = UserBalance::where('user_id', 1)->lockForUpdate()->first();

        if (! $adminBalance) {
            $adminBalance = UserBalance::create([
                'user_id' => 1,
                'total_balance' => 0,
            ]);
        }

        $adminBalance->increment('total_recharge', $amount);

        CoinTransaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::RECHARGE,
            'amount' => $amount,
            'balance_after' => $balance->total_balance,
            'reference' => $reference,
            'invoice_pdf' => $invoicePdf,
        ]);
    }
}
