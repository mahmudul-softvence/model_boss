<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserBalance;

class ProfileBalanceData
{
    /**
     * @return array{
     *     total_earning: mixed,
     *     total_referral_earning: mixed,
     *     total_tip_received: mixed,
     *     total_withdraw: mixed,
     *     total_balance: mixed,
     *     total_bet: mixed
     * }
     */
    public static function forUser(User $user, ?UserBalance $userBalance = null, ?User $viewer = null): array
    {
        $userBalance ??= $user->userBalance;
        $isOwner = $viewer?->is($user) ?? false;

        return [
            'total_earning' => self::visibleAmount($user, $userBalance, $isOwner, 'total_earning', 'show_total_earning'),
            'total_referral_earning' => self::visibleAmount($user, $userBalance, $isOwner, 'total_referral_earning', 'show_total_referral_earning'),
            'total_tip_received' => self::visibleAmount($user, $userBalance, $isOwner, 'total_tip_received', 'show_total_tip_received'),
            'total_withdraw' => self::visibleAmount($user, $userBalance, $isOwner, 'total_withdraw', 'show_total_withdraw'),
            'total_balance' => self::visibleAmount($user, $userBalance, $isOwner, 'total_balance', 'show_total_balance'),
            'total_bet' => self::visibleAmount($user, $userBalance, $isOwner, 'total_bet', 'show_total_bet'),
        ];
    }

    private static function visibleAmount(User $user, ?UserBalance $userBalance, bool $isOwner, string $amountField, string $visibilityField): mixed
    {
        if ($isOwner || (bool) $user->{$visibilityField}) {
            return $userBalance?->{$amountField} ?? 0;
        }

        return null;
    }
}
