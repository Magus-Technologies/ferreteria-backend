<?php

use App\Http\Controllers\Producto\ProductoController;
use App\Http\Controllers\Producto\ProductoImportController;
use App\Http\Controllers\Producto\ProductoFileController;
use App\Http\Controllers\Producto\ProductoPriceController;
use App\Http\Controllers\Producto\ProductoValidationController;
use App\Http\Controllers\DetallePreciosController;
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
    // ARCHIVOS
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::post('/upload-files-masivo', [ProductoFileController::class, 'uploadMasivo']);
        Route::post('/{id}/upload-files', [ProductoFileController::class, 'upload']);
    });

    // ============================================
    // PRECIOS
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::get('/{id}/detalle-precios', [ProductoPriceController::class, 'show']);
        Route::put('/{id}/precios', [ProductoPriceController::class, 'update']);
        Route::post('/precios/bulk-update', [ProductoPriceController::class, 'bulkUpdate']);
    });

    // ============================================
    // CRUD (apiResource)
    // ============================================
    Route::apiResource('productos', ProductoController::class);

    // ============================================
    // DETALLE DE PRECIOS (Legacy - Unidades Derivadas)
    // ============================================
    Route::prefix('detalle-precios')->group(function () {
        Route::post('/import', [DetallePreciosController::class, 'import']);
        Route::post('/get-producto-almacen', [DetallePreciosController::class, 'getProductoAlmacenByCodProducto']);
        Route::post('/importar-unidades-derivadas', [DetallePreciosController::class, 'importarUnidadesDerivadas']);
    });
});
