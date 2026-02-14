<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\StripePayment;
use Illuminate\Http\Request;

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

        return response()->json([
            'url' => $checkout->url,
        ]);
    }


    // Route::middleware('auth:sanctum')->post('/checkout', function (Request $request) {});

}
