<?php

use App\Http\Controllers\RequerimientoInternoController;
use App\Http\Controllers\OrdenCompraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Requerimientos Internos & Órdenes de Compra Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // REQUERIMIENTOS INTERNOS
    // ============================================
    Route::prefix('requerimientos-internos')->group(function () {
        Route::get('/', [RequerimientoInternoController::class, 'index']);
        Route::post('/', [RequerimientoInternoController::class, 'store']);
        Route::get('/{id}', [RequerimientoInternoController::class, 'show']);
        Route::patch('/{id}/estado', [RequerimientoInternoController::class, 'updateEstado']);
    });

    // ============================================
    // ÓRDENES DE COMPRA
    // ============================================
    Route::prefix('ordenes-compra')->group(function () {
        Route::get('/', [OrdenCompraController::class, 'index']);
        Route::post('/', [OrdenCompraController::class, 'store']);
        Route::get('/{id}', [OrdenCompraController::class, 'show']);
        Route::patch('/{id}/aprobar', [OrdenCompraController::class, 'aprobar']);
        Route::patch('/{id}/anular', [OrdenCompraController::class, 'anular']);
    });
});
