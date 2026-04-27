<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use App\Notifications\UserWithdrawalDeclinedNotification;
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
            DB::transaction(function () use ($id) {
                $withdraw = Withdrawal::with('user')
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($withdraw->status !== WithdrawalStatus::PENDING->value) {
                    throw new \Exception('Already processed.');
                }

                $user = $withdraw->user;

                $userBalance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (! $userBalance) {
                    throw new \Exception('User balance not found.');
                }

                if ($withdraw->payment_method === 'stripe') {
                    Stripe::setApiKey(config('services.stripe.secret'));

                    if (! $user->stripe_account_id) {
                        throw new \Exception('User Stripe not connected.');
                    }

                    $account = Account::retrieve($user->stripe_account_id);

                    if (! $account->payouts_enabled) {
                        throw new \Exception('Stripe account not ready.');
                    }

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

                    $user->coinTransactions()->create([
                        'type' => TransactionType::WITHDRAW,
                        'amount' => $withdraw->coin_amount,
                        'balance_after' => $userBalance->total_balance,
                        'reference' => $transfer->id,
                    ]);
                } else {
                    $withdraw->update([
                        'status' => WithdrawalStatus::ACCEPTED,
                    ]);

                    $user->coinTransactions()->create([
                        'type' => TransactionType::WITHDRAW,
                        'amount' => $withdraw->coin_amount,
                        'balance_after' => $userBalance->total_balance,
                        'reference' => $withdraw->withdraw_no,
                    ]);
                }

                $userBalance->increment('total_withdraw', $withdraw->coin_amount);
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
                    'status' => WithdrawalStatus::DECLINED,
                ]);

                $withdraw->user->notify(new UserWithdrawalDeclinedNotification($withdraw));
            });

            return $this->sendResponse([], 'Withdrawal declined and refunded.');
        } catch (\Exception $e) {

            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
