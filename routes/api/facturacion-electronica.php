<?php

use App\Http\Controllers\FacturacionElectronica\NotaDebitoController;
use App\Http\Controllers\FacturacionElectronica\NotaCreditoController;
use App\Http\Controllers\FacturacionElectronica\FacturaController;
use App\Http\Controllers\FacturacionElectronica\MotivoNotaController;
use App\Models\ComprobanteElectronico;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Facturación Electrónica API Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de facturación electrónica:
| - Notas de Débito 
| - Notas de Crédito 
| - Facturas
| - Guías de Remisión (futuro)
|
*/

Route::middleware(['auth:sanctum'])->prefix('facturacion-electronica')->group(function () {
    
    // ========== MOTIVOS DE NOTA ==========
    Route::prefix('motivos-nota')->group(function () {
        Route::get('/', [MotivoNotaController::class, 'index']);
        Route::get('/debito', [MotivoNotaController::class, 'motivosDebito']);
        Route::get('/credito', [MotivoNotaController::class, 'motivosCredito']);
        Route::get('/{id}', [MotivoNotaController::class, 'show']);
    });

    // ========== NOTAS DE DÉBITO ==========
    Route::prefix('notas-debito')->group(function () {
        // CRUD básico
        Route::get('/', [NotaDebitoController::class, 'index']);
        Route::post('/', [NotaDebitoController::class, 'store']);
        Route::get('/{id}', [NotaDebitoController::class, 'show']);
        Route::put('/{id}', [NotaDebitoController::class, 'update']);
        Route::post('/{id}/cancelar', [NotaDebitoController::class, 'cancelar']);

        // Operaciones SUNAT
        Route::post('/{id}/enviar-sunat', [NotaDebitoController::class, 'enviarSunat']);
        Route::get('/{id}/estado-sunat', [NotaDebitoController::class, 'consultarEstadoSunat']);

        // Archivos XML y CDR
        Route::get('/{id}/xml', [NotaDebitoController::class, 'verXml']);
        Route::get('/{id}/cdr', [NotaDebitoController::class, 'descargarCdr']);

        // Consultas específicas
        Route::get('/venta/{ventaId}', [NotaDebitoController::class, 'porVenta']);
        Route::get('/validar-venta/{ventaId}', [NotaDebitoController::class, 'validarVenta']);
    });

    // ========== NOTAS DE CRÉDITO ==========
    Route::prefix('notas-credito')->group(function () {
        // CRUD básico
        Route::get('/', [NotaCreditoController::class, 'index']);
        Route::post('/', [NotaCreditoController::class, 'store']);
        Route::get('/{id}', [NotaCreditoController::class, 'show']);
        Route::put('/{id}', [NotaCreditoController::class, 'update']);
        Route::post('/{id}/cancelar', [NotaCreditoController::class, 'cancelar']);

        // Operaciones SUNAT
        Route::post('/{id}/enviar-sunat', [NotaCreditoController::class, 'enviarSunat']);
        Route::get('/{id}/estado-sunat', [NotaCreditoController::class, 'consultarEstadoSunat']);

        // Archivos XML y CDR
        Route::get('/{id}/xml', [NotaCreditoController::class, 'verXml']);
        Route::get('/{id}/cdr', [NotaCreditoController::class, 'descargarCdr']);

        // Consultas específicas
        Route::get('/venta/{ventaId}', [NotaCreditoController::class, 'porVenta']);
        Route::get('/validar-venta/{ventaId}', [NotaCreditoController::class, 'validarVenta']);
    });

    // ========== FACTURAS Y BOLETAS ==========
    Route::prefix('facturas')->group(function () {
        Route::get('/', [FacturaController::class, 'index']);
        Route::post('/generar', [FacturaController::class, 'generarComprobante']);
        Route::get('/{ventaId}', [FacturaController::class, 'show']);
        Route::post('/{ventaId}/enviar-sunat', [FacturaController::class, 'enviarSunat']);
        Route::get('/{ventaId}/estado-sunat', [FacturaController::class, 'consultarEstadoSunat']);
        Route::get('/{ventaId}/xml', [FacturaController::class, 'verXml']);
        Route::get('/{ventaId}/cdr', [FacturaController::class, 'descargarCdr']);
        Route::get('/validar-venta/{ventaId}', [FacturaController::class, 'validarVenta']);
    });
});
