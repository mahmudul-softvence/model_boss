<?php

namespace App\Http\Controllers\Withdraw\Stripe;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AdminWithdrawalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Stripe\Account;
use Stripe\Stripe;
use Stripe\Transfer;

class StripeWithdrawController extends Controller
{
    public function request(Request $request)
    {
        Stripe::setApiKey(config('cashier.secret'));

        $request->validate([
            'coin_amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();

        if (! $user->stripe_onboarding_complete) {
            return $this->sendError('Stripe not connected.', [], 400);
        }

        if ($request->coin_amount < 10) {
            return $this->sendError('Minimum 10 points required.', [], 400);
        }

        if (! $user->stripe_account_id) {
            return $this->sendError('User Stripe not connected.', [], 400);
        }

        try {
            $autoAccept = Setting::where('key', 'auto_accept_withdrawals')->value('value') === 'true';

            $withdraw = DB::transaction(function () use ($request, $user) {
                $userBalance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (! $userBalance || $userBalance->total_balance < $request->coin_amount) {
                    throw new \Exception('Insufficient balance.');
                }

                $userBalance->decrement('total_balance', $request->coin_amount);

                return Withdrawal::create([
                    'user_id' => $user->id,
                    'payment_method' => 'stripe',
                    'payout_account' => $user->stripe_account_id,
                    'withdraw_no' => 'WD'.Str::ulid(),
                    'coin_amount' => $request->coin_amount,
                    'usd_amount' => $request->coin_amount,
                    'status' => WithdrawalStatus::PENDING,
                ]);
            });

            if ($autoAccept) {
                $this->processStripePayout($withdraw, $user);
            } else {
                $superAdmin = User::role('super_admin')->first();
                if ($superAdmin) {
                    Notification::send($superAdmin, new AdminWithdrawalNotification($withdraw, $user));
                }
            }

            return $this->sendResponse($withdraw->fresh());
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    private function processStripePayout(Withdrawal $withdraw, User $user): void
    {
        try {
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
                    return;
                }

                $userBalance = $user->userBalance()
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedWithdraw->update([
                    'status' => WithdrawalStatus::ACCEPTED,
                    'stripe_transfer_id' => $transfer->id,
                ]);

                $userBalance->increment('total_withdraw', $lockedWithdraw->coin_amount);

                $user->coinTransactions()->create([
                    'type' => TransactionType::WITHDRAW,
                    'amount' => $lockedWithdraw->coin_amount,
                    'balance_after' => $userBalance->total_balance,
                    'reference' => $transfer->id,
                ]);

                $superAdmin = User::role('super_admin')->first();
                if ($superAdmin) {
                    Notification::send($superAdmin, new AdminWithdrawalNotification($lockedWithdraw, $user));
                }
            });
        } catch (\Throwable $e) {
            $this->refundHeldWithdrawal($withdraw, $user);

            throw $e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e);
        }
    }

    private function refundHeldWithdrawal(Withdrawal $withdraw, User $user): void
    {
        DB::transaction(function () use ($withdraw, $user) {
            $lockedWithdraw = Withdrawal::query()
                ->lockForUpdate()
                ->find($withdraw->id);

            if (! $lockedWithdraw || $lockedWithdraw->status !== WithdrawalStatus::PENDING->value) {
                return;
            }

            $userBalance = $user->userBalance()
                ->lockForUpdate()
                ->first();

            if ($userBalance) {
                $userBalance->increment('total_balance', $lockedWithdraw->coin_amount);
            }

            $lockedWithdraw->update([
                'status' => WithdrawalStatus::DECLINED,
            ]);
        });
    }
}
