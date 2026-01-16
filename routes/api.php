<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DespliegueDePagoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ProvinciaController;
use App\Http\Controllers\DistritoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\DetallePreciosController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UnidadDerivadaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\EntregaProductoController;
use App\Http\Controllers\SerieDocumentoController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\ConfiguracionImpresionController;
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
    Route::apiResource('proveedores', ProveedorController::class);

    // DESPLIEGUE DE PAGO
    Route::apiResource('despliegues-de-pago', DespliegueDePagoController::class)->only(['index', 'show']);

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
