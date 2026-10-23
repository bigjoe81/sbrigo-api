<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['trusted-spa-origin', 'throttle:register']);
Route::post('/otp/send', [AuthController::class, 'sendOtp'])
    ->middleware(['trusted-spa-origin', 'throttle:otp-send']);
Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])
    ->middleware(['trusted-spa-origin', 'throttle:otp-verify']);

Route::middleware(['auth:web', 'verified'])->group(function (): void {
    Route::apiResource('contacts', ContactController::class);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/admin/users', [UserController::class, 'index'])
        ->middleware('permission:manage-users');
});
