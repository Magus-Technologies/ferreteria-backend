<?php

use App\Http\Controllers\ComisionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Comisiones Routes
|--------------------------------------------------------------------------
|
| Módulo de gestión de comisiones de vendedores.
| - Resumen por vendedor (generado vs pagado vs pendiente)
| - Detalle de ventas con comisión
| - Registro y listado de pagos de comisiones
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('comisiones')->group(function () {
        Route::get('/por-vendedor', [ComisionController::class, 'porVendedor']);
        Route::get('/detalle/{userId}', [ComisionController::class, 'detalleVendedor']);
        Route::get('/pagos', [ComisionController::class, 'historialPagos']);
        Route::post('/pagos', [ComisionController::class, 'registrarPago']);
        Route::delete('/pagos/{id}', [ComisionController::class, 'eliminarPago']);
    });
});
