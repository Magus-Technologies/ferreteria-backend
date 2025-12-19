<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DetallePreciosController;
use App\Http\Controllers\IngresoSalidaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UnidadDerivadaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/auth/login', [AuthController::class, 'login']);

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
    Route::apiResource('ventas', VentaController::class);
    Route::apiResource('compras', CompraController::class);


    // COTIZACIONES
    Route::apiResource('cotizaciones', CotizacionController::class);


    // TODO: Agregar más rutas según necesidad
    // Route::apiResource('clientes', ClienteController::class);
    // Route::apiResource('proveedores', ProveedorController::class);
    // etc.
});
