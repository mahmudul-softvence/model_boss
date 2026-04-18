<?php

namespace App\Http\Controllers\Stripe;

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
use Stripe\Account;
use Stripe\Stripe;
use Stripe\Transfer;

class StripeWithdrawController extends Controller
{
    public function request(Request $request)
    {

        Stripe::setApiKey(config('services.stripe.secret'));

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

        try {

            DB::transaction(function () use ($request, $user, &$withdraw) {

                $setting = Setting::where('key', 'auto_accept_withdrawals')->first();

                if ($setting->value === 'true') {
                    if (! $user->stripe_account_id) {
                        throw new \Exception('User Stripe not connected.');
                    }

                    $userBalance = $user->userBalance()
                        ->lockForUpdate()
                        ->first();

                    if (! $userBalance || $userBalance->total_balance < $request->coin_amount) {
                        throw new \Exception('Insufficient balance.');
                    }

                    if (! $userBalance) {
                        throw new \Exception('User balance not found.');
                    }

                    $account = Account::retrieve($user->stripe_account_id);

                    if (! $account->payouts_enabled) {
                        throw new \Exception('Stripe account not ready.');
                    }

                    $withdraw = Withdrawal::create([
                        'user_id' => $user->id,
                        'withdraw_no' => 'WD'.now()->timestamp.rand(100, 999),
                        'coin_amount' => $request->coin_amount,
                        'usd_amount' => $request->coin_amount,
                        'status' => WithdrawalStatus::PENDING,
                    ]);

                    $transfer = Transfer::create([
                        'amount' => (int) ($withdraw->usd_amount * 100),
                        'currency' => 'usd',
                        'destination' => $user->stripe_account_id,
                        'description' => 'Withdrawal '.$withdraw->withdraw_no,
                    ]);

                    $withdraw->update([
                        'status' => WithdrawalStatus::ACCEPTED,
                        'stripe_transfer_id' => $transfer->id,
                    ]);

                    $userBalance->decrement('total_balance', $request->coin_amount);
                    $userBalance->increment('total_withdraw', $withdraw->coin_amount);

                    $user->coinTransactions()->create([
                        'type' => TransactionType::WITHDRAW,
                        'amount' => $withdraw->coin_amount,
                        'balance_after' => $userBalance->total_balance,
                        'reference' => $transfer->id,
                    ]);

                    $super_admin = User::role('super_admin')->first();
                    Notification::send($super_admin, new AdminWithdrawalNotification($withdraw, $user));
                } else {
                    $balance = $user->userBalance()
                        ->lockForUpdate()
                        ->first();

                    if (! $balance || $balance->total_balance < $request->coin_amount) {
                        throw new \Exception('Insufficient balance.');
                    }

                    $balance->decrement('total_balance', $request->coin_amount);

                    $usd = $request->coin_amount;

                    $withdraw = Withdrawal::create([
                        'user_id' => $user->id,
                        'withdraw_no' => 'WD'.now()->timestamp.rand(100, 999),
                        'coin_amount' => $request->coin_amount,
                        'usd_amount' => $usd,
                        'status' => WithdrawalStatus::PENDING,
                    ]);

                    $super_admin = User::role('super_admin')->first();
                    Notification::send($super_admin, new AdminWithdrawalNotification($withdraw, $user));
                }
            });

            return $this->sendResponse($withdraw);
        } catch (\Exception $e) {

            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
