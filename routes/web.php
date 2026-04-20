<?php

use App\Http\Controllers\Bitpay\WebhookController as BitpayWebhookController;
use App\Http\Controllers\Moncash\CallbackController;
use App\Http\Controllers\Paypal\CallbackController as PaypalCallbackController;
use App\Http\Controllers\Stripe\WebhookController as StripeWebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('clear', function () {
    Artisan::call('optimize:clear');

    return 'Cache is cleared';
});

Route::match(['GET', 'POST'], 'moncash/callback', [CallbackController::class, 'handle'])
    ->name('moncash.callback');
Route::match(['GET', 'POST'], 'moncash/alert', [CallbackController::class, 'handle'])
    ->name('moncash.alert');
Route::get('paypal/return', [PaypalCallbackController::class, 'handleReturn'])
    ->name('paypal.return');
Route::get('paypal/cancel', [PaypalCallbackController::class, 'handleCancel'])
    ->name('paypal.cancel');
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
Route::post('bitpay/webhook', [BitpayWebhookController::class, 'handleWebhook'])
    ->name('bitpay.webhook');
