<?php

use App\Http\Controllers\Entrega\EntregaAccionesController;
use App\Http\Controllers\Entrega\EntregaCatalogosController;
use App\Http\Controllers\Entrega\EntregaCrudController;
use App\Http\Controllers\Entrega\EntregaListadoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Entregas Routes
|--------------------------------------------------------------------------
|
| Módulo de entregas reestructurado.
| Controllers en App\Http\Controllers\Entrega\
|
*/

// Rutas heredan auth:sanctum del grupo padre en api.php

// ============================================
// CATÁLOGOS (read-only)
// ============================================
Route::prefix('entregas/catalogos')->group(function () {
    Route::get('tipos-entrega',   [EntregaCatalogosController::class, 'tiposEntrega']);
    Route::get('tipos-despacho',  [EntregaCatalogosController::class, 'tiposDespacho']);
    Route::get('estados-entrega', [EntregaCatalogosController::class, 'estadosEntrega']);
    Route::get('quien-entrega',   [EntregaCatalogosController::class, 'quienesEntrega']);
});

// ============================================
// LISTADOS (tabla maestra + tabla detalle)
// ============================================
Route::prefix('entregas')->group(function () {
    Route::get('resumen-ventas',      [EntregaListadoController::class, 'resumenVentas']);
    Route::get('por-venta/{ventaId}', [EntregaListadoController::class, 'porVenta']);
    Route::get('/',                   [EntregaListadoController::class, 'listar']);

    // ── CRUD ──────────────────────────────────────────────────
    Route::post('/',           [EntregaCrudController::class, 'store']);
    Route::get('{id}',         [EntregaCrudController::class, 'show']);
    Route::put('{id}',         [EntregaCrudController::class, 'update']);
    Route::delete('{id}',      [EntregaCrudController::class, 'destroy']);

    // ── ACCIONES DE ESTADO ────────────────────────────────────
    Route::post('{id}/confirmar',       [EntregaAccionesController::class, 'confirmar']);
    Route::post('{id}/en-camino',       [EntregaAccionesController::class, 'enCamino']);
    Route::post('{id}/anular',          [EntregaAccionesController::class, 'anular']);
    Route::post('{id}/reasignar-chofer',[EntregaAccionesController::class, 'reasignarChofer']);
    Route::post('{id}/aceptar-pedido',  [EntregaAccionesController::class, 'aceptarPedido']);
});
