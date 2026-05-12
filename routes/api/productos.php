<?php

use App\Http\Controllers\Producto\ProductoController;
use App\Http\Controllers\Producto\ProductoImportController;
use App\Http\Controllers\Producto\ProductoFileController;
use App\Http\Controllers\Producto\ProductoPriceController;
use App\Http\Controllers\Producto\ProductoValidationController;
use App\Http\Controllers\DetallePreciosController;
use App\Http\Controllers\InventarioReporteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de productos: CRUD, importación, archivos, precios
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // VALIDACIONES (deben ir antes de apiResource)
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::get('/validar-codigo', [ProductoValidationController::class, 'validateCode']);
        Route::get('/validar-codigo-barra', [ProductoValidationController::class, 'validateBarcode']);
        Route::get('/validar-nombre', [ProductoValidationController::class, 'validateName']);
    });

    // ============================================
    // IMPORTACIÓN
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::post('/import', [ProductoImportController::class, 'import']);
        Route::get('/import-progress/{importId}', [ProductoImportController::class, 'progress']);
        Route::post('/import-cancel/{importId}', [ProductoImportController::class, 'cancel']);
        Route::get('/import-results/{importId}', [ProductoImportController::class, 'results']);
    });

    // ============================================
    // REPORTES (antes de rutas con {id})
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::get('/venimientos', [ProductoController::class, 'venimientos']);
    });

    // ============================================
    // ARCHIVOS
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::post('/upload-files-masivo', [ProductoFileController::class, 'uploadMasivo']);
        Route::post('/{id}/upload-files', [ProductoFileController::class, 'upload']);
    });

    // ============================================
    // CRUD (apiResource)
    // ============================================
    Route::apiResource('productos', ProductoController::class)->middleware('broadcast:productos');

    // ============================================
    // PRECIOS (después de apiResource)
    // ============================================
    Route::prefix('productos')->middleware('broadcast:productos')->group(function () {
        Route::get('/{id}/detalle-precios', [ProductoPriceController::class, 'show']);
        Route::put('/{id}/precios', [ProductoPriceController::class, 'update']);
        Route::post('/precios/bulk-update', [ProductoPriceController::class, 'bulkUpdate']);
    });

    // ============================================
    // REPORTES DE INVENTARIO
    // ============================================
    Route::prefix('inventario-reportes')->group(function () {
        Route::get('/top-productos', [InventarioReporteController::class, 'topProductos']);
        Route::get('/resumen', [InventarioReporteController::class, 'resumen']);
        Route::get('/stock-valorizado', [InventarioReporteController::class, 'stockValorizado']);
        Route::get('/stock-bajo', [InventarioReporteController::class, 'stockBajo']);
        Route::get('/cantidades-vendidas', [InventarioReporteController::class, 'cantidadesVendidas']);
    });

    // ============================================
    // DETALLE DE PRECIOS (Legacy - Unidades Derivadas)
    // ============================================
    Route::prefix('detalle-precios')->middleware('broadcast:productos')->group(function () {
        Route::post('/import', [DetallePreciosController::class, 'import']);
        Route::post('/get-producto-almacen', [DetallePreciosController::class, 'getProductoAlmacenByCodProducto']);
        Route::post('/importar-unidades-derivadas', [DetallePreciosController::class, 'importarUnidadesDerivadas']);
    });
});