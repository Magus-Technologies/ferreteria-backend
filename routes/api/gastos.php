<?php

use App\Http\Controllers\EgresoDineroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gastos Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de gestión contable y financiera - Mis Gastos
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // Rutas principales de gastos
    Route::prefix('gastos')->middleware('broadcast:gastos')->group(function () {

        // Obtener reporte de gastos con filtros
        Route::get('/', [EgresoDineroController::class, 'index']);

        // Obtener resumen de gastos (para las cards)
        Route::get('/resumen', [EgresoDineroController::class, 'resumen']);

        // Crear nuevo gasto
        Route::post('/', [EgresoDineroController::class, 'store']);

        // Mostrar gasto específico
        Route::get('/{id}', [EgresoDineroController::class, 'show']);

        // Actualizar gasto
        Route::put('/{id}', [EgresoDineroController::class, 'update']);

        // Anular gasto
        Route::delete('/{id}', [EgresoDineroController::class, 'destroy']);

        // Exportar reporte
        Route::post('/exportar', [EgresoDineroController::class, 'exportar']);

        // Enviar por correo
        Route::post('/enviar-correo', [EgresoDineroController::class, 'enviarCorreo']);
    });

    // Rutas para los gastos extras
    Route::prefix('gastos-extras')->middleware('broadcast:gastos')->group(function () {
        Route::get('/resumen', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'resumen']);
        Route::get('/disponibles', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'disponibles']);
        Route::get('/', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\FlujoFinanciero\GastoExtraController::class, 'destroy']);
    });
});
