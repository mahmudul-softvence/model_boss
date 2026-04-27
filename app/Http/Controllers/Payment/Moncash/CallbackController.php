<?php

namespace App\Http\Controllers\Payment\Moncash;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MoncashPayment;
use App\Models\User;
use App\Services\MoncashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function __construct(
        private readonly MoncashService $moncashService,
        private readonly CreditPointPurchase $creditPointPurchase,
    ) {}

    public function handle(Request $request): RedirectResponse
    {
        $encryptedTransactionId = $this->requestValue($request, 'transactionId');
        $orderId = $this->requestValue($request, 'orderId');

        if (! $encryptedTransactionId && ! $orderId) {
            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'moncash',
                'reason' => 'missing_transaction',
            ]));
        }

        try {
            $paymentDetails = $this->moncashService->retrievePaymentFromCallback(
                $encryptedTransactionId,
                $orderId,
            );

            $reference = (string) ($paymentDetails['reference'] ?? '');

            if ($reference === '') {
                throw new \RuntimeException('MonCash response did not include an order reference.');
            }

            $redirectPath = DB::transaction(function () use ($paymentDetails, $reference): string {
                $payment = MoncashPayment::where('order_id', $reference)
                    ->lockForUpdate()
                    ->firstOrFail();

                $transactionId = $this->paymentValue($paymentDetails, ['transaction_id', 'transNumber']);
                $payer = $this->paymentValue($paymentDetails, ['payer']);

                if ($payment->status === PaymentStatus::COMPLETED->value) {
                    $this->updatePayment($payment, $transactionId, $payer);

                    return 'payment-success';
                }

                $amount = (float) $this->paymentValue($paymentDetails, ['cost', 'amount'], 0);

                if ($this->amountInCents($payment->amount) !== $this->amountInCents($amount)) {
                    throw new \RuntimeException('MonCash amount mismatch.');
                }

                $isSuccessful = $this->isSuccessfulPayment($paymentDetails);

                $this->updatePayment(
                    $payment,
                    $transactionId,
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
                    $payment->transaction_id ?: $payment->order_id,
                );

                return 'payment-success';
            });

            return redirect()->away($this->frontendRedirectUrl($redirectPath, [
                'provider' => 'moncash',
            ]));
        } catch (\Throwable $e) {
            Log::error('MonCash callback error: '.$e->getMessage(), [
                'transactionId' => $encryptedTransactionId,
                'orderId' => $orderId,
            ]);

            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'moncash',
            ]));
        }
    }

    protected function requestValue(Request $request, string $key): ?string
    {
        $value = $request->query($key, $request->input($key));

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function isSuccessfulPayment(array $paymentDetails): bool
    {
        $paymentStatus = $this->paymentValue($paymentDetails, ['payment_status', 'success']);

        if (is_bool($paymentStatus)) {
            return $paymentStatus;
        }

        if (is_numeric($paymentStatus)) {
            return (bool) $paymentStatus;
        }

        $message = strtolower((string) $this->paymentValue($paymentDetails, ['message', 'payment_msg', 'msg'], ''));

        return in_array($message, ['successful', 'success', 'completed', 'paid'], true);
    }

    protected function frontendRedirectUrl(string $path, array $parameters = []): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/'
            .ltrim($path, '/')
            .(empty($parameters) ? '' : '?'.http_build_query($parameters));
    }

    protected function amountInCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function paymentValue(array $paymentDetails, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $paymentDetails) && $paymentDetails[$key] !== null) {
                return $paymentDetails[$key];
            }
        }

        return $default;
    }

    protected function updatePayment(
        MoncashPayment $payment,
        mixed $transactionId = null,
        mixed $payer = null,
        ?string $status = null,
    ): void {
        $attributes = array_filter([
            'transaction_id' => is_string($transactionId) && trim($transactionId) !== '' ? trim($transactionId) : null,
            'payer' => is_string($payer) && trim($payer) !== '' ? trim($payer) : null,
            'status' => $status,
        ], static fn (mixed $value): bool => $value !== null);

        if ($attributes !== []) {
            $payment->update($attributes);
        }
    }
}
