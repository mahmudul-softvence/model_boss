<?php

namespace App\Http\Controllers\Withdraw\Bitpay;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class BitpayConnectController extends Controller
{
    public function connect(Request $request)
    {
        $request->validate([
            'bitpay_wallet' => 'required|string|min:10',
        ]);

        $user = $request->user();
        $user->bitpay_wallet = $request->bitpay_wallet;
        $user->save();

        return $this->sendResponse(['connected' => true]);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        $hasPending = Withdrawal::where('user_id', $user->id)
            ->where('payment_method', 'bitpay')
            ->where('status', WithdrawalStatus::PENDING->value)
            ->exists();

        if ($hasPending) {
            return $this->sendError('Cannot disconnect while a withdrawal is pending.', 422);
        }

        $user->bitpay_wallet = null;
        $user->save();

        return $this->sendResponse(['connected' => false]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->bitpay_wallet,
            'bitpay_wallet' => $user->bitpay_wallet,
        ]);
    }
}
