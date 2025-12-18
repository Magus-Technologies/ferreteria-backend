<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\CompraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

    // Recursos principales
    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('ventas', VentaController::class);
    Route::apiResource('cotizaciones', CotizacionController::class);
    Route::apiResource('compras', CompraController::class);

    // TODO: Agregar más rutas según necesidad
    // Route::apiResource('clientes', ClienteController::class);
    // Route::apiResource('proveedores', ProveedorController::class);
    // Route::apiResource('almacenes', AlmacenController::class);
    // etc.
});
