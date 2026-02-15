<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\GameController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Backend\MatchController;


Route::group(['middleware' => 'api'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('admin_login', [AuthController::class, 'admin_login']);
    Route::post('resend_verification', [AuthController::class, 'resend_verification']);
    Route::get('verify_email/{id}/{hash}', [AuthController::class, 'verify_email'])
        ->middleware('signed')->name('verification.verify');

    Route::get('{provider}/redirect', [SocialController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialController::class, 'callback']);

    Route::post('forgot_password', [ForgotPasswordController::class, 'forgot_password']);
    Route::post('verify_forgot_password', [ForgotPasswordController::class, 'verify_forgot_password']);
    Route::post('reset_password', [ForgotPasswordController::class, 'reset_password']);

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
});

Route::group(['middleware' => ['auth:api', 'role:super_admin']], function () {
    //Category
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'edit']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    //Game
    Route::get('games', [GameController::class, 'index']);
    Route::post('games', [GameController::class, 'store']);
    Route::get('games/{id}', [GameController::class, 'edit']);
    Route::put('games/{id}', [GameController::class, 'update']);
    Route::delete('games/{id}', [GameController::class, 'destroy']);


    //Match
    Route::get('matches', [MatchController::class, 'index']);
    Route::post('matches', [MatchController::class, 'store']);
    Route::get('matches/{id}', [MatchController::class, 'edit']);
    Route::put('matches/{id}', [MatchController::class, 'update']);
    Route::delete('matches/{id}', [MatchController::class, 'destroy']);

    Route::get('match-players/{id}', [MatchController::class, 'players']);


});

require __DIR__ . '/backend.php';
require __DIR__ . '/frontend.php';
