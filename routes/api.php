<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConfiguracionImpresionController;
use App\Http\Controllers\ConfiguracionNotificacionController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\GuiaRemisionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QzTrayController;
use App\Http\Controllers\RestrictionController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\MotivoTrasladoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\CumpleanosController;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Archivo principal de rutas API. Las rutas están organizadas en módulos:
| - routes/api/auth.php - Autenticación y recuperación de contraseña
| - routes/api/catalogos.php - Catálogos (almacenes, categorías, marcas, etc.)
| - routes/api/productos.php - Productos (CRUD, importación, precios, archivos)
| - routes/api/ventas.php - Ventas, compras, cotizaciones, préstamos, clientes
| - routes/api/cajas.php - Cajas (apertura, cierre, transacciones, préstamos)
|
*/

// ============================================
// RUTAS PÚBLICAS
// ============================================

// Autenticación y recuperación de contraseña (importado desde auth.php)
require __DIR__ . '/api/auth.php';

// ============================================
// CATÁLOGOS PÚBLICOS
// ============================================
// Estos endpoints están disponibles sin autenticación para formularios
Route::prefix('catalogos')->group(function () {
    Route::get('/estados-civiles', [CatalogoController::class, 'estadosCiviles']);
    Route::get('/tipos-documento', [CatalogoController::class, 'tiposDocumento']);
    Route::get('/generos', [CatalogoController::class, 'generos']);
    Route::get('/roles-sistema', [CatalogoController::class, 'rolesSistema']);
    Route::get('/cargos', [CatalogoController::class, 'cargos']);
});
Route::get('/roles', [CatalogoController::class, 'roles']);

// Datos públicos de la empresa (para PDFs)

// ============================================
// QZ TRAY - CERTIFICADOS Y FIRMA DIGITAL
// ============================================
// Endpoints públicos para QZ Tray (certificado y firma)
Route::prefix('qz')->group(function () {
    Route::get('/certificate', [QzTrayController::class, 'getCertificate']);
    Route::post('/sign', [QzTrayController::class, 'signRequest']);
    Route::get('/status', [QzTrayController::class, 'status']); // Para diagnóstico
});
Route::get('/empresa/datos-publicos', [
    EmpresaController::class,
    'getDatosPublicos',
]);

// Rutas públicas para ver detalles (necesarias para PDFs compartibles)
Route::get('cotizaciones/{id}', [
    App\Http\Controllers\CotizacionController::class,
    'show',
]);
Route::get('prestamos/{id}', [
    App\Http\Controllers\PrestamoController::class,
    'show',
]);

// ============================================
// PDF (público - para compartir con clientes)
// ============================================
Route::prefix('pdf')->group(function () {
    Route::get('/venta/{id}', [PdfController::class, 'venta']);
    Route::get('/compra/{id}', [PdfController::class, 'compra']);
    Route::get('/cotizacion/{id}', [PdfController::class, 'cotizacion']);
    Route::get('/prestamo/{id}', [PdfController::class, 'prestamo']);
    Route::get('/guia/{id}', [PdfController::class, 'guia']);
    Route::get('/vale/{id}', [PdfController::class, 'vale']);
});

// Ruta pública para ver XML de comprobantes electrónicos (sin autenticación)
Route::get('facturas/comprobante/{comprobanteId}/xml', [
    App\Http\Controllers\FacturacionElectronica\FacturaController::class,
    'verXmlPorComprobante',
]);

// ============================================
// RUTAS PROTEGIDAS (Sanctum)
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // MÓDULOS IMPORTADOS
    // ============================================
    require __DIR__ . '/api/catalogos.php';    // Catálogos (almacenes, categorías, marcas, ubicaciones, unidades, ubigeo)
    require __DIR__ . '/api/productos.php';    // Productos (CRUD, importación, archivos, precios)
    require __DIR__ . '/api/ventas.php';       // Ventas, compras, cotizaciones, préstamos, clientes, proveedores
    require __DIR__ . '/api/cajas.php';        // Cajas (apertura, cierre, transacciones, préstamos)
    require __DIR__ . '/api/facturacion-electronica.php';  // Facturación electrónica (notas de débito, crédito, facturas)
    require __DIR__ . '/api/ganancias.php';    // Gestión contable y financiera - Mis Ganancias
    require __DIR__ . '/api/ingresos.php';     // Gestión contable y financiera - Mis Ingresos
    require __DIR__ . '/api/gastos.php';       // Gestión contable y financiera - Mis Gastos
    require __DIR__ . '/api/servicios.php';    // Servicios (catálogo de servicios para ventas)
    require __DIR__ . '/api/ordenes-compra.php'; // Requerimientos internos y Órdenes de compra

    // ============================================
    // USUARIOS
    // ============================================
    Route::prefix('usuarios')->group(function () {
        Route::get('/supervisores', [UsuarioController::class, 'getSupervisores']);
        Route::get('/vendedores-disponibles', [UsuarioController::class, 'vendedoresDisponibles']);
        Route::post('/fcm-token', [NotificacionController::class, 'updateFcmToken']);
    });
    Route::apiResource('usuarios', UsuarioController::class);

    // ============================================
    // NOTIFICACIONES PUSH (Firebase)
    // ============================================
    Route::prefix('notificaciones')->group(function () {
        Route::post('/send', [NotificacionController::class, 'sendNotification']);
        Route::post('/entrega-programada', [NotificacionController::class, 'notifyEntregaProgramada']);
        Route::post('/test', [NotificacionController::class, 'testNotification']); // Endpoint de prueba
    });

    // ============================================
    // CUMPLEAÑOS
    // ============================================
    Route::get('/cumpleanos/proximos', [CumpleanosController::class, 'proximos']);

    // ============================================
    // CONFIGURACIÓN DE NOTIFICACIONES
    // ============================================
    Route::prefix('configuracion-notificaciones')->group(function () {
        Route::get('/', [ConfiguracionNotificacionController::class, 'index']);
        Route::post('/', [ConfiguracionNotificacionController::class, 'store']);
        Route::get('/cumpleanos', [ConfiguracionNotificacionController::class, 'getCumpleanos']);
    });

    // ============================================
    // EMPRESA
    // ============================================
    Route::apiResource('empresas', EmpresaController::class);

    // ============================================
    // CONFIGURACIÓN DE IMPRESIÓN
    // ============================================
    Route::prefix('configuracion-impresion')->group(function () {
        Route::get('/{tipo_documento}', [ConfiguracionImpresionController::class, 'index']);
        Route::get('/{tipo_documento}/{campo}', [ConfiguracionImpresionController::class, 'show']);
        Route::put('/{tipo_documento}/{campo}', [ConfiguracionImpresionController::class, 'update']);
        Route::put('/{tipo_documento}', [ConfiguracionImpresionController::class, 'updateMultiple']);
        Route::post('/{tipo_documento}/{campo}/reset', [ConfiguracionImpresionController::class, 'resetCampo']);
        Route::post('/{tipo_documento}/reset-all', [ConfiguracionImpresionController::class, 'resetAll']);
    });

    // ============================================
    // MOTIVOS DE TRASLADO (Catálogo N° 20 SUNAT)
    // ============================================
    Route::apiResource('motivos-traslado', MotivoTrasladoController::class);

    // ============================================
    // GUÍAS DE REMISIÓN
    // ============================================
    Route::prefix('guias-remision')->group(function () {
        Route::post('/{id}/emitir', [GuiaRemisionController::class, 'emitir']);
        Route::post('/{id}/anular', [GuiaRemisionController::class, 'anular']);
        Route::post('/{id}/enviar-sunat', [GuiaRemisionController::class, 'enviarSunat']);
        Route::get('/{id}/pdf-data', [GuiaRemisionController::class, 'getPdfData']);
    });
    Route::apiResource('guias-remision', GuiaRemisionController::class);

    // ============================================
    // VALES DE COMPRA (PROMOCIONES)
    // ============================================
    Route::prefix('vales-compra')->group(function () {
        Route::post('/vales-aplicables', [\App\Http\Controllers\ValeCompraController::class, 'valesAplicables']);
        Route::post('/verificar-codigo', [\App\Http\Controllers\ValeCompraController::class, 'verificarCodigoVale']);
        Route::post('/{id}/cambiar-estado', [\App\Http\Controllers\ValeCompraController::class, 'cambiarEstado']);
        Route::get('/{id}/historial-aplicaciones', [\App\Http\Controllers\ValeCompraController::class, 'historialAplicaciones']);
        Route::get('/venta/{ventaId}/vales-aplicados', [\App\Http\Controllers\ValeCompraController::class, 'valesAplicadosVenta']);
    });
    Route::apiResource('vales-compra', \App\Http\Controllers\ValeCompraController::class);

    // ============================================
    // ROLES (sin permisos/restricciones por ahora)
    // ============================================
    Route::prefix('roles')->group(function () {
        Route::get('/', [RestrictionController::class, 'roles']);
        Route::get('/{roleId}', [RestrictionController::class, 'getRole']);
    });

    // ==================== SISTEMA NUEVO: Restricciones (lista negra) ====================
    Route::prefix('restrictions')->group(function () {
        // Listar restricciones
        Route::get('/', [RestrictionController::class, 'index']);

        // Gestión de roles con restricciones
        Route::prefix('roles')->group(function () {
            Route::get('/', [RestrictionController::class, 'roles']);
            Route::get('/{roleId}', [RestrictionController::class, 'getRole']);
            Route::post('/{roleId}/restrictions', [RestrictionController::class, 'assignRestrictionsToRole']);
            Route::post('/{roleId}/toggle', [RestrictionController::class, 'toggleRestrictionForRole']);
        });

        // Verificar acceso de usuarios
        Route::prefix('users')->group(function () {
            Route::post('/{userId}/check-access', [RestrictionController::class, 'checkAccess']);
        });
    });

    // ============================================
    // PERMISOS Y ROLES (Sistema antiguo - mantener por compatibilidad)
    // ============================================
    Route::prefix('permissions')->group(function () {
        // Listar permisos
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/grouped', [PermissionController::class, 'groupedPermissions']);
        Route::get('/stats', [PermissionController::class, 'stats']);

        // Gestión de roles
        Route::prefix('roles')->group(function () {
            Route::get('/', [PermissionController::class, 'roles']);
            Route::post('/', [PermissionController::class, 'createRole']);
            Route::get('/{roleId}', [PermissionController::class, 'getRole']);
            Route::put('/{roleId}', [PermissionController::class, 'updateRole']);
            Route::delete('/{roleId}', [PermissionController::class, 'deleteRole']);
            Route::post('/{roleId}/permissions', [PermissionController::class, 'assignToRole']);
        });

        // Gestión de permisos de usuarios
        Route::prefix('users')->group(function () {
            Route::get('/', [PermissionController::class, 'users']);
            Route::get('/{userId}', [PermissionController::class, 'userPermissions']);
            Route::post('/{userId}/permissions', [PermissionController::class, 'assignToUser']);
            Route::post('/{userId}/roles', [PermissionController::class, 'assignRoleToUser']);
            Route::post('/{userId}/check', [PermissionController::class, 'checkPermission']);
        });
    });
});
