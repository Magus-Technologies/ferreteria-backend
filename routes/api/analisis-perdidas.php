<?php

use App\Http\Controllers\Analisis\AnalisisPerdidasController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas para Análisis de Pérdidas
 * Prefix: /api/analisis-perdidas
 */

Route::prefix('analisis-perdidas')->middleware(['auth:sanctum'])->group(function () {
    // Obtener análisis completo de pérdidas
    Route::get('/', [AnalisisPerdidasController::class, 'index']);
    
    // Obtener resumen de pérdidas por categoría
    Route::get('/resumen', [AnalisisPerdidasController::class, 'resumen']);
});
