<?php

namespace App\Http\Controllers\Withdraw\Paypal;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class PaypalConnectController extends Controller
{
    public function connect(Request $request)
    {
        $request->validate([
            'paypal_email' => 'required|email',
        ]);

        $user = $request->user();
        $user->paypal_email = $request->paypal_email;
        $user->save();

        return $this->sendResponse(['connected' => true]);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        $hasPending = Withdrawal::where('user_id', $user->id)
            ->where('payment_method', 'paypal')
            ->where('status', WithdrawalStatus::PENDING->value)
            ->exists();

        if ($hasPending) {
            return $this->sendError('Cannot disconnect while a withdrawal is pending.', 422);
        }

        $user->paypal_email = null;
        $user->save();

        return $this->sendResponse(['connected' => false]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->paypal_email,
            'paypal_email' => $user->paypal_email,
        ]);
    }
}
