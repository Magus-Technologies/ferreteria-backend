<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Rutas de autenticación: login, logout, recuperación de contraseña
|
*/

// ============================================
// RUTAS PÚBLICAS (Sin autenticación)
// ============================================

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Recuperación de contraseña
Route::prefix('password')->group(function () {
    Route::post('/send-code', [PasswordResetController::class, 'sendCode']);
    Route::post('/verify-code', [PasswordResetController::class, 'verifyCode']);
    Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

// ============================================
// RUTAS PROTEGIDAS (Requieren autenticación)
// ============================================

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});
