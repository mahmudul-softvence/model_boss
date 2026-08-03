<?php

namespace App\Http\Controllers\Payment\Stripe;

use App\Actions\CreditPointPurchase;
use App\Enums\PaymentStatus;
use App\Enums\WithdrawalStatus;
use App\Models\StripePayment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\UserWithdrawalCompletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends CashierController
{
    public function __construct(private readonly CreditPointPurchase $creditPointPurchase)
    {
        parent::__construct();
    }

    public function handleWebhook(Request $request): Response
    {
        if (! filled(config('cashier.webhook.secret'))) {
            Log::critical('Stripe webhook rejected because webhook secret is not configured.');

            return response('Webhook secret not configured', 500);
        }

        return parent::handleWebhook($request);
    }

    public function handleCheckoutSessionCompleted($payload)
    {
        $session = $payload['data']['object'];

        if ($session['payment_status'] !== 'paid') {
            return response()->json(['status' => 'not paid']);
        }

        try {
            DB::transaction(function () use ($session) {
                $payment = StripePayment::where('stripe_payment_id', $session['id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($payment->status === PaymentStatus::COMPLETED->value) {
                    return;
                }

                $invoicePdf = null;
                if (! empty($session['invoice'])) {
                    $stripe = new StripeClient(config('cashier.secret'));
                    $invoice = $stripe->invoices->retrieve($session['invoice']);
                    $invoicePdf = $invoice->invoice_pdf;
                }

                $userId = $session['metadata']['user_id'];
                $amount = $session['metadata']['amount'];
                $user = User::findOrFail($userId);

                if ((int) ($amount * 100) !== $session['amount_total']) {
                    throw new \Exception('Amount mismatch');
                }

                if ($session['customer'] !== $user->stripe_id) {
                    throw new \Exception('Customer mismatch');
                }

                $payment->update([
                    'status' => PaymentStatus::COMPLETED->value,
                ]);

                $this->creditPointPurchase->execute(
                    $user,
                    (float) $amount,
                    $session['id'],
                    $invoicePdf,
                );
            });

            return response()->noContent();
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error: '.$e->getMessage());

            return response()->json(['error' => 'Webhook failed'], 500);
        }
    }

    public function handlePaymentIntentPaymentFailed($payload)
    {
        $intent = $payload['data']['object'];

        $this->markPaymentFailed($intent['id']);

        return response()->noContent();
    }

    public function handleCheckoutSessionExpired($payload)
    {
        $session = $payload['data']['object'];

        $this->markPaymentFailed($session['id']);

        return response()->noContent();
    }

    public function handleChargeFailed($payload)
    {
        $charge = $payload['data']['object'];

        $this->markPaymentFailed($charge['payment_intent']);

        return response()->noContent();
    }

    public function handleTransferCreated($payload)
    {
        $transfer = $payload['data']['object'];

        DB::transaction(function () use ($transfer) {

            $withdraw = Withdrawal::where('stripe_transfer_id', $transfer['id'])
                ->lockForUpdate()
                ->first();

            if (! $withdraw) {
                return;
            }

            if ($withdraw->status !== WithdrawalStatus::ACCEPTED->value) {
                return;
            }

            $withdraw->update([
                'status' => WithdrawalStatus::PAID,
            ]);

            $withdraw->user->notify(new UserWithdrawalCompletedNotification($withdraw));
        });

        return response()->noContent();
    }

    public function handleTransferFailed($payload)
    {
        $transfer = $payload['data']['object'];

        DB::transaction(function () use ($transfer) {

            $withdraw = Withdrawal::where('stripe_transfer_id', $transfer['id'])
                ->lockForUpdate()
                ->first();

            if (! $withdraw) {
                return;
            }

            if ($withdraw->status !== WithdrawalStatus::ACCEPTED->value) {
                return;
            }

            $balance = $withdraw->user->userBalance()->lockForUpdate()->first();

            if ($balance) {
                $balance->increment('total_balance', $withdraw->coin_amount);
                $balance->decrement('total_withdraw', min($withdraw->coin_amount, $balance->total_withdraw));
            }

            $withdraw->update([
                'status' => WithdrawalStatus::DECLINED,
            ]);
        });

        return response()->noContent();
    }

    public function handleTransferReversed($payload)
    {
        return $this->handleTransferFailed($payload);
    }

    protected function markPaymentFailed(string $stripeId)
    {
        $payment = StripePayment::where('stripe_payment_id', $stripeId)->first();

        if (! $payment) {
            return;
        }

        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::FAILED->value,
        ]);
    }
}
