<?php

use App\Http\Controllers\Stripe\CheckoutController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:api'])->group(function () {
    Route::post('checkout', [CheckoutController::class, 'checkout']);
});
