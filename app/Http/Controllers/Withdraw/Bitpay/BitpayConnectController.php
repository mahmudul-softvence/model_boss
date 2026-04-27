<?php

namespace App\Http\Controllers\Withdraw\Bitpay;

use App\Http\Controllers\Controller;
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

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->bitpay_wallet,
            'bitpay_wallet' => $user->bitpay_wallet,
        ]);
    }
}
