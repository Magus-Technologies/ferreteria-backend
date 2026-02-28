<?php

use App\Http\Controllers\CajaController;
use App\Http\Controllers\DespliegueDePagoController;
use App\Http\Controllers\Cajas\AperturaCajaController;
use App\Http\Controllers\Cajas\CajaPrincipalController;
use App\Http\Controllers\Cajas\CierreCajaController;
use App\Http\Controllers\Cajas\DesplieguePagoController;
use App\Http\Controllers\Cajas\MovimientoInternoController;
use App\Http\Controllers\Cajas\PrestamoEntreCajasController;
use App\Http\Controllers\Cajas\SubCajaController;
use App\Http\Controllers\Cajas\TransaccionController;
use App\Http\Controllers\Cajas\DeudaPersonalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cash Register (Cajas) Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de cajas: apertura, cierre, transacciones, préstamos
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // CAJA LEGACY (Compatibilidad)
    // ============================================
    Route::prefix('cajas')->group(function () {
        Route::get('/consulta-apertura', [CajaController::class, 'consultaApertura']);
        Route::post('/aperturar', [CajaController::class, 'aperturar']);
        Route::post('/{id}/cerrar', [CajaController::class, 'cerrar']);
        Route::get('/activa', [CajaController::class, 'cajaActiva']);
        Route::get('/historial', [CajaController::class, 'historial']);
    });

    // ============================================
    // DESPLIEGUE DE PAGO (Legacy)
    // ============================================
    Route::apiResource('despliegues-de-pago', DespliegueDePagoController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    // ============================================
    // MÉTODOS DE PAGO (Alias para compatibilidad)
    // ============================================
    Route::prefix('metodos-de-pago')->group(function () {
        Route::get('/agrupados-por-banco', [\App\Http\Controllers\MetodoDePagoController::class, 'agrupadosPorBanco']);
        Route::get('/{id}/resumen-detallado', [\App\Http\Controllers\MetodoDePagoController::class, 'getResumenDetallado']);
        Route::get('/', [\App\Http\Controllers\MetodoDePagoController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\MetodoDePagoController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\MetodoDePagoController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\MetodoDePagoController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\MetodoDePagoController::class, 'destroy']);
    });

    // ============================================
    // MÓDULO DE CAJAS REFACTORIZADO
    // ============================================
    Route::prefix('cajas')->group(function () {

        // Métodos de Pago
        Route::get('/metodos-pago', [DesplieguePagoController::class, 'index']);
        Route::get('/metodos-pago/mostrar', [DesplieguePagoController::class, 'mostrar']);

        // Cajas Principales
        Route::prefix('cajas-principales')->group(function () {
            Route::get('/', [CajaPrincipalController::class, 'index']);
            Route::post('/', [CajaPrincipalController::class, 'store']);
            Route::get('/usuario/actual', [CajaPrincipalController::class, 'getByUser']);
            Route::get('/{id}', [CajaPrincipalController::class, 'show']);
            Route::delete('/{id}', [CajaPrincipalController::class, 'destroy']);
            Route::get('/{cajaPrincipalId}/sub-cajas', [SubCajaController::class, 'index']);
            Route::get('/{cajaPrincipalId}/sub-cajas/con-saldo-vendedor', [SubCajaController::class, 'getConSaldoVendedor']);
            Route::get('/{cajaPrincipalId}/transacciones', [TransaccionController::class, 'indexByCajaPrincipal']);
        });

        // Sub-Cajas
        Route::prefix('sub-cajas')->group(function () {
            Route::post('/', [SubCajaController::class, 'store']);
            Route::get('/metodos-para-ventas', [SubCajaController::class, 'metodosParaVentas']);
            Route::get('/todas-con-saldo-vendedor', [SubCajaController::class, 'getTodasConSaldoVendedor']);
            Route::get('/todas-con-saldo-efectivo', [SubCajaController::class, 'getTodasConSaldoEfectivo']);
            Route::get('/vendedores-con-efectivo', [SubCajaController::class, 'getVendedoresConEfectivo']);
            Route::get('/buscar-por-despliegue/{desplieguePagoId}', [SubCajaController::class, 'buscarPorDesplieguePago']);
            Route::get('/{id}', [SubCajaController::class, 'show']);
            Route::put('/{id}', [SubCajaController::class, 'update']);
            Route::delete('/{id}', [SubCajaController::class, 'destroy']);
            Route::get('/{subCajaId}/transacciones', [TransaccionController::class, 'index']);
        });

        // Transacciones
        Route::prefix('transacciones')->middleware('caja.abierta')->group(function () {
            Route::post('/', [TransaccionController::class, 'store']);
            Route::get('/{id}', [TransaccionController::class, 'show']);
        });

        // Apertura y Cierre
        Route::post('/aperturar', [AperturaCajaController::class, 'aperturar']);
        Route::get('/consulta-apertura/{cajaPrincipalId}', [AperturaCajaController::class, 'consultaApertura']);
        Route::get('/historial-aperturas', [AperturaCajaController::class, 'historial']);
        Route::get('/historial-aperturas/todas', [AperturaCajaController::class, 'historialTodas']);
        Route::post('/apertura/{id}/enviar-email', [AperturaCajaController::class, 'enviarTicketEmail']); // Enviar ticket de apertura por correo
        Route::get('/apertura/{id}/test-email', [AperturaCajaController::class, 'testEnviarEmail']); // TEST: Enviar email sin PDF

        // Cierre de Caja
        Route::prefix('cierre')->group(function () {
            Route::get('/activa', [CierreCajaController::class, 'obtenerCajaActiva']);
            Route::get('/diagnostico-distribuciones', [CierreCajaController::class, 'diagnosticoDistribuciones']); // Diagnóstico temporal
            Route::get('/arqueos', [CierreCajaController::class, 'listarArqueos']); // Listar arqueos diarios
            Route::post('/{id}/cerrar', [CierreCajaController::class, 'cerrarCaja']); // Re-add /cerrar specifically to match frontend URL
            Route::post('/{id}/re-cerrar', [CierreCajaController::class, 'reCerrarCaja']); // Re-Cerrar
            Route::post('/{id}/recalcular-cierre', [CierreCajaController::class, 'recalcularCierre']);
            Route::put('/{id}/actualizar-cierre', [CierreCajaController::class, 'actualizarCierre']);
            // Route::get('/{id}/resumen-movimientos', [CierreCajaController::class, 'obtenerResumenMovimientos']); // TODO: Implementar
            Route::get('/{id}/detalle-movimientos', [CierreCajaController::class, 'obtenerDetalleMovimientos']);
            Route::get('/{id}/movimientos', [CierreCajaController::class, 'obtenerDetalleMovimientos']);
            Route::post('/validar-supervisor', [CierreCajaController::class, 'validarSupervisor']);
            Route::post('/{id}/enviar-email', [CierreCajaController::class, 'enviarTicketEmail']); // Enviar ticket por correo
            Route::post('/{id}/aprobar', [CierreCajaController::class, 'aprobarCierre']); // Aprobar cierre pendiente
            Route::get('/{id}', [CierreCajaController::class, 'obtenerCierre']); // Nuevo endpoint
            Route::get('/{id}/cierre', [CierreCajaController::class, 'obtenerCierre']); // Alias legacy
        });

        // Préstamos entre Cajas
        Route::prefix('prestamos')->middleware('caja.abierta')->group(function () {
            Route::get('/', [PrestamoEntreCajasController::class, 'index']);
            Route::get('/pendientes', [PrestamoEntreCajasController::class, 'pendientes']);
            Route::post('/', [PrestamoEntreCajasController::class, 'store']);
            Route::post('/{id}/aprobar', [PrestamoEntreCajasController::class, 'aprobar']);
            Route::post('/{id}/rechazar', [PrestamoEntreCajasController::class, 'rechazar']);
            Route::post('/{id}/devolver', [PrestamoEntreCajasController::class, 'devolver']);
        });

        // Movimientos Internos
        Route::prefix('movimientos-internos')->middleware('caja.abierta')->group(function () {
            Route::get('/', [MovimientoInternoController::class, 'index']);
            Route::get('/depositos-seguridad', [MovimientoInternoController::class, 'depositosSeguridad']);
            Route::post('/', [MovimientoInternoController::class, 'store']);
        });

        // Préstamos entre Vendedores
        Route::prefix('prestamos-vendedores')->group(function () {
            // Consultas (NO requieren caja abierta)
            Route::get('/', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'listarSolicitudes']);
            Route::get('/pendientes', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'solicitudesPendientes']);
            Route::get('/transferencias', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'listarTransferencias']);

            // Crear solicitud NO requiere caja abierta (solo necesita tener distribución)
            Route::post('/', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'crearSolicitud']);

            // Rechazar NO requiere caja abierta (solo es cambiar estado)
            Route::post('/{id}/rechazar', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'rechazarSolicitud']);

            // Aprobar SÍ requiere caja abierta (involucra transferencia de dinero)
            Route::middleware('caja.abierta')->group(function () {
                Route::post('/{id}/aprobar', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'aprobarSolicitud']);
            });
        });

        // Vendedores con efectivo
        Route::get('/vendedores/con-efectivo', [\App\Http\Controllers\Cajas\PrestamoVendedorController::class, 'vendedoresConEfectivo']);

        // Deudas de Personal
        Route::prefix('deudas-personal')->group(function () {
            Route::get('/', [DeudaPersonalController::class, 'index']);
            Route::get('/resumen', [DeudaPersonalController::class, 'resumen']);
            Route::post('/abono', [DeudaPersonalController::class, 'registrarAbono']);
            Route::get('/{id}/historial', [DeudaPersonalController::class, 'historialAbonos']);
            Route::post('/{id}/pagar', [DeudaPersonalController::class, 'pagar']); // Mantener para compatibilidad
        });
    });
});
