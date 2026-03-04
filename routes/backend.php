<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\GalleryController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    // User Manage
    Route::get('users/search', [UserController::class, 'search']);
    Route::patch('users/change_role/{user}', [UserController::class, 'change_role']);
    Route::apiResource('users', UserController::class)->except(['destroy']);
    Route::post('users/unsuspend/{user}', [UserController::class, 'unsuspend']);
    Route::post('users/suspend/{user}', [UserController::class, 'suspend']);

    // Withdraw Manage
    Route::get('withdraws', [WithdrawController::class, 'index']);
    Route::post('withdraws/accept/{id}', [WithdrawController::class, 'accept']);
    Route::post('withdraws/declined/{id}', [WithdrawController::class, 'declined']);

    // Gallery
    Route::apiResource('galleries', GalleryController::class);

    // News
    Route::apiResource('news', NewsController::class);
});
