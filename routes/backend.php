<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    // User Manage
    Route::apiResource('users', UserController::class);
    Route::post('users/unsuspend/{user}', [UserController::class, 'unsuspend']);

    // Withdraw Manage
    Route::get('withdraws', [WithdrawController::class, 'index']);
    Route::post('withdraws/accept', [WithdrawController::class, 'accept']);
    Route::post('withdraws/declined', [WithdrawController::class, 'declined']);
});
