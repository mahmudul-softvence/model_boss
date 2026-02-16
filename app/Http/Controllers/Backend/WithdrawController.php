<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\User;
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

                $withdraw = Withdrawal::with('user')
                    ->lockForUpdate()
                    ->findOrFail($id);


                if ($withdraw->status !== WithdrawalStatus::PENDING->value) {
                    throw new \Exception('Already processed.');
                }

                $user = $withdraw->user;

                if (!$user->stripe_account_id) {
                    throw new \Exception('User Stripe not connected.');
                }

                $userBalance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (!$userBalance) {
                    throw new \Exception('User balance not found.');
                }

                // $superAdmin = User::role('super_admin')->first();

                // if (!$superAdmin) {
                //     throw new \Exception('Super Admin not found.');
                // }

                // $superBalance = $superAdmin->userBalance()
                //     ->lockForUpdate()
                //     ->first();

                // if (!$superBalance) {
                //     throw new \Exception('Super Admin balance not found.');
                // }

                // if ($superBalance->total_balance < $withdraw->coin_amount) {
                //     throw new \Exception('Super Admin balance insufficient.');
                // }

                $account = Account::retrieve($user->stripe_account_id);

                if (!$account->payouts_enabled) {
                    throw new \Exception('Stripe account not ready.');
                }

                $transfer = Transfer::create([
                    'amount' => (int) ($withdraw->usd_amount * 100),
                    'currency' => 'usd',
                    'destination' => $user->stripe_account_id,
                    'description' => 'Withdrawal ' . $withdraw->withdraw_no,
                ]);

                $withdraw->update([
                    'status' => WithdrawalStatus::ACCEPTED,
                    'stripe_transfer_id' =>  $transfer->id
                ]);

                $userBalance->increment('total_withdraw', $withdraw->coin_amount);

                // $superBalance->decrement('total_balance', $withdraw->coin_amount);

                $user->coinTransactions()->create([
                    'type' => TransactionType::WITHDRAW,
                    'amount' => $withdraw->coin_amount,
                    'balance_after' => $userBalance->total_balance,
                    'reference' => $transfer->id,
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
                    'status' => WithdrawalStatus::DECLINED
                ]);
            });

            return $this->sendResponse([], 'Withdrawal declined and refunded.');
        } catch (\Exception $e) {

            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
