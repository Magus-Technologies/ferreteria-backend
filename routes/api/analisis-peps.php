<?php

use App\Http\Controllers\Analisis\AnalisisPepsController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas para Análisis PEPS (diferencia de tipo de cambio)
 * Prefix: /api/analisis-peps
 */

Route::prefix('analisis-peps')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [AnalisisPepsController::class, 'index']);
    Route::post('/calcular', [AnalisisPepsController::class, 'calcular']);
});
