<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Account;
use Stripe\Stripe;
use Stripe\Transfer;

class WithdrawController extends Controller
{
    public function index()
    {
        $withdraw_req = Withdrawal::all();
        return $this->sendResponse($withdraw_req);
    }


    public function accept($id)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {

            DB::transaction(function () use ($id) {

                $withdraw = Withdrawal::lockForUpdate()
                    ->findOrFail($id);

                if ($withdraw->status !== WithdrawalStatus::PENDING) {
                    throw new \Exception('Already processed.');
                }

                $user = $withdraw->user;

                if (!$user->stripe_account_id) {
                    throw new \Exception('User Stripe not connected.');
                }

                $account = Account::retrieve(
                    $user->stripe_account_id
                );

                if (!$account->payouts_enabled) {
                    throw new \Exception('Stripe account not ready.');
                }

                $transfer = Transfer::create([
                    'amount' => $withdraw->usd_amount * 100,
                    'currency' => 'usd',
                    'destination' => $user->stripe_account_id,
                    'description' => 'Withdrawal ' . $withdraw->withdraw_no,
                ]);

                $withdraw->update([
                    'status' => WithdrawalStatus::ACCEPTED
                ]);

                $currentBalance = $user->userBalance()
                    ->lockForUpdate()
                    ->first()
                    ->total_balance;

                $user->coinTransactions()->create([
                    'type' => TransactionType::WITHDRAW,
                    'amount' => $withdraw->coin_amount,
                    'balance_after' => $currentBalance,
                    'reference' => $transfer->id
                ]);
            });

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

                if ($withdraw->status !== WithdrawalStatus::PENDING) {
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
                    'status' => WithdrawalStatus::DECLINED
                ]);
            });

            return $this->sendResponse([], 'Withdrawal declined and refunded.');
        } catch (\Exception $e) {

            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
