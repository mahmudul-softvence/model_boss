<?php

namespace App\Http\Controllers\Withdraw\Stripe;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
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

        if (! $user->stripe_account_id) {

            $account = Account::create([
                'type' => 'express',
                'email' => $user->email,
            ]);

            $user->stripe_account_id = $account->id;
            $user->save();
        }

        $link = AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => config('app.frontend_url').'/'.config('app.frontend_account_connect_failed'),
            'return_url' => config('app.frontend_url').'/'.config('app.frontend_account_connect'),
            'type' => 'account_onboarding',
        ]);

        $data = [
            'url' => $link->url,
        ];

        return $this->sendResponse($data);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        $hasPending = Withdrawal::where('user_id', $user->id)
            ->where('payment_method', 'stripe')
            ->where('status', WithdrawalStatus::PENDING->value)
            ->exists();

        if ($hasPending) {
            return $this->sendError('Cannot disconnect while a withdrawal is pending.', 422);
        }

        $user->stripe_account_id = null;
        $user->stripe_onboarding_complete = false;
        $user->save();

        return $this->sendResponse(['connected' => false]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        $account = Account::retrieve($user->stripe_account_id);

        if ($account->payouts_enabled) {

            $user->stripe_onboarding_complete = true;
            $user->save();
        }

        return response()->json([
            'connected' => $account->payouts_enabled,
        ]);
    }
}
