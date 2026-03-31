<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserBalance;
use App\Models\CoinTransaction;
use App\Models\Tip;
use Illuminate\Support\Facades\DB;

class TipController extends Controller
{
    public function sendTip(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'tip_amount'       => 'required|numeric|min:1',
        ]);

        $senderId   = auth('api')->id();
        $receiverId = $request->receiver_id;
        $amount     = $request->tip_amount;

        if ($senderId == $receiverId) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot tip yourself.'
            ], 422);
        }

        try {

            DB::transaction(function () use ($senderId, $receiverId, $amount) {

                $senderBalance = UserBalance::where('user_id', $senderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($senderBalance->total_balance < $amount) {
                    throw new \RuntimeException('Insufficient balance.');
                }

                // deduct sender
                $senderBalance->total_balance -= $amount;
                $senderBalance->save();

                CoinTransaction::create([
                    'user_id'       => $senderId,
                    'type'          => 'tip',
                    'amount'        => -$amount,
                    'balance_after' => $senderBalance->total_balance,
                    'reference'     => 'Tip sent to user #' . $receiverId,
                ]);

                if ($receiverId != 1) {

                    $receiverShare = $amount * 0.5;
                    $adminShare    = $amount * 0.5;

                    $receiverBalance = UserBalance::where('user_id', $receiverId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $adminBalance = UserBalance::where('user_id', 1)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $receiverBalance->total_balance += $receiverShare;
                    $receiverBalance->total_tip_received += $receiverShare;
                    $receiverBalance->save();

                    CoinTransaction::create([
                        'user_id'       => $receiverId,
                        'type'          => 'tip',
                        'amount'        => $receiverShare,
                        'balance_after' => $receiverBalance->total_balance,
                        'reference'     => 'Tip received from user #' . $senderId,
                    ]);

                    $adminBalance->total_balance += $adminShare;
                    $adminBalance->total_tip_received += $adminShare;
                    $adminBalance->save();
                    CoinTransaction::create([
                        'user_id'       => 1,
                        'type'          => 'tip',
                        'amount'        => $adminShare,
                        'balance_after' => $adminBalance->total_balance,
                        'reference'     => 'Admin share from tip sent by user #' . $senderId,
                    ]);

                } else {

                    $adminBalance = UserBalance::where('user_id', 1)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $adminBalance->total_balance += $amount;
                    $adminBalance->total_tip_received += $amount;
                    $adminBalance->save();
                    CoinTransaction::create([
                        'user_id'       => 1,
                        'type'          => 'tip',
                        'amount'        => $amount,
                        'balance_after' => $adminBalance->total_balance,
                        'reference'     => 'Tip received from user #' . $senderId,
                    ]);
                }

                Tip::create([
                    'send_user_id'     => $senderId,
                    'received_user_id' => $receiverId,
                    'tip_amount'       => $amount,
                ]);
            });

            return response()->json([
                'status'  => true,
                'message' => 'Tip sent successfully.'
            ]);

        } catch (\RuntimeException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }

    public function sendCoin(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'amount'      => 'required|numeric|min:1',
        ]);

        $senderId   = auth('api')->id();
        $receiverId = $request->receiver_id;
        $amount     = $request->amount;

        if ($senderId == $receiverId) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot send coins to yourself.'
            ], 422);
        }

        try {

            DB::transaction(function () use ($senderId, $receiverId, $amount) {

                $senderBalance = UserBalance::where('user_id', $senderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($senderBalance->total_balance < $amount) {
                    throw new \RuntimeException('Insufficient balance.');
                }

                // deduct sender
                $senderBalance->total_balance -= $amount;
                $senderBalance->save();

                CoinTransaction::create([
                    'user_id'       => $senderId,
                    'type'          => 'send-coin',
                    'amount'        => -$amount,
                    'balance_after' => $senderBalance->total_balance,
                    'reference'     => 'Sent coins to user #' . $receiverId,
                ]);

                // credit receiver
                $receiverBalance = UserBalance::where('user_id', $receiverId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $receiverBalance->total_balance += $amount;
                $receiverBalance->save();

                CoinTransaction::create([
                    'user_id'       => $receiverId,
                    'type'          => 'receive_coin',
                    'amount'        => $amount,
                    'balance_after' => $receiverBalance->total_balance,
                    'reference'     => 'Received coins from user #' . $senderId,
                ]);
            });

            return response()->json([
                'status'  => true,
                'message' => 'Coins sent successfully.'
            ]);

        } catch (\RuntimeException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }



}
