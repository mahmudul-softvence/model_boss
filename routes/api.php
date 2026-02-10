<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('admin_login', [AuthController::class, 'admin_login']);
    Route::post('resend_verification', [AuthController::class, 'resend_verification']);
    Route::get('verify_email/{id}/{hash}', [AuthController::class, 'verify_email'])->middleware('signed')->name('verification.verify');

    Route::get('{provider}/redirect', [SocialController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialController::class, 'callback']);

    Route::post('forgot_password', [ForgotPasswordController::class, 'forgot_password']);
    Route::post('verify_forgot_password', [ForgotPasswordController::class, 'verify_forgot_password']);
    Route::post('reset_password', [ForgotPasswordController::class, 'reset_password']);

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
});


