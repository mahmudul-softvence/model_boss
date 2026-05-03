<?php

use App\Http\Controllers\Backend\AdminSettingController;
use App\Http\Controllers\Backend\CredentialSettingController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\GalleryController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WithdrawController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'role:super_admin'])->prefix('admin')->group(function () {
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

    Route::put('settings', [AdminSettingController::class, 'update']);
    Route::put('settings/change_password', [AdminSettingController::class, 'change_password']);
    Route::get('settings/auto_accept_withdraw', [AdminSettingController::class, 'get_auto_accept_withdraw']);
    Route::put('settings/auto_accept_withdraw', [AdminSettingController::class, 'auto_accept_withdraw']);

    // Credential Settings
    Route::get('credentials', [CredentialSettingController::class, 'index']);
    Route::put('credentials/{group}', [CredentialSettingController::class, 'update']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('notifications', [NotificationController::class, 'notifications']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read_notifications']);
    Route::delete('notifications/delete', [NotificationController::class, 'delete_all_notifications']);
    Route::delete('notifications/{id}/delete', [NotificationController::class, 'delete_notifications']);
});
