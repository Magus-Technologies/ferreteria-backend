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
    Route::prefix('gastos')->group(function () {
        
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
});