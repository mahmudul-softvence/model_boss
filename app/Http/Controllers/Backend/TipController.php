<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserBalance;
use App\Notifications\CoinReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipController extends Controller
{
    public function sendTip(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'tip_amount' => 'required|numeric|min:1',
        ]);

        $senderId = auth('api')->id();
        $receiverId = $request->receiver_id;
        $amount = $request->tip_amount;

        if ($senderId == $receiverId) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot tip yourself.',
            ], 422);
        }

        try {

            DB::transaction(function () use ($senderId, $receiverId, $amount) {

                $senderBalance = UserBalance::where('user_id', $senderId)
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $senderId]);

                if ($senderBalance->total_balance < $amount) {
                    throw new \RuntimeException('Insufficient balance.');
                }

                // deduct sender
                $senderBalance->total_balance -= $amount;
                $senderBalance->save();

                CoinTransaction::create([
                    'user_id' => $senderId,
                    'type' => 'tip',
                    'amount' => -$amount,
                    'balance_after' => $senderBalance->total_balance,
                    'reference' => 'Tip sent to user #'.$receiverId,
                ]);

                if ($receiverId != 1) {
                    $this->creditTipShare($receiverId, $amount * 0.5, 'Tip received from user #'.$senderId);
                    $this->creditTipShare(1, $amount * 0.5, 'Admin share from tip sent by user #'.$senderId);
                } else {
                    $this->creditTipShare(1, $amount, 'Tip received from user #'.$senderId);
                }

                Tip::create([
                    'send_user_id' => $senderId,
                    'received_user_id' => $receiverId,
                    'tip_amount' => $amount,
                ]);
            });

            return response()->json([
                'status' => true,
                'message' => 'Tip sent successfully.',
            ]);
        } catch (\RuntimeException $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    public function sendCoin(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $senderId = auth('api')->id();
        $receiverId = $request->receiver_id;
        $amount = $request->amount;

        if ($senderId == $receiverId) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot send coins to yourself.',
            ], 422);
        }

        $fee = $this->calculateFee($amount);
        $receiverAmount = $amount - $fee;

        if ($receiverAmount <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Amount is too small after fee deduction.',
            ], 422);
        }

        try {

            DB::transaction(function () use ($senderId, $receiverId, $amount, $fee, $receiverAmount) {

                $senderBalance = UserBalance::where('user_id', $senderId)
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $senderId]);

                if ($senderBalance->total_balance < $amount) {
                    throw new \RuntimeException('Insufficient balance.');
                }

                $senderBalance->total_balance -= $amount;
                $senderBalance->save();

                CoinTransaction::create([
                    'user_id' => $senderId,
                    'type' => 'send-coin',
                    'amount' => -$amount,
                    'balance_after' => $senderBalance->total_balance,
                    'reference' => 'Sent coins to user #'.$receiverId,
                ]);

                $receiverBalance = UserBalance::where('user_id', $receiverId)
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $receiverId]);

                $receiverBalance->total_balance += $receiverAmount;
                $receiverBalance->save();

                CoinTransaction::create([
                    'user_id' => $receiverId,
                    'type' => 'receive-coin',
                    'amount' => $receiverAmount,
                    'balance_after' => $receiverBalance->total_balance,
                    'reference' => 'Received coins (after fee) from user #'.$senderId,
                ]);

                $adminBalance = UserBalance::where('user_id', 1)
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => 1]);

                $adminBalance->total_balance += $fee;
                $adminBalance->save();

                CoinTransaction::create([
                    'user_id' => 1,
                    'type' => 'Send-coin fee',
                    'amount' => $fee,
                    'balance_after' => $adminBalance->total_balance,
                    'reference' => 'Fee from send coin by user #'.$senderId,
                ]);
            });

            $sender = User::find($senderId);
            $receiver = User::find($receiverId);

            if ($sender && $receiver) {
                $receiver->notify(new CoinReceivedNotification($sender, $receiverAmount));
            }

            return response()->json([
                'status' => true,
                'message' => 'Coins sent successfully.',
            ]);
        } catch (\RuntimeException $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    /**
     * Lock the recipient's balance, credit the tip share, and record the transaction.
     * Must be called inside a DB transaction.
     */
    private function creditTipShare(int $userId, float $share, string $reference): void
    {
        $balance = UserBalance::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrCreate(['user_id' => $userId]);

        $balance->total_balance += $share;
        $balance->total_tip_received += $share;
        $balance->save();

        CoinTransaction::create([
            'user_id' => $userId,
            'type' => 'tip',
            'amount' => $share,
            'balance_after' => $balance->total_balance,
            'reference' => $reference,
        ]);
    }

    private function calculateFee($amount)
    {
        if ($amount >= 0 && $amount <= 50) {
            return 5;
        } elseif ($amount <= 500) {
            return 10;
        } elseif ($amount <= 1000) {
            return 15;
        } elseif ($amount <= 5000) {
            return 30;
        } elseif ($amount <= 20000) {
            return 100;
        } elseif ($amount <= 100000) {
            return 300;
        } else {
            return 500;
        }
    }

    public function userList(Request $request)
    {
        $search = $request->query('search');

        $users = User::query()
            ->where('id', '!=', 1)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('artist_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->select('id', 'name', 'artist_name', 'email')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->artist_name ?: $user->name,
                'email' => $user->email,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ]);
    }
}
