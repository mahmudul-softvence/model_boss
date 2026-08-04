<?php

use App\Http\Controllers\Backend\AdminSettingController;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Backend\CredentialSettingController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\GalleryController;
use App\Http\Controllers\Backend\GameController;
use App\Http\Controllers\Backend\MatchController;
use App\Http\Controllers\Backend\MatchForVotingController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\PrivacyPolicyController;
use App\Http\Controllers\Backend\PromotionalTermController;
use App\Http\Controllers\Backend\SupportController;
use App\Http\Controllers\Backend\TermsAndConditionController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WinnerController;
use App\Http\Controllers\Backend\WithdrawController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'role:super_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::post('dashboard/change_live_status', [DashboardController::class, 'change_live_status']);

        // User Manage
        Route::get('users/search', [UserController::class, 'search']);
        Route::patch('users/change_role/{user}', [UserController::class, 'change_role']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::post('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'delete']);
        Route::post('users/unsuspend/{user}', [UserController::class, 'unsuspend']);
        Route::post('users/suspend/{user}', [UserController::class, 'suspend']);
        Route::get('users/count/total', [UserController::class, 'total_users']);

        // Withdraw Manage
        Route::get('withdraws', [WithdrawController::class, 'index']);
        Route::post('withdraws/accept/{id}', [WithdrawController::class, 'accept']);
        Route::post('withdraws/declined/{id}', [WithdrawController::class, 'declined']);

        // Gallery Manage
        Route::get('galleries', [GalleryController::class, 'index']);
        Route::post('galleries', [GalleryController::class, 'store']);
        Route::get('galleries/{gallery}', [GalleryController::class, 'show']);
        Route::post('galleries/{gallery}', [GalleryController::class, 'update']);
        Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy']);

        // News
        Route::get('news', [NewsController::class, 'index']);
        Route::post('news', [NewsController::class, 'store']);
        Route::get('news/{news}', [NewsController::class, 'show']);
        Route::post('news/{news}', [NewsController::class, 'update']);
        Route::delete('news/{news}', [NewsController::class, 'destroy']);

        // Admin Profile Change

        // POST is required for multipart/form-data (image upload); PUT still works for JSON.
        Route::match(['put', 'post'], 'settings', [AdminSettingController::class, 'update']);
        Route::put('settings/change_password', [AdminSettingController::class, 'change_password']);
        Route::get('settings/auto_accept_withdraw', [AdminSettingController::class, 'get_auto_accept_withdraw']);
        Route::put('settings/auto_accept_withdraw', [AdminSettingController::class, 'auto_accept_withdraw']);
        Route::get('settings/auto_offer_challenges', [AdminSettingController::class, 'get_auto_offer_challenges']);
        Route::put('settings/auto_offer_challenges', [AdminSettingController::class, 'auto_offer_challenges']);
        Route::get('settings/challenge_rules', [AdminSettingController::class, 'get_challenge_rules']);
        Route::put('settings/challenge_rules', [AdminSettingController::class, 'update_challenge_rules']);
        Route::get('settings/social_links', [AdminSettingController::class, 'get_social_links']);
        Route::put('settings/social_links', [AdminSettingController::class, 'update_social_links']);

        // Promotional Terms Content
        Route::get('promotional-terms', [PromotionalTermController::class, 'show']);
        Route::put('promotional-terms', [PromotionalTermController::class, 'update']);

        // Privacy Policy & Terms
        Route::get('privacy-policy', [PrivacyPolicyController::class, 'show']);
        Route::put('privacy-policy', [PrivacyPolicyController::class, 'update']);
        Route::get('terms-and-conditions', [TermsAndConditionController::class, 'show']);
        Route::put('terms-and-conditions', [TermsAndConditionController::class, 'update']);

        // Credential Settings
        Route::get('credentials', [CredentialSettingController::class, 'index']);
        Route::put('credentials/{group}', [CredentialSettingController::class, 'update']);

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
        Route::get('challenges/{id}/submissions', [AdminChallengeController::class, 'submissions']);
        Route::post('challenges/{id}/winner', [AdminChallengeController::class, 'winner']);
        Route::post('challenges/{id}/release-payout', [AdminChallengeController::class, 'releasePayout']);
        Route::post('challenges/{id}/cancel', [AdminChallengeController::class, 'cancel']);
        Route::post('challenges/{id}/publish-match', [AdminChallengeController::class, 'publishMatch']);
        Route::delete('challenges/{id}', [AdminChallengeController::class, 'destroy']);
        Route::post('users/{user}/challenge-access', [AdminChallengeController::class, 'grantAccess']);
        Route::delete('users/{user}/challenge-access', [AdminChallengeController::class, 'revokeAccess']);
    });

Route::middleware('auth:api')->group(function () {
    Route::get('notifications', [NotificationController::class, 'notifications']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read_notifications']);
    Route::delete('notifications/delete', [NotificationController::class, 'delete_all_notifications']);
    Route::delete('notifications/{id}/delete', [NotificationController::class, 'delete_notifications']);
});
