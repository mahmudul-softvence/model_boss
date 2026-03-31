<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\StripePayment;
use App\Models\Withdrawal;
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
                'success_url' => config('app.frontend_url') . '/payment-success',
                'cancel_url'  => config('app.frontend_url') . '/payment-cancel',
                'metadata' => [
                    'user_id' => $user->id,
                    'amount'  => $amount,
                ],

                'invoice_creation' => [
                    'enabled' => true,
                ]
            ],
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
}
