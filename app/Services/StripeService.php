<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\StripePayment;
use App\Models\User;

class StripeService
{
    public function createCheckout(User $user, float $amount): array
    {
        $user->createOrGetStripeCustomer();

        $checkout = $user->checkoutCharge(
            $amount * 100,
            'One Time Custom Payment',
            1,
            [
                'success_url' => config('app.frontend_url') . '/payment-success',
                'cancel_url' => config('app.frontend_url') . '/payment-cancel',
                'metadata' => [
                    'user_id' => $user->id,
                    'amount' => $amount,
                ],
                'invoice_creation' => [
                    'enabled' => true,
                ],
            ],
        );

        StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $checkout->id,
            'usd_amount' => $amount,
            'coin_amount' => $amount,
            'status' => PaymentStatus::PENDING->value,
        ]);

        return [
            'stripe_payment_id' => $checkout->id,
            'url' => $checkout->url,
        ];
    }
}
