<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Backend\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\GameController;
use App\Http\Controllers\Backend\MatchController;
use App\Http\Controllers\Backend\MatchForVotingController;
use App\Http\Controllers\Backend\SupportController;
use App\Http\Controllers\Backend\TipController;
use App\Http\Controllers\Backend\WinnerController;
use App\Http\Controllers\Frontend\ChallengeController;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('login');

Route::group(['middleware' => 'api'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-login-otp', [AuthController::class, 'verifyLoginOtp']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::post('resend_verification', [AuthController::class, 'resend_verification']);
    Route::get('verify_email/{id}/{hash}', [AuthController::class, 'verify_email'])
        ->middleware('signed')->name('verification.verify');

    Route::get('{provider}/redirect', [SocialController::class, 'redirect']);
    Route::match(['GET', 'POST'], '{provider}/callback', [SocialController::class, 'callback']);

    Route::post('forgot_password', [ForgotPasswordController::class, 'forgot_password']);
    Route::post('verify_forgot_password', [ForgotPasswordController::class, 'verify_forgot_password']);
    Route::post('reset_password', [ForgotPasswordController::class, 'reset_password']);

    Route::get('categories', [CategoryController::class, 'landing']);
    Route::get('games', [GameController::class, 'landing']);
    Route::get('matches', [MatchController::class, 'landing']);
    Route::get('match/{id}', [MatchController::class, 'socketMatch']);
    Route::get('bigboss-supporter', [SupportController::class, 'bigBossSupporter']);

    Route::get('match-for-voting', [MatchForVotingController::class, 'todaysMatches']);

    // Big Boss Challenge (public)
    Route::get('challenges', [ChallengeController::class, 'index']);
    Route::get('challenges/{id}', [ChallengeController::class, 'show'])->whereNumber('id');
    Route::get('users/{id}/challenges', [ChallengeController::class, 'userChallenges'])->whereNumber('id');
    Route::get('users/{id}/accepted-challenges', [ChallengeController::class, 'acceptedChallenges'])->whereNumber('id');
    Route::get('users/{id}/completed-challenges', [ChallengeController::class, 'completedChallenges'])->whereNumber('id');
    Route::get('bigboss-challenger', [ChallengeController::class, 'leaderboard']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::post('/support', [SupportController::class, 'store']);
    Route::post('send-tip', [TipController::class, 'sendTip']);

    // Send Coin
    Route::get('user-list', [TipController::class, 'userList']);
    Route::post('send-coin', [TipController::class, 'sendCoin']);

    Route::get('user-transactions', [WinnerController::class, 'userTransactions']);
    Route::get('past-supports', [SupportController::class, 'pastSupport']);
    Route::get('referral-link-used', [SupportController::class, 'referralLinkUsed']);
    Route::get('support-history', [SupportController::class, 'supportHistory']);

    Route::post('/vote', [MatchForVotingController::class, 'vote']);
    Route::post('/vote-player/{match_id}', [MatchForVotingController::class, 'votePlayer']);

    // Big Boss Challenge (player actions)
    Route::get('my-challenge-access', [ChallengeController::class, 'canCreate']);
    Route::get('challenges-for-me', [ChallengeController::class, 'incoming']);
    Route::post('challenges', [ChallengeController::class, 'store']);
    Route::post('challenges/{id}/accept', [ChallengeController::class, 'accept']);
    Route::post('challenges/{id}/decline', [ChallengeController::class, 'decline']);
    Route::post('challenges/{id}/ready', [ChallengeController::class, 'ready']);
    Route::post('challenges/{id}/submit-result', [ChallengeController::class, 'submitResult']);
    Route::post('challenges/{id}/cancel', [ChallengeController::class, 'cancel']);
});

require __DIR__.'/backend.php';
require __DIR__.'/frontend.php';
