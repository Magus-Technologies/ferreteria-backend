<?php

use App\Http\Controllers\GananciasController;
use App\Http\Controllers\DashboardContableController;
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

    // Dashboard contable y financiero (gráficos)
    Route::prefix('dashboard-contable')->group(function () {
        Route::get('/porcentaje-ganancias', [DashboardContableController::class, 'porcentajeGanancias']);
        Route::get('/cierres-con-perdida', [DashboardContableController::class, 'cierresConPerdida']);
        Route::get('/clientes-morosos', [DashboardContableController::class, 'clientesMorosos']);
        Route::get('/ganancias-por-recomendacion', [DashboardContableController::class, 'gananciasPorRecomendacion']);
        Route::get('/resumen-cards', [DashboardContableController::class, 'resumenCards']);
    });
});