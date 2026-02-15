<?php

namespace App\Services;

use App\Models\User;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Transfer;

class StripeConnectService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function createOrGetAccount(User $user)
    {
        if ($user->stripe_account_id) {
            return $user->stripe_account_id;
        }

        $account = Account::create([
            'type' => 'express',
            'email' => $user->email,
        ]);

        $user->update([
            'stripe_account_id' => $account->id
        ]);

        return $account->id;
    }

    public function generateOnboardingLink(User $user)
    {
        $accountLink = AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => route('stripe.refresh'),
            'return_url' => route('stripe.return'),
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    public function createTransfer(User $user, float $amount)
    {
        return Transfer::create([
            'amount' => $amount * 100,
            'currency' => 'usd',
            'destination' => $user->stripe_account_id,
        ]);
    }
}
