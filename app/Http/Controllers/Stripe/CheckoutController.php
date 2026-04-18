<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BitpayPayment;
use App\Models\MoncashPayment;
use App\Services\BitpayService;
use App\Services\MoncashService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly MoncashService $moncashService,
        private readonly BitpayService $bitpayService,
        private readonly StripeService $stripeService,
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', 'string', Rule::in(['stripe', 'moncash', 'bitpay'])],
        ]);

        $user = $request->user();
        $amount = (float) $request->input('amount');
        $paymentMethod = strtolower((string) $request->input('payment_method', 'stripe'));

        if ($paymentMethod === 'moncash') {
            return $this->checkoutWithMoncash($user, $amount);
        }

        if ($paymentMethod === 'bitpay') {
            return $this->checkoutWithBitpay($user, $amount);
        }

        return $this->checkoutWithStripe($user, $amount);
    }

    protected function checkoutWithStripe($user, float $amount): JsonResponse
    {
        $checkout = $this->stripeService->createCheckout($user, $amount);

        return $this->sendResponse([
            'url' => $checkout['url'],
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

    protected function checkoutWithBitpay($user, float $amount): JsonResponse
    {
        $orderId = (string) Str::uuid();
        $checkout = $this->bitpayService->createCheckout($amount, $orderId);

        BitpayPayment::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'bitpay_invoice_id' => $checkout['invoice_id'],
            'amount' => $amount,
            'coin_amount' => $amount,
            'status' => PaymentStatus::PENDING->value,
        ]);

        return $this->sendResponse([
            'url' => $checkout['url'],
            'payment_method' => 'bitpay',
        ]);
    }
}
