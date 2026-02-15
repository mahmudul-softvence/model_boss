<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;

class StripeConnectController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        if (!$user->stripe_account_id) {
            $account = Account::create([
                'type' => 'express',
                'email' => $user->email,
            ]);

            $user->update([
                'stripe_account_id' => $account->id
            ]);
        }

        $link = AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => config('app.frontend_url') . '/stripe/refresh',
            'return_url'  => config('app.frontend_url') . '/stripe/return',
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'url' => $link->url
        ]);
    }


    public function status(Request $request)
    {
        $user = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        $account = Account::retrieve($user->stripe_account_id);

        if ($account->payouts_enabled) {
            $user->update([
                'stripe_onboarding_complete' => true
            ]);
        }

        return response()->json([
            'connected' => $account->payouts_enabled
        ]);
    }
}
