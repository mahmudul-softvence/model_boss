<?php

namespace App\Http\Controllers\Withdraw\Paypal;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AdminWithdrawalNotification;
use App\Services\PaypalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PaypalWithdrawController extends Controller
{
    public function __construct(private readonly PaypalService $paypal) {}

    public function request(Request $request)
    {
        $request->validate([
            'coin_amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();

        if (! $user->paypal_email) {
            return $this->sendError('PayPal account not connected.', [], 400);
        }

        if ($request->coin_amount < 10) {
            return $this->sendError('Minimum 10 points required.', [], 400);
        }

        try {
            $autoAccept = Setting::where('key', 'auto_accept_withdrawals')->value('value') === 'true';

            $withdraw = DB::transaction(function () use ($request, $user) {
                $balance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->total_balance < $request->coin_amount) {
                    throw new \Exception('Insufficient balance.');
                }

                $balance->decrement('total_balance', $request->coin_amount);

                return Withdrawal::create([
                    'user_id' => $user->id,
                    'payment_method' => 'paypal',
                    'payout_account' => $user->paypal_email,
                    'withdraw_no' => 'WD'.Str::ulid(),
                    'coin_amount' => $request->coin_amount,
                    'usd_amount' => $request->coin_amount,
                    'status' => WithdrawalStatus::PENDING,
                ]);
            });

            if ($autoAccept) {
                $this->processPaypalPayout($withdraw, $user);
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

    private function processPaypalPayout(Withdrawal $withdraw, User $user): void
    {
        try {
            $batchId = $this->paypal->sendPayout(
                $user->paypal_email,
                (float) $withdraw->usd_amount,
                $withdraw->withdraw_no,
            );

            DB::transaction(function () use ($withdraw, $batchId, $user) {
                $lockedWithdraw = Withdrawal::query()
                    ->lockForUpdate()
                    ->findOrFail($withdraw->id);

                if ($lockedWithdraw->status !== WithdrawalStatus::PENDING->value) {
                    return;
                }

                $balance = $user->userBalance()
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedWithdraw->update([
                    'status' => WithdrawalStatus::ACCEPTED,
                    'paypal_payout_id' => $batchId,
                ]);

                $balance->increment('total_withdraw', $lockedWithdraw->coin_amount);

                $user->coinTransactions()->create([
                    'type' => TransactionType::WITHDRAW,
                    'amount' => $lockedWithdraw->coin_amount,
                    'balance_after' => $balance->total_balance,
                    'reference' => $batchId,
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

            $balance = $user->userBalance()
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $balance->increment('total_balance', $lockedWithdraw->coin_amount);
            }

            $lockedWithdraw->update([
                'status' => WithdrawalStatus::DECLINED,
            ]);
        });
    }
}
