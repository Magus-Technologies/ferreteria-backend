<?php

use App\Http\Controllers\IngresoDineroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ingresos Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de gestión contable y financiera - Mis Ingresos
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // Rutas principales de ingresos
    Route::prefix('ingresos')->middleware('broadcast:ingresos')->group(function () {

        // Obtener reporte de ingresos con filtros
        Route::get('/', [IngresoDineroController::class, 'index']);

        // Obtener resumen de ingresos (para las cards)
        Route::get('/resumen', [IngresoDineroController::class, 'resumen']);

        // Crear nuevo ingreso
        Route::post('/', [IngresoDineroController::class, 'store']);

        // Mostrar ingreso específico
        Route::get('/{id}', [IngresoDineroController::class, 'show']);

        // Actualizar ingreso
        Route::put('/{id}', [IngresoDineroController::class, 'update']);

        // Anular ingreso
        Route::delete('/{id}', [IngresoDineroController::class, 'destroy']);

        // Exportar reporte
        Route::post('/exportar', [IngresoDineroController::class, 'exportar']);

        // Enviar por correo
        Route::post('/enviar-correo', [IngresoDineroController::class, 'enviarCorreo']);
    });

    // Rutas para los ingresos extras
    Route::prefix('ingresos-extras')->middleware('broadcast:ingresos')->group(function () {
        Route::get('/resumen', [\App\Http\Controllers\FlujoFinanciero\IngresoExtraController::class, 'resumen']);
        Route::get('/', [\App\Http\Controllers\FlujoFinanciero\IngresoExtraController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\FlujoFinanciero\IngresoExtraController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\FlujoFinanciero\IngresoExtraController::class, 'update']);
        Route::post('/{id}/anular', [\App\Http\Controllers\FlujoFinanciero\IngresoExtraController::class, 'anular']);
    });
});
