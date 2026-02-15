<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\StripePayment;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Stripe\Account;

class CheckoutController extends Controller
{

    public function checkout(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
        ]);

        $user = $request->user();
        $amount = $request->amount;

        $user->createOrGetStripeCustomer();

        $checkout = $user->checkoutCharge(
            $amount * 100,
            'One Time Custom Payment',
            1,
            [
                'success_url' => config('app.frontend_url') . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => config('app.frontend_url') . '/payment-cancel',
                'metadata' => [
                    'user_id' => $user->id,
                    'amount'  => $amount,
                ],
            ]
        );

        StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $checkout->id,
            'usd_amount' => $amount,
            'coin_amount' => $amount,
            'status' => PaymentStatus::PENDING,
        ]);

        $data = [
            'url' => $checkout->url,
        ];

        return $this->sendResponse($data);
    }


    public function connect_account()
    {
        $user = auth()->user();

        $service = app(StripeConnectService::class);

        $service->createOrGetAccount($user);

        return redirect(
            $service->generateOnboardingLink($user)
        );
    }



    public function stripe_return()
    {
        $user = auth()->user();

        $account = Account::retrieve($user->stripe_account_id);

        if ($account->payouts_enabled) {
            $user->update([
                'stripe_onboarding_complete' => true
            ]);
        }

        return redirect()->route('dashboard');
    }
}
