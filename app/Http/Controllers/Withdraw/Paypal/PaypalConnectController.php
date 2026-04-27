<?php

namespace App\Http\Controllers\Withdraw\Paypal;

use App\Http\Controllers\Controller;
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

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->paypal_email,
            'paypal_email' => $user->paypal_email,
        ]);
    }
}
