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


Route::get('storage-proxy/{folder}/{file}', function ($folder, $file) {
    $path = storage_path("app/public/$folder/$file");

    if (!file_exists($path)) {
        abort(404);
    }

    $response = response()->file($path);
    $response->headers->set('Access-Control-Allow-Origin', '*');

    return $response;
});
