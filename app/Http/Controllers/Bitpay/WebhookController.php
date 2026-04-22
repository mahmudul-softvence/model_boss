<?php

namespace App\Http\Controllers\Bitpay;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BitpayPayment;
use App\Models\User;
use App\Services\BitpayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly BitpayService $bitpayService,
        private readonly CreditPointPurchase $creditPointPurchase,
    ) {}

    public function handleWebhook(Request $request): Response
    {
        $invoiceId = trim((string) ($request->input('invoiceId') ?? $request->input('id') ?? ''));
        $status = $request->input('status');

        if (! $invoiceId) {
            return response('', 400);
        }

        Log::info('BitPay webhook received', [
            'invoiceId' => $invoiceId,
            'status' => $status,
        ]);

        try {
            $invoice = $this->bitpayService->retrieveInvoice($invoiceId);

            return $this->processPayment($invoice, $invoiceId);
        } catch (\Throwable $e) {
            Log::error('BitPay webhook error: '.$e->getMessage(), [
                'invoiceId' => $invoiceId,
            ]);

            return response('', 500);
        }
    }

    protected function processPayment(array $invoice, string $invoiceId): Response
    {
        $payment = BitpayPayment::where('bitpay_invoice_id', $invoiceId)->first();

        if (! $payment) {
            Log::warning('BitPay payment not found', ['invoiceId' => $invoiceId]);

            return response('', 404);
        }

        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return response('', 200);
        }

        $isCompleted = $this->bitpayService->isPaymentCompleted($invoice);
        $isFailed = $this->bitpayService->isPaymentFailed($invoice);

        if ($isFailed) {
            $payment->update(['status' => PaymentStatus::FAILED->value]);

            return response('', 200);
        }

        if (! $isCompleted) {
            return response('', 200);
        }

        DB::transaction(function () use ($payment, $invoice) {
            $payment->update([
                'status' => PaymentStatus::COMPLETED->value,
                'payer' => $invoice['buyer']['email'] ?? null,
            ]);

            $user = User::findOrFail($payment->user_id);

            $this->creditPointPurchase->execute(
                $user,
                (float) $payment->coin_amount,
                $payment->bitpay_invoice_id,
            );
        });

        return response('', 200);
    }
}
