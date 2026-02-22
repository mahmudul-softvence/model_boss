<?php

use App\Http\Controllers\Frontend\FollowController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PostController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\TwitchController;
use App\Http\Controllers\Stripe\CheckoutController;
use App\Http\Controllers\Stripe\StripeConnectController;
use App\Http\Controllers\Stripe\StripeWithdrawController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:api'])->group(function () {
    // Post
    Route::apiResource('posts', PostController::class);

    //Follow/Unfollow
    Route::post('follow/{id}', [FollowController::class, 'follow']);
    Route::delete('unfollow/{id}', [FollowController::class, 'unfollow']);

    //Profile
    Route::put('profile/update', [ProfileController::class, 'update']);

    // Twitch live
    Route::get('twitch/check_live', [TwitchController::class, 'status']);

    // Payment Releted
    Route::post('checkout', [CheckoutController::class, 'checkout']);
    Route::post('connect_account', [CheckoutController::class, 'connect_account']);
    Route::post('stripe/connect', [StripeConnectController::class, 'connect']);
    Route::get('stripe/status', [StripeConnectController::class, 'status']);
    Route::post('withdraw/request', [StripeWithdrawController::class, 'request']);
});


Route::middleware(['api'])->group(function () {
    Route::get('get_featured_news', [HomeController::class, 'get_featured_news']);
});
