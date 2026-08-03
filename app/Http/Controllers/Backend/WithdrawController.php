<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use App\Notifications\UserWithdrawalDeclinedNotification;
use App\Services\PaypalService;
use Illuminate\Support\Facades\DB;
use Stripe\Account;
use Stripe\Stripe;
use Stripe\Transfer;
use Symfony\Component\HttpFoundation\Request;

class WithdrawController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $withdraw_req = Withdrawal::latest()->paginate($limit);

        return $this->sendResponse(WithdrawalResource::collection($withdraw_req));
    }

    public function accept($id)
    {
        try {
            $withdraw = DB::transaction(function () use ($id) {
                $withdraw = Withdrawal::with('user')
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($withdraw->status !== WithdrawalStatus::PENDING->value) {
                    throw new \Exception('Already processed.');
                }

                if (! $withdraw->user->userBalance()->lockForUpdate()->exists()) {
                    throw new \Exception('User balance not found.');
                }

                return $withdraw;
            });

            $user = $withdraw->user;

            if ($withdraw->payment_method === 'stripe') {
                $this->acceptStripeWithdrawal($withdraw, $user);
            } elseif ($withdraw->payment_method === 'paypal') {
                $this->acceptPaypalWithdrawal($withdraw, $user);
            } else {
                DB::transaction(function () use ($withdraw, $user) {
                    $lockedWithdraw = Withdrawal::query()
                        ->lockForUpdate()
                        ->findOrFail($withdraw->id);

                    if ($lockedWithdraw->status !== WithdrawalStatus::PENDING->value) {
                        throw new \Exception('Already processed.');
                    }

                    $userBalance = $user->userBalance()
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedWithdraw->update([
                        'status' => WithdrawalStatus::ACCEPTED,
                    ]);

                    $user->coinTransactions()->create([
                        'type' => TransactionType::WITHDRAW,
                        'amount' => $lockedWithdraw->coin_amount,
                        'balance_after' => $userBalance->total_balance,
                        'reference' => $lockedWithdraw->withdraw_no,
                    ]);

                    $userBalance->increment('total_withdraw', $lockedWithdraw->coin_amount);
                });
            }

            return $this->sendResponse([], 'Withdrawal accepted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    public function declined($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $withdraw = Withdrawal::lockForUpdate()
                    ->findOrFail($id);

                if ($withdraw->status !== WithdrawalStatus::PENDING->value) {
                    throw new \Exception('Already processed.');
                }

                $balance = $withdraw->user->userBalance()
                    ->lockForUpdate()
                    ->first();

                $balance->increment(
                    'total_balance',
                    $withdraw->coin_amount
                );

                $withdraw->update([
                    'status' => WithdrawalStatus::DECLINED,
                ]);

                $withdraw->user->notify(new UserWithdrawalDeclinedNotification($withdraw));
            });

            return $this->sendResponse([], 'Withdrawal declined and refunded.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    private function acceptStripeWithdrawal(Withdrawal $withdraw, $user): void
    {
        Stripe::setApiKey(config('cashier.secret'));

        if (! $user->stripe_account_id) {
            throw new \Exception('User Stripe not connected.');
        }

        $account = Account::retrieve($user->stripe_account_id);

        if (! $account->payouts_enabled) {
            throw new \Exception('Stripe account not ready.');
        }

        $transfer = Transfer::create([
            'amount' => (int) round($withdraw->usd_amount * 100),
            'currency' => 'usd',
            'destination' => $user->stripe_account_id,
            'description' => 'Withdrawal '.$withdraw->withdraw_no,
        ], [
            'idempotency_key' => 'withdraw-'.$withdraw->withdraw_no,
        ]);

        DB::transaction(function () use ($withdraw, $transfer, $user) {
            $lockedWithdraw = Withdrawal::query()
                ->lockForUpdate()
                ->findOrFail($withdraw->id);

            if ($lockedWithdraw->status !== WithdrawalStatus::PENDING->value) {
                throw new \Exception('Already processed.');
            }

            $userBalance = $user->userBalance()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedWithdraw->update([
                'status' => WithdrawalStatus::ACCEPTED,
                'stripe_transfer_id' => $transfer->id,
            ]);

            $user->coinTransactions()->create([
                'type' => TransactionType::WITHDRAW,
                'amount' => $lockedWithdraw->coin_amount,
                'balance_after' => $userBalance->total_balance,
                'reference' => $transfer->id,
            ]);

            $userBalance->increment('total_withdraw', $lockedWithdraw->coin_amount);
        });
    }

    private function acceptPaypalWithdrawal(Withdrawal $withdraw, $user): void
    {
        if (! $user->paypal_email) {
            throw new \Exception('User PayPal not connected.');
        }

        $batchId = app(PaypalService::class)->sendPayout(
            $user->paypal_email,
            (float) $withdraw->usd_amount,
            $withdraw->withdraw_no,
        );

        DB::transaction(function () use ($withdraw, $batchId, $user) {
            $lockedWithdraw = Withdrawal::query()
                ->lockForUpdate()
                ->findOrFail($withdraw->id);

            if ($lockedWithdraw->status !== WithdrawalStatus::PENDING->value) {
                throw new \Exception('Already processed.');
            }

            $userBalance = $user->userBalance()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedWithdraw->update([
                'status' => WithdrawalStatus::ACCEPTED,
                'paypal_payout_id' => $batchId,
            ]);

            $user->coinTransactions()->create([
                'type' => TransactionType::WITHDRAW,
                'amount' => $lockedWithdraw->coin_amount,
                'balance_after' => $userBalance->total_balance,
                'reference' => $batchId,
            ]);

            $userBalance->increment('total_withdraw', $lockedWithdraw->coin_amount);
        });
    }
}
