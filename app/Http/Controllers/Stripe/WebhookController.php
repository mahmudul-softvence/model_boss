<?php

namespace App\Http\Controllers\Stripe;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\CoinTransaction;
use App\Models\StripePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;


class WebhookController extends CashierController
{
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

                if ($payment->status === PaymentStatus::COMPLETED) {
                    return;
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
                    'status' => PaymentStatus::COMPLETED
                ]);

                $balance = $user->userBalance()->lockForUpdate()->first();

                if (!$balance) {
                    $balance = $user->userBalance()->create([
                        'total_balance' => 0
                    ]);
                }

                $balance->increment('total_balance', $amount);
                $balance->refresh();

                CoinTransaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::RECHARGE,
                    'amount' => $amount,
                    'balance_after' => $balance->total_balance,
                    'reference' => $session['id'],
                ]);
            });

            return response()->noContent();
        } catch (\Throwable $e) {

            Log::error('Stripe webhook error: ' . $e->getMessage());

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



    protected function markPaymentFailed(string $stripeId)
    {
        $payment = StripePayment::where('stripe_payment_id', $stripeId)->first();

        if (!$payment) {
            return;
        }

        if ($payment->status === PaymentStatus::COMPLETED) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::FAILED
        ]);
    }
}
