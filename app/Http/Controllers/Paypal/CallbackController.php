<?php

namespace App\Http\Controllers\Paypal;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaypalPayment;
use App\Models\User;
use App\Services\PaypalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CallbackController extends Controller
{
    public function __construct(
        private readonly PaypalService $paypalService,
        private readonly CreditPointPurchase $creditPointPurchase,
    ) {}

    public function handleReturn(Request $request): RedirectResponse
    {
        $paypalOrderId = $this->requestValue($request, 'token');

        if (! $paypalOrderId) {
            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'paypal',
                'reason' => 'missing_token',
            ]));
        }

        try {
            $payment = PaypalPayment::where('paypal_order_id', $paypalOrderId)->first();

            if (! $payment) {
                throw new RuntimeException('PayPal payment not found.');
            }

            if ($payment->status === PaymentStatus::COMPLETED->value) {
                return redirect()->away($this->frontendRedirectUrl('payment-success', [
                    'provider' => 'paypal',
                ]));
            }

            if ($payment->status === PaymentStatus::FAILED->value) {
                return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                    'provider' => 'paypal',
                ]));
            }

            $capturedOrder = $this->paypalService->captureApprovedOrder($paypalOrderId);

            $redirectPath = DB::transaction(function () use ($payment, $capturedOrder): string {
                $payment = PaypalPayment::whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($payment->status === PaymentStatus::COMPLETED->value) {
                    return 'payment-success';
                }

                $purchaseUnit = $this->firstPurchaseUnit($capturedOrder);
                $providerOrderId = trim((string) ($capturedOrder['id'] ?? ''));
                $localOrderId = (string) $this->purchaseUnitValue($purchaseUnit, ['custom_id', 'reference_id', 'invoice_id'], '');
                $capture = data_get($purchaseUnit, 'payments.captures.0', []);
                $captureStatus = strtoupper((string) data_get($capture, 'status', $capturedOrder['status'] ?? ''));
                $captureId = data_get($capture, 'id');
                $payer = data_get($capturedOrder, 'payer.email_address');
                $amount = (float) data_get($capture, 'amount.value', data_get($purchaseUnit, 'amount.value', 0));

                if ($providerOrderId === '') {
                    throw new RuntimeException('PayPal capture did not return an order ID.');
                }

                if ($payment->paypal_order_id !== $providerOrderId) {
                    throw new RuntimeException('PayPal order mismatch.');
                }

                if ($localOrderId !== '' && $payment->order_id !== $localOrderId) {
                    throw new RuntimeException('PayPal purchase reference mismatch.');
                }

                if ($this->amountInCents($payment->amount) !== $this->amountInCents($amount)) {
                    throw new RuntimeException('PayPal amount mismatch.');
                }

                $isSuccessful = $captureStatus === 'COMPLETED';

                $this->updatePayment(
                    $payment,
                    $captureId,
                    $payer,
                    $isSuccessful ? PaymentStatus::COMPLETED->value : PaymentStatus::FAILED->value,
                );

                if (! $isSuccessful) {
                    return 'payment-cancel';
                }

                $user = User::findOrFail($payment->user_id);

                $this->creditPointPurchase->execute(
                    $user,
                    (float) $payment->coin_amount,
                    $payment->capture_id ?: $payment->paypal_order_id,
                );

                return 'payment-success';
            });

            return redirect()->away($this->frontendRedirectUrl($redirectPath, [
                'provider' => 'paypal',
            ]));
        } catch (\Throwable $e) {
            Log::error('PayPal callback error: ' . $e->getMessage(), [
                'token' => $paypalOrderId,
            ]);

            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'paypal',
            ]));
        }
    }

    public function handleCancel(Request $request): RedirectResponse
    {
        $paypalOrderId = $this->requestValue($request, 'token');
        $reason = $this->requestValue($request, 'errorcode');

        if ($paypalOrderId) {
            DB::transaction(function () use ($paypalOrderId): void {
                $payment = PaypalPayment::where('paypal_order_id', $paypalOrderId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment || $payment->status === PaymentStatus::COMPLETED->value) {
                    return;
                }

                $payment->update([
                    'status' => PaymentStatus::FAILED->value,
                ]);
            });
        }

        return redirect()->away($this->frontendRedirectUrl('payment-cancel', array_filter([
            'provider' => 'paypal',
            'reason' => $reason ?: null,
        ])));
    }

    protected function requestValue(Request $request, string $key): ?string
    {
        $value = $request->query($key, $request->input($key));

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function frontendRedirectUrl(string $path, array $parameters = []): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            . '/'
            . ltrim($path, '/')
            . (empty($parameters) ? '' : '?' . http_build_query($parameters));
    }

    protected function amountInCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    protected function firstPurchaseUnit(array $capturedOrder): array
    {
        $purchaseUnit = $capturedOrder['purchase_units'][0] ?? null;

        if (! is_array($purchaseUnit)) {
            throw new RuntimeException('PayPal capture did not return purchase unit details.');
        }

        return $purchaseUnit;
    }

    protected function purchaseUnitValue(array $purchaseUnit, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $purchaseUnit) && $purchaseUnit[$key] !== null) {
                return $purchaseUnit[$key];
            }
        }

        return $default;
    }

    protected function updatePayment(
        PaypalPayment $payment,
        mixed $captureId = null,
        mixed $payer = null,
        ?string $status = null,
    ): void {
        $attributes = array_filter([
            'capture_id' => is_string($captureId) && trim($captureId) !== '' ? trim($captureId) : null,
            'payer' => is_string($payer) && trim($payer) !== '' ? trim($payer) : null,
            'status' => $status,
        ], static fn(mixed $value): bool => $value !== null);

        if ($attributes !== []) {
            $payment->update($attributes);
        }
    }
}
