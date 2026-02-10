<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::apiResource('users', UserController::class);
    Route::post('users/unsuspend/{user}', [UserController::class, 'unsuspend']);
});
