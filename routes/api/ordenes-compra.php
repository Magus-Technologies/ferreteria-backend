<?php

use App\Http\Controllers\RequerimientoInternoController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\TipoServicioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Requerimientos Internos & Órdenes de Compra Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // TIPOS DE SERVICIO
    // ============================================
    Route::apiResource('tipos-servicio', TipoServicioController::class);

    // ============================================
    // REQUERIMIENTOS INTERNOS
    // ============================================
    Route::prefix('requerimientos-internos')->middleware('broadcast:requerimientos-internos')->group(function () {
        Route::get('/', [RequerimientoInternoController::class, 'index']);
        Route::post('/', [RequerimientoInternoController::class, 'store']);
        Route::get('/{id}', [RequerimientoInternoController::class, 'show']);
        Route::patch('/{id}/estado', [RequerimientoInternoController::class, 'updateEstado']);
        Route::patch('/productos/{productoId}/actualizar-cantidad-ordenada', [RequerimientoInternoController::class, 'actualizarCantidadOrdenada']);
        Route::post('/{id}/enviar-correo', [RequerimientoInternoController::class, 'enviarCorreo']);

        // Aprobación por cargo
        Route::post('/{id}/pasar-aprobacion', [RequerimientoInternoController::class, 'pasarAprobacion']);
        Route::post('/{id}/aprobar', [RequerimientoInternoController::class, 'aprobar']);
        Route::post('/{id}/rechazar', [RequerimientoInternoController::class, 'rechazar']);
        Route::post('/{id}/escalar-superior', [RequerimientoInternoController::class, 'escalarASuperior']);
        Route::get('/{id}/approval_history', [RequerimientoInternoController::class, 'approvalHistory']);
    });

    // ============================================
    // ÓRDENES DE COMPRA
    // ============================================
    Route::prefix('ordenes-compra')->middleware('broadcast:ordenes-compra')->group(function () {
        Route::get('/', [OrdenCompraController::class, 'index']);
        Route::post('/', [OrdenCompraController::class, 'store']);
        Route::get('/{id}', [OrdenCompraController::class, 'show']);
        Route::put('/{id}', [OrdenCompraController::class, 'update']);
        Route::patch('/{id}/aprobar', [OrdenCompraController::class, 'aprobar']);
        Route::patch('/{id}/anular', [OrdenCompraController::class, 'anular']);
        Route::post('/{id}/enviar-correo', [OrdenCompraController::class, 'enviarCorreo']);
    });
});
