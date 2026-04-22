<?php

namespace App\Http\Controllers\Coinbase;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CoinbasePayment;
use App\Models\User;
use App\Services\CoinbaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly CoinbaseService $coinbaseService,
        private readonly CreditPointPurchase $creditPointPurchase,
    ) {}

    public function handleWebhook(Request $request): Response
    {
        $chargeId = trim((string) ($request->input('event.data.id') ?? $request->input('id') ?? ''));
        $eventType = trim((string) ($request->input('event.type') ?? ''));

        if ($chargeId === '') {
            return response('', 400);
        }

        Log::info('Coinbase webhook received', [
            'charge_id' => $chargeId,
            'event_type' => $eventType,
        ]);

        try {
            $charge = $this->coinbaseService->retrieveCharge($chargeId);

            return $this->processPayment($charge, $chargeId);
        } catch (\Throwable $exception) {
            Log::error('Coinbase webhook error: '.$exception->getMessage(), [
                'charge_id' => $chargeId,
            ]);

            return response('', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    protected function processPayment(array $charge, string $chargeId): Response
    {
        $payment = CoinbasePayment::where('coinbase_charge_id', $chargeId)->first();

        if (! $payment) {
            Log::warning('Coinbase payment not found', ['charge_id' => $chargeId]);

            return response('', 404);
        }

        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return response('', 200);
        }

        if ($this->coinbaseService->isPaymentFailed($charge)) {
            $payment->update(['status' => PaymentStatus::FAILED->value]);

            return response('', 200);
        }

        if (! $this->coinbaseService->isPaymentCompleted($charge)) {
            return response('', 200);
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => PaymentStatus::COMPLETED->value,
            ]);

            $user = User::findOrFail($payment->user_id);

            $this->creditPointPurchase->execute(
                $user,
                (float) $payment->coin_amount,
                $payment->coinbase_charge_id,
            );
        });

        return response('', 200);
    }
}
