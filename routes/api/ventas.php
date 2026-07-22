<?php

use App\Http\Controllers\VentaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProfesionController;
use App\Http\Controllers\ClienteReporteController;
use App\Http\Controllers\ClienteCalificacionController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ProveedorCalificacionController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\EntregaProductoController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\SerieDocumentoController;
use App\Http\Controllers\ChoferController;
use App\Http\Controllers\TransportistaController;
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
    Route::prefix('ventas')->middleware('broadcast:ventas')->group(function () {
        Route::get('/por-cobrar', [VentaController::class, 'ventasPorCobrar']);
        Route::get('/historial', [VentaController::class, 'historialGeneral']);
        Route::get('/cobros', [VentaController::class, 'getAllCobros']); // Listar TODOS los cobros con filtros
        Route::post('/cobro-multiple', [VentaController::class, 'storeCobroMultiple']); // Cobro múltiple por cliente
        Route::get('/{id}/historial', [VentaController::class, 'getHistorial']);
        Route::get('/{id}/cobros', [VentaController::class, 'getCobros']);   // Listar cobros de una venta
        Route::post('/{id}/cobros', [VentaController::class, 'storeCobro']); // Registrar un cobro
        Route::put('/{ventaId}/cobros/{cobroId}/anular', [VentaController::class, 'anularCobro']); // Anular un cobro
    });
    // Consultar ventas (mis-ventas) es solo lectura y no debe exigir caja abierta.
    Route::apiResource('ventas', VentaController::class)
        ->only(['index', 'show'])
        ->middleware('broadcast:ventas');

    Route::apiResource('ventas', VentaController::class)
        ->except(['index', 'show'])
        ->middleware(['caja.abierta', 'broadcast:ventas']);

    // ============================================
    // COMPRAS
    // ============================================
    Route::prefix('compras')->middleware('broadcast:compras')->group(function () {
        Route::get('/resumen-mensual', [CompraController::class, 'resumenMensual']);
        Route::get('/reporte', [CompraController::class, 'reporteCompras']);
        Route::get('/resumen', [CompraController::class, 'resumenCompras']);
        Route::get('/por-pagar', [CompraController::class, 'comprasPorPagar']);
        Route::get('/{id}/pagos', [CompraController::class, 'getPagos']);
        Route::get('/{id}/detalle-completo', [CompraController::class, 'detalleCompleto']);
        Route::post('/{id}/pagos', [CompraController::class, 'storePago']);
        Route::put('/{compraId}/pagos/{pagoId}/anular', [CompraController::class, 'anularPago']);
        Route::put('/{id}/lotes-vencimientos', [CompraController::class, 'updateLotesVencimientos']);
    });
    Route::apiResource('compras', CompraController::class)->middleware('broadcast:compras');

    // ============================================
    // COTIZACIONES
    // ============================================
    Route::prefix('cotizaciones')->middleware('broadcast:cotizaciones')->group(function () {
        Route::get('/siguiente-numero/preview', [CotizacionController::class, 'siguienteNumero']);
        Route::post('/{id}/convertir-a-venta', [CotizacionController::class, 'convertirAVenta']);
        Route::post('/{id}/vincular-venta', [CotizacionController::class, 'vincularVenta']);
        Route::post('/{id}/duplicar', [CotizacionController::class, 'duplicar']);
        Route::post('/{id}/eliminar', [CotizacionController::class, 'eliminar']);
    });
    Route::apiResource('cotizaciones', CotizacionController::class)->middleware('broadcast:cotizaciones');

    // ============================================
    // PRÉSTAMOS (Clientes)
    // ============================================
    // Resumen para dashboard (sin requerir caja abierta, es solo lectura).
    Route::get('prestamos-resumen-dashboard', [PrestamoController::class, 'resumenDashboard']);

    // Rutas de SOLO LECTURA - NO requieren caja abierta (index, show, consultas)
    Route::prefix('prestamos')->middleware('broadcast:prestamos')->group(function () {
        Route::get('/siguiente-numero/preview', [PrestamoController::class, 'siguienteNumero']);
        Route::get('/{id}/pagos', [PrestamoController::class, 'listarPagos']);
    });
    Route::apiResource('prestamos', PrestamoController::class)
        ->only(['index', 'show'])
        ->middleware('broadcast:prestamos');

    // Rutas de ESCRITURA - SÍ requieren caja abierta
    Route::prefix('prestamos')->middleware(['caja.abierta', 'broadcast:prestamos'])->group(function () {
        Route::post('/{id}/pagos', [PrestamoController::class, 'registrarPago']);
        Route::delete('/{prestamo_id}/pagos/{pago_id}', [PrestamoController::class, 'eliminarPago']);
        Route::post('/{prestamo_id}/pagos/{pago_id}/anular', [PrestamoController::class, 'anularPago']);
        Route::post('/{id}/devolucion', [PrestamoController::class, 'registrarDevolucion']);
        Route::post('/{id}/anular', [PrestamoController::class, 'anular']);
    });
    Route::apiResource('prestamos', PrestamoController::class)
        ->except(['index', 'show'])
        ->middleware(['caja.abierta', 'broadcast:prestamos']);

    // ============================================
    // CLIENTES
    // ============================================
    Route::get('clientes/estadisticas', [ClienteController::class, 'estadisticas']);
    Route::post('clientes/check-documento', [ClienteController::class, 'checkDocumento']);
    Route::apiResource('profesiones', ProfesionController::class)->only(['index', 'store'])->middleware('broadcast:profesiones');
    
    // Rutas de direcciones de clientes
    Route::get('clientes/{clienteId}/direcciones', [ClienteController::class, 'listarDirecciones']);
    Route::post('clientes/{clienteId}/direcciones', [ClienteController::class, 'crearDireccion']);
    Route::put('direcciones/{id}', [ClienteController::class, 'actualizarDireccion']);
    Route::delete('direcciones/{id}', [ClienteController::class, 'eliminarDireccion']);
    Route::post('direcciones/{id}/marcar-principal', [ClienteController::class, 'marcarDireccionPrincipal']);
    Route::get('clientes/{clienteId}/recomendaciones', [ClienteController::class, 'recomendaciones']);
    
    Route::apiResource('clientes', ClienteController::class)->middleware('broadcast:clientes');

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
    Route::get('clientes/calificaciones/resumen', [ClienteCalificacionController::class, 'resumen']);
    Route::post('clientes/{clienteId}/calificaciones', [ClienteCalificacionController::class, 'store'])->middleware('broadcast:cliente-calificaciones');
    Route::put('calificaciones/{calificacionId}', [ClienteCalificacionController::class, 'update'])->middleware('broadcast:cliente-calificaciones');
    Route::delete('calificaciones/{calificacionId}', [ClienteCalificacionController::class, 'destroy'])->middleware('broadcast:cliente-calificaciones');
    Route::get('calificaciones-cliente/estados', [ClienteCalificacionController::class, 'estados']);

    // ============================================
    // PROVEEDORES
    // ============================================
    Route::get('proveedores/ordenar-por-compras', [ProveedorController::class, 'getProveedoresOrdenadosPorCompras']);
    Route::get('proveedores/con-compras/ids', [ProveedorController::class, 'getProveedoresConCompras']);
    Route::get('proveedores/check-documento', [ProveedorController::class, 'checkDocumento']);
    Route::get('proveedores', [ProveedorController::class, 'index']);
    Route::post('proveedores', [ProveedorController::class, 'store']);
    Route::get('proveedores/{id}', [ProveedorController::class, 'show']);
    Route::put('proveedores/{id}', [ProveedorController::class, 'update']);
    Route::post('proveedores/{id}/carros', [ProveedorController::class, 'addCarro']);
    Route::post('proveedores/{id}/choferes', [ProveedorController::class, 'addChofer']);
    Route::delete('proveedores/{id}', [ProveedorController::class, 'destroy'])->middleware('broadcast:proveedores');

    // ============================================
    // CALIFICACIONES DE PROVEEDORES
    // ============================================
    Route::get('proveedores/{proveedorId}/calificaciones', [ProveedorCalificacionController::class, 'index']);
    Route::get('proveedores/{proveedorId}/calificaciones/ultima', [ProveedorCalificacionController::class, 'ultimaCalificacion']);
    Route::post('proveedores/{proveedorId}/calificaciones', [ProveedorCalificacionController::class, 'store'])->middleware('broadcast:proveedor-calificaciones');
    Route::put('calificaciones/{calificacionId}', [ProveedorCalificacionController::class, 'update'])->middleware('broadcast:proveedor-calificaciones');
    Route::delete('calificaciones/{calificacionId}', [ProveedorCalificacionController::class, 'destroy'])->middleware('broadcast:proveedor-calificaciones');
    Route::get('calificaciones/estados', [ProveedorCalificacionController::class, 'estados']);

    // ============================================
    // INGRESOS Y SALIDAS (Inventario)
    // ============================================
    Route::apiResource('ingresos-salidas', IngresoSalidaController::class)->middleware('broadcast:ingresos-salidas');

    // ============================================
    // TRANSFERENCIAS DE STOCK (entre almacenes)
    // ============================================
    Route::get('transferencias-stock-resumen-dashboard', [\App\Http\Controllers\TransferenciaStockController::class, 'resumenDashboard']);
    Route::apiResource('transferencias-stock', \App\Http\Controllers\TransferenciaStockController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->middleware('broadcast:transferencias-stock');

    // ============================================
    // RECEPCIONES DE ALMACÉN
    // ============================================
    Route::apiResource('recepciones-almacen', RecepcionAlmacenController::class)->only(['index', 'show', 'store', 'destroy'])->middleware('broadcast:recepciones-almacen');
    Route::post('recepciones-almacen/finalizar-compra/{compra_id}', [RecepcionAlmacenController::class, 'finalizarCompra'])->middleware('broadcast:recepciones-almacen');

    // ============================================
    // ENTREGAS DE PRODUCTOS (legacy) — SOLO crear/editar, migrados por dentro a
    // la tabla nueva. El resto del CRUD legacy (index/show/destroy/aceptar/anular)
    // y los eventos se eliminaron: el front usa la API nueva (entregas/*) para
    // listar / ver / anular / confirmar / eventos.
    // ============================================
    Route::apiResource('entregas-productos', EntregaProductoController::class)
        ->only(['store', 'update'])
        ->middleware('broadcast:entregas-productos');

    // ============================================
    // PAQUETES
    // ============================================
    Route::get('paquetes/by-producto/{productoId}', [PaqueteController::class, 'byProducto']);
    Route::apiResource('paquetes', PaqueteController::class);

    // ============================================
    // SERIES DE DOCUMENTOS
    // ============================================
    Route::get('series-documentos/siguiente-numero/preview', [SerieDocumentoController::class, 'siguienteNumero']);
    Route::apiResource('series-documentos', SerieDocumentoController::class)->middleware('broadcast:series-documentos');

    // ============================================
    // CHOFERES
    // ============================================
    Route::get('choferes/buscar-dni/{dni}', [ChoferController::class, 'buscarPorDni']);
    Route::apiResource('choferes', ChoferController::class)->middleware('broadcast:choferes');

    // ============================================
    // TRANSPORTISTAS (empresas de transporte terceras, GRE-Remitente PÚBLICO)
    // ============================================
    Route::get('transportistas', [TransportistaController::class, 'index']);
    Route::post('transportistas', [TransportistaController::class, 'store']);
    // Consulta del registro MTC por RUC (scraping del portal MTC, cacheado 24h).
    // Va ANTES de la ruta {id} para que 'consulta-mtc' no matchee como id.
    Route::get('transportistas/consulta-mtc/{ruc}', [TransportistaController::class, 'consultaMtc']);
    Route::put('transportistas/{id}', [TransportistaController::class, 'update']);

    // ============================================
    // NOTAS DE DÉBITO (Facturación Electrónica)
    // ============================================
    Route::prefix('notas-debito')->group(function () {
        Route::post('/', [\App\Http\Controllers\NotaDebitoController::class, 'store']);
        Route::post('/consultar-estado', [\App\Http\Controllers\NotaDebitoController::class, 'consultarEstado']);
        Route::get('/{serie}/{numero}/xml', [\App\Http\Controllers\NotaDebitoController::class, 'verXml']);
    });
});
