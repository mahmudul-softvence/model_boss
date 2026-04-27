<?php

namespace App\Http\Controllers\Moncash;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AdminWithdrawalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MoncashWithdrawController extends Controller
{
    public function request(Request $request)
    {
        $request->validate([
            'coin_amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();

        if (! $user->moncash_phone) {
            return $this->sendError('MonCash account not connected.', [], 400);
        }

        if ($request->coin_amount < 10) {
            return $this->sendError('Minimum 10 points required.', [], 400);
        }

        try {
            DB::transaction(function () use ($request, $user, &$withdraw) {
                $balance = $user->userBalance()
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->total_balance < $request->coin_amount) {
                    throw new \Exception('Insufficient balance.');
                }

                $balance->decrement('total_balance', $request->coin_amount);

                $withdraw = Withdrawal::create([
                    'user_id' => $user->id,
                    'payment_method' => 'moncash',
                    'payout_account' => $user->moncash_phone,
                    'withdraw_no' => 'WD'.now()->timestamp.rand(100, 999),
                    'coin_amount' => $request->coin_amount,
                    'usd_amount' => $request->coin_amount,
                    'status' => WithdrawalStatus::PENDING,
                ]);

                $super_admin = User::role('super_admin')->first();
                Notification::send($super_admin, new AdminWithdrawalNotification($withdraw, $user));
            });

            return $this->sendResponse($withdraw);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
