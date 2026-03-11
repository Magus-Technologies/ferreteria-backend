<?php

use App\Http\Controllers\VentaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteReporteController;
use App\Http\Controllers\ClienteCalificacionController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\EntregaProductoController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\SerieDocumentoController;
use App\Http\Controllers\ChoferController;
use App\Http\Controllers\RecepcionAlmacenController;
use App\Http\Controllers\KardexController;
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
    Route::prefix('ventas')->group(function () {
        Route::get('/por-cobrar', [VentaController::class, 'ventasPorCobrar']);
        Route::get('/historial', [VentaController::class, 'historialGeneral']);
        Route::get('/{id}/historial', [VentaController::class, 'getHistorial']);
        Route::get('/{id}/cobros', [VentaController::class, 'getCobros']);   // Listar cobros de una venta
        Route::post('/{id}/cobros', [VentaController::class, 'storeCobro']); // Registrar un cobro
    });
    Route::apiResource('ventas', VentaController::class)->middleware('caja.abierta');

    // ============================================
    // COMPRAS
    // ============================================
    Route::prefix('compras')->group(function () {
        Route::get('/resumen-mensual', [CompraController::class, 'resumenMensual']);
        Route::get('/reporte', [CompraController::class, 'reporteCompras']);
        Route::get('/resumen', [CompraController::class, 'resumenCompras']);
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
    
    // Rutas de direcciones de clientes
    Route::get('clientes/{clienteId}/direcciones', [ClienteController::class, 'listarDirecciones']);
    Route::post('clientes/{clienteId}/direcciones', [ClienteController::class, 'crearDireccion']);
    Route::put('direcciones/{id}', [ClienteController::class, 'actualizarDireccion']);
    Route::delete('direcciones/{id}', [ClienteController::class, 'eliminarDireccion']);
    Route::post('direcciones/{id}/marcar-principal', [ClienteController::class, 'marcarDireccionPrincipal']);
    
    Route::apiResource('clientes', ClienteController::class);

    // ============================================
    // CLIENTES - REPORTES
    // ============================================
    Route::prefix('cliente-reportes')->group(function () {
        Route::get('/top-clientes', [ClienteReporteController::class, 'topClientes']);
        Route::get('/resumen', [ClienteReporteController::class, 'resumen']);
        Route::get('/por-cobrar', [ClienteReporteController::class, 'clientesPorCobrar']);
        Route::get('/listado', [ClienteReporteController::class, 'listadoClientes']);
        Route::get('/frecuentes', [ClienteReporteController::class, 'clientesFrecuentes']);
        Route::get('/recientes', [ClienteReporteController::class, 'clientesRecientes']);
    });

    // ============================================
    // CALIFICACIONES DE CLIENTES
    // ============================================
    Route::get('clientes/{clienteId}/calificaciones', [ClienteCalificacionController::class, 'index']);
    Route::get('clientes/{clienteId}/calificaciones/ultima', [ClienteCalificacionController::class, 'ultimaCalificacion']);
    Route::post('clientes/{clienteId}/calificaciones', [ClienteCalificacionController::class, 'store']);
    Route::put('calificaciones/{calificacionId}', [ClienteCalificacionController::class, 'update']);
    Route::delete('calificaciones/{calificacionId}', [ClienteCalificacionController::class, 'destroy']);
    Route::get('calificaciones/estados', [ClienteCalificacionController::class, 'estados']);

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
    // KARDEX
    // ============================================
    Route::get('kardex', [KardexController::class, 'index']);
    Route::get('kardex/inventario', [KardexController::class, 'inventario']);

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
