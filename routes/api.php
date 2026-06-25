<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Backend\Admin\ChallengeController as AdminChallengeController;
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
    Route::post('challenges/{id}/cancel', [ChallengeController::class, 'cancel']);
});

Route::group(['middleware' => ['auth:api', 'role:super_admin'], 'prefix' => 'admin'], function () {
    // Category
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'edit']);
    Route::post('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    // Game
    Route::get('games', [GameController::class, 'index']);
    Route::post('games', [GameController::class, 'store']);
    Route::get('games/{id}', [GameController::class, 'edit']);
    Route::post('games/{id}', [GameController::class, 'update']);
    Route::delete('games/{id}', [GameController::class, 'destroy']);
    Route::get('all-games', [GameController::class, 'allGames']);

    // Match
    Route::get('matches', [MatchController::class, 'index']);
    Route::post('matches', [MatchController::class, 'store']);
    Route::get('matches/{id}', [MatchController::class, 'edit']);
    Route::post('matches/{id}', [MatchController::class, 'update']);
    Route::delete('matches/{id}', [MatchController::class, 'destroy']);

    Route::patch('/pin-unpin-match/{id}', [MatchController::class, 'togglePin']);
    Route::patch('/remove-view-match/{id}', [MatchController::class, 'toggleRemove']);
    // match confirmation
    Route::post('match-confirm/{id}', [SupportController::class, 'confirm']);

    // vote Start
    Route::post('start-vote/{match_id}', [MatchForVotingController::class, 'startVote']);

    Route::get('match-players/{id}', [MatchController::class, 'players']);

    Route::get('all-players', [MatchController::class, 'allPlayers']);
    Route::post('match-winner/{id}', [WinnerController::class, 'winner']);

    // Dashboard
    Route::get('earnings', [DashboardController::class, 'earnings']);
    Route::get('recent-streams', [DashboardController::class, 'recentStreams']);
    Route::get('running-matches', [DashboardController::class, 'runningMatches']);

    // match voting
    Route::get('match-voting/', [MatchForVotingController::class, 'index']);
    Route::post('match-voting/', [MatchForVotingController::class, 'store']);
    Route::get('match-voting/{id}', [MatchForVotingController::class, 'edit']);
    Route::post('match-voting/{id}', [MatchForVotingController::class, 'update']);
    Route::delete('match-voting/{id}', [MatchForVotingController::class, 'destroy']);

    Route::get('all-transaction', [WinnerController::class, 'adminTransactions']);

    // Big Boss Challenge (admin)
    Route::get('challenges', [AdminChallengeController::class, 'index']);
    Route::get('challenge-stats', [AdminChallengeController::class, 'stats']);
    Route::post('challenges/{id}/approve', [AdminChallengeController::class, 'approve']);
    Route::post('challenges/{id}/reject', [AdminChallengeController::class, 'reject']);
    Route::post('challenges/{id}/winner', [AdminChallengeController::class, 'winner']);
    Route::post('challenges/{id}/cancel', [AdminChallengeController::class, 'cancel']);
    Route::delete('challenges/{id}', [AdminChallengeController::class, 'destroy']);
    Route::post('users/{user}/challenge-access', [AdminChallengeController::class, 'grantAccess']);
    Route::delete('users/{user}/challenge-access', [AdminChallengeController::class, 'revokeAccess']);
});

require __DIR__ . '/backend.php';
require __DIR__ . '/frontend.php';
