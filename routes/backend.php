<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\GalleryController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WithdrawController;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    // User Manage
    Route::get('users/search', [UserController::class, 'search']);
    Route::patch('users/change_role/{user}', [UserController::class, 'change_role']);
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users/{user}', [UserController::class, 'update']);
    Route::post('users/unsuspend/{user}', [UserController::class, 'unsuspend']);
    Route::post('users/suspend/{user}', [UserController::class, 'suspend']);

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


    Route::get('update/something', function () {
        // $request->validate([
        //     'name' => 'required',
        //     'email' => 'required'
        // ]);

        $g = Gallery::get();

        return response()->json([
            'gallery' => GalleryResource::collection($g)
        ]);
    });
});
