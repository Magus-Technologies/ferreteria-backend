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
    Route::apiResource('almacenes', AlmacenController::class);
    Route::apiResource('categorias', CategoriaController::class);
    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('ubicaciones', UbicacionController::class);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);
    Route::apiResource('unidades-derivadas', UnidadDerivadaController::class);
    Route::apiResource('tipos-ingreso-salida', TipoIngresoSalidaController::class);

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
