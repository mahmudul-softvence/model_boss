<?php

use App\Http\Controllers\Moncash\CallbackController;
use App\Http\Controllers\Stripe\WebhookController;
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
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
