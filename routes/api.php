<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\GameController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Backend\MatchController;
use App\Http\Controllers\Backend\SupportController;
use App\Http\Controllers\Backend\WinnerController;

Route::group(['middleware' => 'api'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::post('resend_verification', [AuthController::class, 'resend_verification']);
    Route::get('verify_email/{id}/{hash}', [AuthController::class, 'verify_email'])
        ->middleware('signed')->name('verification.verify');

    Route::get('{provider}/redirect', [SocialController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialController::class, 'callback']);

    Route::post('forgot_password', [ForgotPasswordController::class, 'forgot_password']);
    Route::post('verify_forgot_password', [ForgotPasswordController::class, 'verify_forgot_password']);
    Route::post('reset_password', [ForgotPasswordController::class, 'reset_password']);

    Route::get('categories', [CategoryController::class, 'landing']);
    Route::get('games', [GameController::class, 'landing']);
    Route::get('matches', [MatchController::class, 'landing']);
    Route::get('bigboss-supporter', [SupportController::class, 'bigBossSupporter']);

});

Route::middleware(['auth:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::post('/support', [SupportController::class, 'store']);
    Route::post('send-tip', [SupportController::class, 'sendTip']);
    Route::get('user-transactions', [WinnerController::class, 'userTransactions']);
    Route::get('past-supports', [SupportController::class, 'pastSupport']);
    Route::get('referral-link-used', [SupportController::class, 'referralLinkUsed']);
    Route::get('support-history', [SupportController::class, 'supportHistory']);
});

Route::group(['middleware' => ['auth:api', 'role:super_admin'], 'prefix' => 'admin'], function () {
    //Category
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'edit']);
    Route::post('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    //Game
    Route::get('games', [GameController::class, 'index']);
    Route::post('games', [GameController::class, 'store']);
    Route::get('games/{id}', [GameController::class, 'edit']);
    Route::post('games/{id}', [GameController::class, 'update']);
    Route::delete('games/{id}', [GameController::class, 'destroy']);
    Route::get('all-games', [GameController::class, 'allGames']);

    //Match
    Route::get('matches', [MatchController::class, 'index']);
    Route::post('matches', [MatchController::class, 'store']);
    Route::get('matches/{id}', [MatchController::class, 'edit']);
    Route::post('matches/{id}', [MatchController::class, 'update']);
    Route::delete('matches/{id}', [MatchController::class, 'destroy']);
    //match confirmation
    Route::post('match-confirm/{id}', [SupportController::class, 'confirm']);

    Route::get('match-players/{id}', [MatchController::class, 'players']);

    Route::get('all-players', [MatchController::class, 'allPlayers']);
    Route::post('match-winner/{id}', [WinnerController::class, 'winner']);

});

require __DIR__ . '/backend.php';
require __DIR__ . '/frontend.php';
