<?php

use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Stripe\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('clear', function () {
    Artisan::call('optimize:clear');
    return "Cache is cleared";
});

Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
