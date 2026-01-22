<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConfiguracionImpresionController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DespliegueDePagoController;
use App\Http\Controllers\DetallePreciosController;
use App\Http\Controllers\DistritoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EntregaProductoController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ProvinciaController;
use App\Http\Controllers\SerieDocumentoController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UnidadDerivadaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/auth/login', [AuthController::class, 'login']);

// Recuperación de contraseña
Route::post('/password/send-code', [App\Http\Controllers\PasswordResetController::class, 'sendCode']);
Route::post('/password/verify-code', [App\Http\Controllers\PasswordResetController::class, 'verifyCode']);
Route::post('/password/reset', [App\Http\Controllers\PasswordResetController::class, 'resetPassword']);

// Datos públicos de la empresa (para PDFs)
Route::get('/empresa/datos-publicos', [EmpresaController::class, 'getDatosPublicos']);

// Rutas públicas para ver detalles (necesarias para PDFs compartibles)
Route::get('cotizaciones/{id}', [CotizacionController::class, 'show']);
Route::get('prestamos/{id}', [PrestamoController::class, 'show']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

    // Recursos principales
    Route::apiResource('almacenes', AlmacenController::class);
    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('categorias', CategoriaController::class);
    Route::apiResource('ubicaciones', UbicacionController::class);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);
    Route::apiResource('unidades-derivadas', UnidadDerivadaController::class);

    // Rutas específicas de productos (antes de apiResource)
    Route::get('productos/validar-codigo', [ProductoController::class, 'validarCodigo']);
    Route::get('productos/{id}/detalle-precios', [ProductoController::class, 'detallePrecios']);
    Route::post('productos/import', [ProductoController::class, 'import']);
    Route::post('productos/upload-files-masivo', [ProductoController::class, 'uploadFilesMasivo']);
    Route::post('productos/{id}/upload-files', [ProductoController::class, 'uploadFiles']);

    Route::apiResource('productos', ProductoController::class);

    // Rutas de Detalle de Precios (Unidades Derivadas)
    Route::post('detalle-precios/import', [DetallePreciosController::class, 'import']);
    Route::post('detalle-precios/get-producto-almacen', [DetallePreciosController::class, 'getProductoAlmacenByCodProducto']);
    Route::post('detalle-precios/importar-unidades-derivadas', [DetallePreciosController::class, 'importarUnidadesDerivadas']);
    Route::apiResource('ingresos-salidas', IngresoSalidaController::class);

    // VENTAS
    Route::apiResource('ventas', VentaController::class);

    // PAQUETES
    Route::apiResource('paquetes', PaqueteController::class);

    // ENTREGAS DE PRODUCTOS
    Route::apiResource('entregas-productos', EntregaProductoController::class);

    // SERIES DE DOCUMENTOS
    Route::get('series-documentos/siguiente-numero/preview', [SerieDocumentoController::class, 'siguienteNumero']);
    Route::apiResource('series-documentos', SerieDocumentoController::class);

    // COMPRAS
    Route::apiResource('compras', CompraController::class);

    // COTIZACIONES
    Route::get('cotizaciones/siguiente-numero/preview', [CotizacionController::class, 'siguienteNumero']);
    Route::post('cotizaciones/{id}/convertir-a-venta', [CotizacionController::class, 'convertirAVenta']);
    Route::apiResource('cotizaciones', CotizacionController::class);

    // PRÉSTAMOS
    Route::get('prestamos/siguiente-numero/preview', [PrestamoController::class, 'siguienteNumero']);
    Route::get('prestamos/{id}/pagos', [PrestamoController::class, 'listarPagos']);
    Route::post('prestamos/{id}/pagos', [PrestamoController::class, 'registrarPago']);
    Route::delete('prestamos/{prestamo_id}/pagos/{pago_id}', [PrestamoController::class, 'eliminarPago']);
    Route::apiResource('prestamos', PrestamoController::class);

    // USURIOS
    Route::get('usuarios/vendedores-disponibles', [UsuarioController::class, 'vendedoresDisponibles']);
    Route::apiResource('usuarios', UsuarioController::class);

    // EMPRESA
    Route::apiResource('empresas', EmpresaController::class);

    // UBIGEO - Departamentos, Provincias y Distritos
    Route::get('departamentos', [DepartamentoController::class, 'index']);
    Route::get('departamentos/{id}', [DepartamentoController::class, 'show']);
    Route::get('departamentos/{codigo}/provincias', [DepartamentoController::class, 'provincias']);

    Route::get('provincias', [ProvinciaController::class, 'index']);
    Route::get('provincias/{id}', [ProvinciaController::class, 'show']);
    Route::get('provincias/{departamento}/{provincia}/distritos', [ProvinciaController::class, 'distritos']);

    Route::get('distritos', [DistritoController::class, 'index']);
    Route::get('distritos/{id}', [DistritoController::class, 'show']);

    // CLIENTES
    Route::post('clientes/check-documento', [ClienteController::class, 'checkDocumento']);
    Route::apiResource('clientes', ClienteController::class);

    // CHOFERES
    Route::get('choferes/buscar-dni/{dni}', [App\Http\Controllers\ChoferController::class, 'buscarPorDni']);
    Route::apiResource('choferes', App\Http\Controllers\ChoferController::class);

    // PROVEEDORES
    Route::get('proveedores/check-documento', [ProveedorController::class, 'checkDocumento']);
    Route::apiResource('proveedores', ProveedorController::class);

    // DESPLIEGUE DE PAGO
    Route::apiResource('despliegues-de-pago', DespliegueDePagoController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

    // CAJA - Apertura y Cierre
    Route::get('cajas/consulta-apertura', [CajaController::class, 'consultaApertura']);
    Route::post('cajas/aperturar', [CajaController::class, 'aperturar']);
    Route::post('cajas/{id}/cerrar', [CajaController::class, 'cerrar']);
    Route::get('cajas/activa', [CajaController::class, 'cajaActiva']);
    Route::get('cajas/historial', [CajaController::class, 'historial']);

    // CONFIGURACIÓN DE IMPRESIÓN
    Route::get('configuracion-impresion/{tipo_documento}', [ConfiguracionImpresionController::class, 'index']);
    Route::get('configuracion-impresion/{tipo_documento}/{campo}', [ConfiguracionImpresionController::class, 'show']);
    Route::put('configuracion-impresion/{tipo_documento}/{campo}', [ConfiguracionImpresionController::class, 'update']);
    Route::put('configuracion-impresion/{tipo_documento}', [ConfiguracionImpresionController::class, 'updateMultiple']);
    Route::post('configuracion-impresion/{tipo_documento}/{campo}/reset', [ConfiguracionImpresionController::class, 'resetCampo']);
    Route::post('configuracion-impresion/{tipo_documento}/reset-all', [ConfiguracionImpresionController::class, 'resetAll']);
});

// ============================================
// RUTAS DEL MÓDULO DE CAJAS (PROTEGIDAS CON AUTENTICACIÓN)
// ============================================
use App\Http\Controllers\Cajas\AperturaCajaController;
use App\Http\Controllers\Cajas\CajaPrincipalController;
use App\Http\Controllers\Cajas\CierreCajaController;
use App\Http\Controllers\Cajas\DesplieguePagoController;
use App\Http\Controllers\Cajas\MovimientoInternoController;
use App\Http\Controllers\Cajas\PrestamoEntreCajasController;
use App\Http\Controllers\Cajas\SubCajaController;
use App\Http\Controllers\Cajas\TransaccionController;

Route::middleware('auth:sanctum')->prefix('cajas')->group(function () {

    // Métodos de Pago (Despliegue de Pago)
    Route::get('/metodos-pago', [DesplieguePagoController::class, 'index']);
    Route::get('/metodos-pago/mostrar', [DesplieguePagoController::class, 'mostrar']);

    // Cajas Principales
    Route::get('/cajas-principales', [CajaPrincipalController::class, 'index']);
    Route::post('/cajas-principales', [CajaPrincipalController::class, 'store']);
    Route::get('/cajas-principales/{id}', [CajaPrincipalController::class, 'show']);
    Route::get('/cajas-principales/usuario/actual', [CajaPrincipalController::class, 'getByUser']);
    Route::delete('/cajas-principales/{id}', [CajaPrincipalController::class, 'destroy']);

    // Sub-Cajas
    Route::get('/cajas-principales/{cajaPrincipalId}/sub-cajas', [SubCajaController::class, 'index']);
    Route::post('/sub-cajas', [SubCajaController::class, 'store']);
    Route::get('/sub-cajas/{id}', [SubCajaController::class, 'show']);
    Route::put('/sub-cajas/{id}', [SubCajaController::class, 'update']);
    Route::delete('/sub-cajas/{id}', [SubCajaController::class, 'destroy']);

    // Transacciones
    Route::get('/sub-cajas/{subCajaId}/transacciones', [TransaccionController::class, 'index']);
    Route::get('/cajas-principales/{cajaPrincipalId}/transacciones', [TransaccionController::class, 'indexByCajaPrincipal']);
    Route::post('/transacciones', [TransaccionController::class, 'store']);
    Route::get('/transacciones/{id}', [TransaccionController::class, 'show']);

    // Apertura y Cierre de Caja
    Route::post('/aperturar', [AperturaCajaController::class, 'aperturar']);
    Route::get('/consulta-apertura/{cajaPrincipalId}', [AperturaCajaController::class, 'consultaApertura']);
    Route::get('/historial-aperturas', [AperturaCajaController::class, 'historial']);
    Route::get('/historial-aperturas/todas', [AperturaCajaController::class, 'historialTodas']);
    
    // Cierre de Caja - Nuevos Endpoints
    Route::get('/activa', [CierreCajaController::class, 'obtenerCajaActiva']);
    Route::post('/{id}/cerrar', [CierreCajaController::class, 'cerrarCaja']);
    Route::get('/{id}/resumen-movimientos', [CierreCajaController::class, 'obtenerResumenMovimientos']);
    Route::get('/{id}/detalle-movimientos', [CierreCajaController::class, 'obtenerDetalleMovimientos']);
    Route::post('/validar-supervisor', [CierreCajaController::class, 'validarSupervisor']);
    
    // Préstamos entre Cajas (con sistema de aprobación)
    Route::get('/prestamos', [PrestamoEntreCajasController::class, 'index']);
    Route::get('/prestamos/pendientes', [PrestamoEntreCajasController::class, 'pendientes']);
    Route::post('/prestamos', [PrestamoEntreCajasController::class, 'store']);
    Route::post('/prestamos/{id}/aprobar', [PrestamoEntreCajasController::class, 'aprobar']);
    Route::post('/prestamos/{id}/rechazar', [PrestamoEntreCajasController::class, 'rechazar']);
    Route::post('/prestamos/{id}/devolver', [PrestamoEntreCajasController::class, 'devolver']);
    
    // Movimientos Internos
    Route::get('/movimientos-internos', [MovimientoInternoController::class, 'index']);
    Route::post('/movimientos-internos', [MovimientoInternoController::class, 'store']);
});
