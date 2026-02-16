<?php

use App\Http\Controllers\Stripe\CheckoutController;
use App\Http\Controllers\Stripe\StripeConnectController;
use App\Http\Controllers\Stripe\StripeWithdrawController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:api'])->group(function () {
    Route::post('checkout', [CheckoutController::class, 'checkout']);
    Route::post('connect_account', [CheckoutController::class, 'connect_account']);
    Route::post('stripe/connect', [StripeConnectController::class, 'connect']);
    Route::get('stripe/status', [StripeConnectController::class, 'status']);
    Route::post('withdraw/request', [StripeWithdrawController::class, 'request']);
});
