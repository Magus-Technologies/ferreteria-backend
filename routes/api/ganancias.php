<?php

use App\Http\Controllers\GananciasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ganancias Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de gestión contable y financiera - Mis Ganancias
|
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas principales de ganancias
    Route::prefix('ganancias')->group(function () {
        
        // Obtener reporte de ganancias con filtros
        Route::get('/', [GananciasController::class, 'index']);
        
        // Obtener resumen de ganancias (para las cards)
        Route::get('/resumen', [GananciasController::class, 'resumen']);
        
        // Exportar reporte
        Route::post('/exportar', [GananciasController::class, 'exportar']);
        
        // Enviar por correo
        Route::post('/enviar-correo', [GananciasController::class, 'enviarCorreo']);

        // Obtener pagos de compras
        Route::get('/pagos-compras', [GananciasController::class, 'pagosCompras']);

        // Obtener detalle de pérdidas
        Route::get('/perdidas-detalle', [GananciasController::class, 'perdidasDetalle']);
    });
});