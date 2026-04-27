<?php

use App\Http\Controllers\Bitpay\BitpayConnectController;
use App\Http\Controllers\Bitpay\BitpayWithdrawController;
use App\Http\Controllers\Frontend\FollowController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PostController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\TwitchController;
use App\Http\Controllers\Moncash\MoncashConnectController;
use App\Http\Controllers\Moncash\MoncashWithdrawController;
use App\Http\Controllers\Paypal\PaypalConnectController;
use App\Http\Controllers\Paypal\PaypalWithdrawController;
use App\Http\Controllers\Stripe\CheckoutController;
use App\Http\Controllers\Stripe\StripeConnectController;
use App\Http\Controllers\Stripe\StripeWithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function () {
    // Post
    Route::apiResource('posts', PostController::class);

    // Follow/Unfollow
    Route::post('follow/{id}', [FollowController::class, 'follow']);
    Route::delete('unfollow/{id}', [FollowController::class, 'unfollow']);

    // Profile
    Route::post('profile/update', [ProfileController::class, 'update']);

    // Twitch live
    Route::get('twitch/check_live', [TwitchController::class, 'status']);

    // Payment
    Route::post('checkout', [CheckoutController::class, 'checkout']);
    Route::post('connect_account', [CheckoutController::class, 'connect_account']);

    // Stripe payout account
    Route::post('stripe/connect', [StripeConnectController::class, 'connect']);
    Route::get('stripe/status', [StripeConnectController::class, 'status']);
    Route::post('stripe/withdraw', [StripeWithdrawController::class, 'request']);

    // PayPal payout account
    Route::post('paypal/connect', [PaypalConnectController::class, 'connect']);
    Route::get('paypal/status', [PaypalConnectController::class, 'status']);
    Route::post('paypal/withdraw', [PaypalWithdrawController::class, 'request']);

    // BitPay payout account
    Route::post('bitpay/connect', [BitpayConnectController::class, 'connect']);
    Route::get('bitpay/status', [BitpayConnectController::class, 'status']);
    Route::post('bitpay/withdraw', [BitpayWithdrawController::class, 'request']);

    // MonCash payout account
    Route::post('moncash/connect', [MoncashConnectController::class, 'connect']);
    Route::get('moncash/status', [MoncashConnectController::class, 'status']);
    Route::post('moncash/withdraw', [MoncashWithdrawController::class, 'request']);

    // Legacy withdraw route (Stripe)
    Route::post('withdraw/request', [StripeWithdrawController::class, 'request']);

    Route::get('show_artist_prifile/{id}', [ProfileController::class, 'show_artist_prifile']);
    Route::get('show_artist_posts/{id}', [ProfileController::class, 'show_artist_posts']);

    Route::get('see_follower', [ProfileController::class, 'see_follower']);
    Route::get('see_following', [ProfileController::class, 'see_following']);
});

Route::middleware(['api'])->group(function () {
    Route::get('get_featured_news', [HomeController::class, 'get_featured_news']);
    Route::get('get_featured_gallery', [HomeController::class, 'get_featured_gallery']);
    Route::get('get_live_staus', [HomeController::class, 'get_live_staus']);
    Route::get('search_artist', [HomeController::class, 'search_artist']);
    Route::get('get_all_games', [HomeController::class, 'get_all_games']);
});
