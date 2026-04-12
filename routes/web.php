<?php

use App\Http\Controllers\Moncash\CallbackController;
use App\Http\Controllers\Netcash\CallbackController as NetcashCallbackController;
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
Route::post('netcash/notify', [NetcashCallbackController::class, 'notify'])
    ->name('netcash.notify');
Route::match(['GET', 'POST'], 'netcash/accept', [NetcashCallbackController::class, 'accept'])
    ->name('netcash.accept');
Route::match(['GET', 'POST'], 'netcash/decline', [NetcashCallbackController::class, 'decline'])
    ->name('netcash.decline');
Route::match(['GET', 'POST'], 'netcash/redirect', [NetcashCallbackController::class, 'redirect'])
    ->name('netcash.redirect');
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
