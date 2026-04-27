<?php

namespace App\Http\Controllers\Withdraw\Moncash;

use App\Http\Controllers\Controller;
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

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected' => (bool) $user->moncash_phone,
            'moncash_phone' => $user->moncash_phone,
        ]);
    }
}
