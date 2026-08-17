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
        Route::post('/import-update', [ProductoImportController::class, 'importUpdate']);
        Route::get('/import-progress/{importId}', [ProductoImportController::class, 'progress']);
        Route::post('/import-cancel/{importId}', [ProductoImportController::class, 'cancel']);
        Route::get('/import-results/{importId}', [ProductoImportController::class, 'results']);
    });

    // ============================================
    // REPORTES (antes de rutas con {id})
    // ============================================
    Route::prefix('productos')->group(function () {
        Route::get('/vencimientos', [ProductoController::class, 'vencimientos']);
        // Listado LIGERO optimizado para el modal de búsqueda.
        // Devuelve TODOS los productos del almacén con un shape mínimo
        // (sin compras, sin productoComplementario, sin tiene_ingresos).
        // Cache 10 min en el service.
        Route::get('/listado-modal', [ProductoController::class, 'listadoModal']);

        // Listado COMPLETO para la vista "Mi Almacén".
        // Devuelve TODOS los productos con shape completo (ambos estados,
        // tiene_ingresos, img, ficha_tecnica, todos los almacenes).
        // SIN compras. Cache 10 min en el service.
        Route::get('/listado-completo', [ProductoController::class, 'listadoCompleto']);
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
        Route::get('/demanda-por-categoria', [InventarioReporteController::class, 'demandaPorCategoria']);
        Route::get('/costo-ajuste', [InventarioReporteController::class, 'costoAjuste']);
        Route::get('/productos-rotados', [InventarioReporteController::class, 'productosRotados']);
        Route::get('/inventario-por-anio', [InventarioReporteController::class, 'inventarioPorAnio']);
        Route::get('/productos-sin-rotar', [InventarioReporteController::class, 'productosSinRotar']);
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