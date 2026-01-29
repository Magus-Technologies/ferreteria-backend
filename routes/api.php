<?php

use App\Http\Controllers\ConfiguracionImpresionController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UsuarioController;
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

// Datos públicos de la empresa (para PDFs)
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

    // ============================================
    // USUARIOS
    // ============================================
    Route::prefix('usuarios')->group(function () {
        Route::get('/vendedores-disponibles', [UsuarioController::class, 'vendedoresDisponibles']);
    });
    Route::apiResource('usuarios', UsuarioController::class);

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
    // PERMISOS Y ROLES
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
