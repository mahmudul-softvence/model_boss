<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MoncashPayment;
use App\Models\StripePayment;
use App\Services\MoncashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private readonly MoncashService $moncashService) {}

    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', 'string', Rule::in(['stripe', 'moncash'])],
        ]);

        $user = $request->user();
        $amount = (float) $request->input('amount');
        $paymentMethod = strtolower((string) $request->input('payment_method', 'stripe'));

        if ($paymentMethod === 'moncash') {
            return $this->checkoutWithMoncash($user, $amount);
        }

        return $this->checkoutWithStripe($user, $amount);
    }

    protected function checkoutWithStripe($user, float $amount): JsonResponse
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

        return $this->sendResponse([
            'url' => $checkout->url,
            'payment_method' => 'stripe',
        ]);
    }

    protected function checkoutWithMoncash($user, float $amount): JsonResponse
    {
        $orderId = (string) Str::uuid();
        $checkout = $this->moncashService->createCheckout($amount, $orderId);

        MoncashPayment::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'coin_amount' => $amount,
            'status' => PaymentStatus::PENDING->value,
        ]);

        return $this->sendResponse([
            'url' => $checkout['url'],
            'payment_method' => 'moncash',
        ]);
    }
}
