<?php

use App\Http\Controllers\Payment\Bitpay\WebhookController as BitpayWebhookController;
use App\Http\Controllers\Payment\Moncash\CallbackController as MoncashCallbackController;
use App\Http\Controllers\Payment\Paypal\CallbackController as PaypalCallbackController;
use App\Http\Controllers\Payment\Stripe\WebhookController as StripeWebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('clear', function () {
    Artisan::call('optimize:clear');

    return 'Cache is cleared';
});

Route::match(['GET', 'POST'], 'moncash/callback', [MoncashCallbackController::class, 'handle'])
    ->name('moncash.callback');
Route::match(['GET', 'POST'], 'moncash/alert', [MoncashCallbackController::class, 'handle'])
    ->name('moncash.alert');
Route::get('paypal/return', [PaypalCallbackController::class, 'handleReturn'])
    ->name('paypal.return');
Route::get('paypal/cancel', [PaypalCallbackController::class, 'handleCancel'])
    ->name('paypal.cancel');
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
Route::post('bitpay/webhook', [BitpayWebhookController::class, 'handleWebhook'])
    ->name('bitpay.webhook');
