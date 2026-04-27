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
            DB::transaction(function () use ($request, $user, &$withdraw) {
                $setting = Setting::where('key', 'auto_accept_withdrawals')->first();

                $balance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->total_balance < $request->coin_amount) {
                    throw new \Exception('Insufficient balance.');
                }

                $balance->decrement('total_balance', $request->coin_amount);

                $withdraw = Withdrawal::create([
                    'user_id' => $user->id,
                    'payment_method' => 'paypal',
                    'payout_account' => $user->paypal_email,
                    'withdraw_no' => 'WD'.now()->timestamp.rand(100, 999),
                    'coin_amount' => $request->coin_amount,
                    'usd_amount' => $request->coin_amount,
                    'status' => WithdrawalStatus::PENDING,
                ]);

                if ($setting?->value === 'true') {
                    $batchId = $this->paypal->sendPayout(
                        $user->paypal_email,
                        (float) $withdraw->usd_amount,
                        $withdraw->withdraw_no,
                    );

                    $withdraw->update([
                        'status' => WithdrawalStatus::ACCEPTED,
                        'paypal_payout_id' => $batchId,
                    ]);

                    $balance->increment('total_withdraw', $withdraw->coin_amount);

                    $user->coinTransactions()->create([
                        'type' => TransactionType::WITHDRAW,
                        'amount' => $withdraw->coin_amount,
                        'balance_after' => $balance->total_balance,
                        'reference' => $batchId,
                    ]);
                }

                $super_admin = User::role('super_admin')->first();
                Notification::send($super_admin, new AdminWithdrawalNotification($withdraw, $user));
            });

            return $this->sendResponse($withdraw);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
