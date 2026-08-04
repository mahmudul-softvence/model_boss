<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\GameController;
use App\Http\Controllers\Backend\MatchController;
use App\Http\Controllers\Backend\MatchForVotingController;
use App\Http\Controllers\Backend\SupportController;
use App\Http\Controllers\Backend\TipController;
use App\Http\Controllers\Backend\WinnerController;
use App\Http\Controllers\Frontend\ChallengeController;
use App\Http\Controllers\Frontend\ChallengeRuleController;
use App\Http\Controllers\Frontend\FollowController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PostController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\PromotionalTermController;
use App\Http\Controllers\Frontend\SocialLinkController;
use App\Http\Controllers\Frontend\TwitchController;
use App\Http\Controllers\Payment\Stripe\CheckoutController;
use App\Http\Controllers\Withdraw\Bitpay\BitpayConnectController;
use App\Http\Controllers\Withdraw\Bitpay\BitpayWithdrawController;
use App\Http\Controllers\Withdraw\Moncash\MoncashConnectController;
use App\Http\Controllers\Withdraw\Moncash\MoncashWithdrawController;
use App\Http\Controllers\Withdraw\Paypal\PaypalConnectController;
use App\Http\Controllers\Withdraw\Paypal\PaypalWithdrawController;
use App\Http\Controllers\Withdraw\Stripe\StripeConnectController;
use App\Http\Controllers\Withdraw\Stripe\StripeWithdrawController;
use Illuminate\Support\Facades\Route;

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

    // Post
    Route::apiResource('posts', PostController::class);

    // Follow/Unfollow
    Route::post('follow/{id}', [FollowController::class, 'follow']);
    Route::delete('unfollow/{id}', [FollowController::class, 'unfollow']);
    Route::get('followers/count', [ProfileController::class, 'followersCount']);
    Route::get('following/count', [ProfileController::class, 'followingCount']);
    // New paginated endpoints that include mutual flags for UI (do not replace existing routes)
    Route::get('followers/list', [ProfileController::class, 'followersList']);
    Route::get('following/list', [ProfileController::class, 'followingList']);
    Route::get('see_follower', [ProfileController::class, 'see_follower']);
    Route::get('see_following', [ProfileController::class, 'see_following']);
    // Followers/following of a specific user (e.g. after searching an artist)
    Route::get('users/{id}/followers', [FollowController::class, 'userFollowers']);
    Route::get('users/{id}/following', [FollowController::class, 'userFollowing']);

    // Profile
    Route::post('profile/update', [ProfileController::class, 'update']);
    Route::patch('profile/bio', [ProfileController::class, 'updateBio']);
    Route::post('profile/change-fav-game', [ProfileController::class, 'changeFavGame']);
    Route::post('profile/toggle-email-visibility', [ProfileController::class, 'toggleEmailVisibility']);
    Route::post('profile/visibility', [ProfileController::class, 'updateVisibility']);

    // Twitch live
    Route::get('twitch/check_live', [TwitchController::class, 'status']);

    // Payment
    Route::post('checkout', [CheckoutController::class, 'checkout']);
    Route::post('connect_account', [CheckoutController::class, 'connect_account']);

    // Stripe payout account
    Route::post('stripe/connect', [StripeConnectController::class, 'connect']);
    Route::delete('stripe/disconnect', [StripeConnectController::class, 'disconnect']);
    Route::get('stripe/status', [StripeConnectController::class, 'status']);
    Route::post('stripe/withdraw', [StripeWithdrawController::class, 'request']);

    // PayPal payout account
    Route::post('paypal/connect', [PaypalConnectController::class, 'connect']);
    Route::delete('paypal/disconnect', [PaypalConnectController::class, 'disconnect']);
    Route::get('paypal/status', [PaypalConnectController::class, 'status']);
    Route::post('paypal/withdraw', [PaypalWithdrawController::class, 'request']);

    // BitPay payout account
    Route::post('bitpay/connect', [BitpayConnectController::class, 'connect']);
    Route::delete('bitpay/disconnect', [BitpayConnectController::class, 'disconnect']);
    Route::get('bitpay/status', [BitpayConnectController::class, 'status']);
    Route::post('bitpay/withdraw', [BitpayWithdrawController::class, 'request']);

    // MonCash payout account
    Route::post('moncash/connect', [MoncashConnectController::class, 'connect']);
    Route::delete('moncash/disconnect', [MoncashConnectController::class, 'disconnect']);
    Route::get('moncash/status', [MoncashConnectController::class, 'status']);
    Route::post('moncash/withdraw', [MoncashWithdrawController::class, 'request']);

    // Legacy withdraw route (Stripe)
    Route::post('withdraw/request', [StripeWithdrawController::class, 'request']);

    Route::get('show_artist_prifile/{id}', [ProfileController::class, 'show_artist_prifile']);
    Route::get('show_artist_posts/{id}', [ProfileController::class, 'show_artist_posts']);

    // Global challenge rules (readable by any authenticated user)
    Route::get('challenge-rules', [ChallengeRuleController::class, 'index']);
});

Route::middleware(['api'])->group(function () {
    Route::get('get_featured_news', [HomeController::class, 'get_featured_news']);
    Route::get('get_featured_gallery', [HomeController::class, 'get_featured_gallery']);
    Route::get('get_live_staus', [HomeController::class, 'get_live_staus']);
    Route::get('search_artist', [HomeController::class, 'search_artist']);
    Route::get('get_all_games', [HomeController::class, 'get_all_games']);
    Route::get('promotional-terms', [PromotionalTermController::class, 'index']);
    Route::get('social-links', [SocialLinkController::class, 'index']);
    Route::get('get_users_for_select', [HomeController::class, 'get_users_for_select']);
});
