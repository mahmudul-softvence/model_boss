<?php

namespace App\Http\Controllers\Withdraw\Moncash;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class MoncashConnectController extends Controller
{
    public function connect(Request $request)
    {
        $request->validate([
            'moncash_phone' => 'required|string|min:8',
        ]);

        $user = $request->user();
        $user->moncash_phone = $request->moncash_phone;
        $user->save();

        return $this->sendResponse(['connected' => true]);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        $hasPending = Withdrawal::where('user_id', $user->id)
            ->where('payment_method', 'moncash')
            ->where('status', WithdrawalStatus::PENDING->value)
            ->exists();

        if ($hasPending) {
            return $this->sendError('Cannot disconnect while a withdrawal is pending.', 422);
        }

        $user->moncash_phone = null;
        $user->save();

        return $this->sendResponse(['connected' => false]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->moncash_phone,
            'moncash_phone' => $user->moncash_phone,
        ]);
    }
}
