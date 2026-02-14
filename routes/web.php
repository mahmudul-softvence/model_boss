<?php

use App\Http\Controllers\Stripe\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
