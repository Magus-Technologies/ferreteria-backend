<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\UnidadDerivadaController;
use App\Http\Controllers\TipoIngresoSalidaController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ProvinciaController;
use App\Http\Controllers\DistritoController;
use App\Http\Controllers\TipoServicioController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog Routes
|--------------------------------------------------------------------------
|
| Rutas de catálogos: almacenes, categorías, marcas, ubicaciones, unidades, ubigeo
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // CATÁLOGOS PRINCIPALES
    // ============================================
    Route::apiResource('almacenes', AlmacenController::class)->middleware('broadcast:almacenes');
    Route::patch('almacenes/{id}/toggle-activo', [AlmacenController::class, 'toggleActivo'])->middleware('broadcast:almacenes');
    Route::get('almacenes/{id}/estado-replicacion', [AlmacenController::class, 'estadoReplicacion']);
    Route::post('almacenes/{id}/replicar-a-todos', [AlmacenController::class, 'replicarProductos']);
    Route::apiResource('categorias', CategoriaController::class)->middleware('broadcast:categorias');
    Route::apiResource('marcas', MarcaController::class)->middleware('broadcast:marcas');
    Route::apiResource('ubicaciones', UbicacionController::class)->middleware('broadcast:ubicaciones');
    Route::apiResource('unidades-medida', UnidadMedidaController::class)->middleware('broadcast:unidades-medida');
    Route::apiResource('unidades-derivadas', UnidadDerivadaController::class)->middleware('broadcast:unidades-derivadas');
    Route::apiResource('tipos-ingreso-salida', TipoIngresoSalidaController::class)->middleware('broadcast:tipos-ingreso-salida');
    Route::apiResource('tipos-servicio', TipoServicioController::class)->middleware('broadcast:tipos-servicio');
    Route::apiResource('vehiculos', VehiculoController::class)->middleware('broadcast:vehiculos');

    // ============================================
    // UBIGEO (Departamentos, Provincias, Distritos)
    // ============================================
    Route::prefix('departamentos')->group(function () {
        Route::get('/', [DepartamentoController::class, 'index']);
        Route::get('/{id}', [DepartamentoController::class, 'show']);
        Route::get('/{codigo}/provincias', [DepartamentoController::class, 'provincias']);
    });

    Route::prefix('provincias')->group(function () {
        Route::get('/', [ProvinciaController::class, 'index']);
        Route::get('/{id}', [ProvinciaController::class, 'show']);
        Route::get('/{departamento}/{provincia}/distritos', [ProvinciaController::class, 'distritos']);
    });

    Route::prefix('distritos')->group(function () {
        Route::get('/', [DistritoController::class, 'index']);
        Route::get('/{id}', [DistritoController::class, 'show']);
    });
});
