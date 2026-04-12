<?php

namespace App\Http\Controllers\Netcash;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\NetcashPayment;
use App\Models\User;
use App\Services\NetcashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function __construct(
        private readonly NetcashService $netcashService,
        private readonly CreditPointPurchase $creditPointPurchase,
    ) {}

    public function notify(Request $request): Response
    {
        try {
            $this->processCallback($request);

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Netcash notify error: '.$e->getMessage(), [
                'reference' => $request->input('Reference'),
                'request_trace' => $request->input('RequestTrace'),
            ]);

            return response('ERROR', 500);
        }
    }

    public function accept(Request $request): RedirectResponse
    {
        return $this->redirectForCallback($request, 'payment-success');
    }

    public function decline(Request $request): RedirectResponse
    {
        return $this->redirectForCallback($request, 'payment-cancel');
    }

    public function redirect(Request $request): RedirectResponse
    {
        return $this->redirectForCallback($request, 'payment-cancel');
    }

    protected function redirectForCallback(Request $request, string $defaultPath): RedirectResponse
    {
        try {
            $status = $this->processCallback($request);

            if ($status === PaymentStatus::COMPLETED->value) {
                return redirect()->away($this->frontendRedirectUrl('payment-success', [
                    'provider' => 'netcash',
                ]));
            }

            if ($status === PaymentStatus::PENDING->value) {
                return redirect()->away($this->frontendRedirectUrl($defaultPath, [
                    'provider' => 'netcash',
                    'status' => 'pending',
                ]));
            }

            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'netcash',
            ]));
        } catch (\Throwable $e) {
            Log::error('Netcash callback error: '.$e->getMessage(), [
                'reference' => $request->input('Reference'),
                'request_trace' => $request->input('RequestTrace'),
            ]);

            return redirect()->away($this->frontendRedirectUrl('payment-cancel', [
                'provider' => 'netcash',
            ]));
        }
    }

    protected function processCallback(Request $request): string
    {
        $requestTrace = $this->requestValue($request, 'RequestTrace');
        $traceDetails = $requestTrace
            ? $this->netcashService->traceTransaction($requestTrace)
            : $request->all();

        $postedReference = $this->requestValue($request, 'Reference');
        $reference = $this->arrayValue($traceDetails, ['Reference', 'reference'], $postedReference);

        if (! is_string($reference) || trim($reference) === '') {
            throw new \RuntimeException('Missing Netcash reference.');
        }

        $reference = trim($reference);

        if ($postedReference && $postedReference !== $reference) {
            throw new \RuntimeException('Netcash reference mismatch.');
        }

        return DB::transaction(function () use ($traceDetails, $reference, $requestTrace): string {
            $payment = NetcashPayment::where('reference', $reference)
                ->lockForUpdate()
                ->firstOrFail();
            $originalStatus = $payment->status;

            $amount = (float) $this->arrayValue($traceDetails, ['Amount', 'amount'], 0);

            if ($this->amountInCents($payment->amount) !== $this->amountInCents($amount)) {
                throw new \RuntimeException('Netcash amount mismatch.');
            }

            $reason = $this->normalizeString($this->arrayValue($traceDetails, ['Reason', 'reason']));
            $method = $this->normalizeString($this->arrayValue($traceDetails, ['Method', 'method']));
            $status = $this->resolveStatus($traceDetails, $reason);

            $payment->update(array_filter([
                'request_trace' => $requestTrace,
                'payment_method' => $method,
                'reason' => $reason,
                'status' => $status,
            ], static fn (mixed $value): bool => $value !== null));

            if ($originalStatus !== PaymentStatus::COMPLETED->value && $status === PaymentStatus::COMPLETED->value) {
                $user = User::findOrFail($payment->user_id);

                $this->creditPointPurchase->execute(
                    $user,
                    (float) $payment->coin_amount,
                    $payment->request_trace ?: $payment->reference,
                );
            }

            return $status;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveStatus(array $payload, ?string $reason): string
    {
        $accepted = $this->arrayValue($payload, ['TransactionAccepted', 'transaction_accepted']);

        if (is_bool($accepted)) {
            return $accepted
                ? PaymentStatus::COMPLETED->value
                : $this->pendingOrFailedStatus($reason);
        }

        if (is_numeric($accepted)) {
            return ((bool) $accepted)
                ? PaymentStatus::COMPLETED->value
                : $this->pendingOrFailedStatus($reason);
        }

        if (is_string($accepted)) {
            return filter_var($accepted, FILTER_VALIDATE_BOOLEAN)
                ? PaymentStatus::COMPLETED->value
                : $this->pendingOrFailedStatus($reason);
        }

        return $this->pendingOrFailedStatus($reason);
    }

    protected function pendingOrFailedStatus(?string $reason): string
    {
        return strcasecmp((string) $reason, 'Pending payment') === 0
            ? PaymentStatus::PENDING->value
            : PaymentStatus::FAILED->value;
    }

    protected function requestValue(Request $request, string $key): ?string
    {
        $value = $request->query($key, $request->input($key));

        return $this->normalizeString($value);
    }

    protected function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function arrayValue(array $payload, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                return $payload[$key];
            }
        }

        return $default;
    }
}
