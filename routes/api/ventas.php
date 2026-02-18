<?php

use App\Http\Controllers\VentaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\EntregaProductoController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\SerieDocumentoController;
use App\Http\Controllers\ChoferController;
use App\Http\Controllers\RecepcionAlmacenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales & Operations Routes
|--------------------------------------------------------------------------
|
| Rutas de ventas, compras, cotizaciones, préstamos, clientes, proveedores
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // VENTAS
    // ============================================
    Route::apiResource('ventas', VentaController::class)->middleware('caja.abierta');

    // ============================================
    // COMPRAS
    // ============================================
    Route::prefix('compras')->group(function () {
        Route::get('/por-pagar', [CompraController::class, 'comprasPorPagar']);
        Route::get('/{id}/pagos', [CompraController::class, 'getPagos']);
        Route::post('/{id}/pagos', [CompraController::class, 'storePago']);
        Route::put('/{id}/lotes-vencimientos', [CompraController::class, 'updateLotesVencimientos']);
    });
    Route::apiResource('compras', CompraController::class);

    // ============================================
    // COTIZACIONES
    // ============================================
    Route::prefix('cotizaciones')->group(function () {
        Route::get('/siguiente-numero/preview', [CotizacionController::class, 'siguienteNumero']);
        Route::post('/{id}/convertir-a-venta', [CotizacionController::class, 'convertirAVenta']);
    });
    Route::apiResource('cotizaciones', CotizacionController::class);

    // ============================================
    // PRÉSTAMOS (Clientes)
    // ============================================
    Route::prefix('prestamos')->middleware('caja.abierta')->group(function () {
        Route::get('/siguiente-numero/preview', [PrestamoController::class, 'siguienteNumero']);
        Route::get('/{id}/pagos', [PrestamoController::class, 'listarPagos']);
        Route::post('/{id}/pagos', [PrestamoController::class, 'registrarPago']);
        Route::delete('/{prestamo_id}/pagos/{pago_id}', [PrestamoController::class, 'eliminarPago']);
    });
    Route::apiResource('prestamos', PrestamoController::class)->middleware('caja.abierta');

    // ============================================
    // CLIENTES
    // ============================================
    Route::get('clientes/estadisticas', [ClienteController::class, 'estadisticas']);
    Route::post('clientes/check-documento', [ClienteController::class, 'checkDocumento']);
    Route::apiResource('clientes', ClienteController::class);

    // ============================================
    // PROVEEDORES
    // ============================================
    Route::get('proveedores/check-documento', [ProveedorController::class, 'checkDocumento']);
    Route::apiResource('proveedores', ProveedorController::class);

    // ============================================
    // INGRESOS Y SALIDAS (Inventario)
    // ============================================
    Route::apiResource('ingresos-salidas', IngresoSalidaController::class);

    // ============================================
    // RECEPCIONES DE ALMACÉN
    // ============================================
    Route::apiResource('recepciones-almacen', RecepcionAlmacenController::class)->only(['index', 'show', 'store', 'destroy']);

    // ============================================
    // ENTREGAS DE PRODUCTOS
    // ============================================
    Route::apiResource('entregas-productos', EntregaProductoController::class);

    // ============================================
    // PAQUETES
    // ============================================
    Route::get('paquetes/by-producto/{productoId}', [PaqueteController::class, 'byProducto']);
    Route::apiResource('paquetes', PaqueteController::class);

    // ============================================
    // SERIES DE DOCUMENTOS
    // ============================================
    Route::get('series-documentos/siguiente-numero/preview', [SerieDocumentoController::class, 'siguienteNumero']);
    Route::apiResource('series-documentos', SerieDocumentoController::class);

    // ============================================
    // CHOFERES
    // ============================================
    Route::get('choferes/buscar-dni/{dni}', [ChoferController::class, 'buscarPorDni']);
    Route::apiResource('choferes', ChoferController::class);

    // ============================================
    // NOTAS DE DÉBITO (Facturación Electrónica)
    // ============================================
    Route::prefix('notas-debito')->group(function () {
        Route::post('/', [\App\Http\Controllers\NotaDebitoController::class, 'store']);
        Route::post('/consultar-estado', [\App\Http\Controllers\NotaDebitoController::class, 'consultarEstado']);
        Route::get('/{serie}/{numero}/xml', [\App\Http\Controllers\NotaDebitoController::class, 'verXml']);
    });
});
