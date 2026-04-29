<?php

use App\Http\Controllers\KardexInventarioController;
use App\Http\Controllers\KardexFacturacionController;
use App\Http\Controllers\KardexFinanzasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kardex Routes
|--------------------------------------------------------------------------
|
| Rutas para los diferentes tipos de kardex:
| - Kardex de Inventario (movimientos de stock)
| - Kardex de Facturación (ventas y documentos)
| - Kardex de Finanzas (movimientos financieros)
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // KARDEX DE INVENTARIO
    // ============================================
    Route::get('kardex-inventario', [KardexInventarioController::class, 'index']);

    // ============================================
    // KARDEX DE FACTURACIÓN
    // ============================================
    Route::get('kardex-facturacion', [KardexFacturacionController::class, 'index']);

    // ============================================
    // KARDEX DE FINANZAS
    // ============================================
    Route::get('kardex/finanzas', [KardexFinanzasController::class, 'index']);
});
