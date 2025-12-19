<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\VentaController;
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
    Route::apiResource('almacenes', AlmacenController::class);

    // Rutas específicas de productos (antes de apiResource)
    Route::get('productos/validar-codigo', [ProductoController::class, 'validarCodigo']);
    Route::get('productos/{id}/detalle-precios', [ProductoController::class, 'detallePrecios']);
    Route::post('productos/import', [ProductoController::class, 'import']);

    Route::apiResource('productos', ProductoController::class);
    Route::apiResource('ingresos-salidas', IngresoSalidaController::class);
    Route::apiResource('ventas', VentaController::class);
    Route::apiResource('cotizaciones', CotizacionController::class);
    Route::apiResource('compras', CompraController::class);

    // TODO: Agregar más rutas según necesidad
    // Route::apiResource('clientes', ClienteController::class);
    // Route::apiResource('proveedores', ProveedorController::class);
    // etc.
});
