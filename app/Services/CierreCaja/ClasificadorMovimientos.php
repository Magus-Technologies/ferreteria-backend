<?php

namespace App\Services\CierreCaja;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClasificadorMovimientos
{
    public function clasificar(Collection $movimientos, Collection $ventas): array
    {
        return [
            'ingresos' => $movimientos->where('tipo', 'ingreso'),
            'egresos' => $movimientos->where('tipo', 'egreso'),
            'ventas' => $ventas,
            'metodosPago' => $this->agruparPorMetodoPago($ventas)
        ];
    }

    /**
     * Consolidar cierre de caja SOLO del vendedor actual
     * Filtra transacciones por user_id para mostrar solo lo que hizo el vendedor
     */
    public function clasificarPorTodasLasSubCajas(string $aperturaId, Collection $ventas): array
    {
        \Log::info('🔍🔍🔍 clasificarPorTodasLasSubCajas - INICIO', [
            'apertura_id' => $aperturaId,
        ]);
        
        // Obtener la apertura para saber el user_id y las fechas
        $apertura = DB::table('apertura_cierre_caja as acc')
            ->join('cajas_principales as cp', 'acc.caja_principal_id', '=', 'cp.id')
            ->where('acc.id', $aperturaId)
            ->select('acc.*', 'cp.user_id')
            ->first();

        if (!$apertura) {
            \Log::warning('⚠️ No se encontró apertura');
            return $this->respuestaVacia();
        }

        \Log::info('✅ Apertura encontrada', [
            'apertura_id' => $apertura->id,
            'user_id' => $apertura->user_id,
            'caja_principal_id' => $apertura->caja_principal_id,
        ]);

        $userId = $apertura->user_id;

        // Obtener todas las sub-cajas (no solo del vendedor, porque puede interactuar con cualquiera)
        $subCajasIds = DB::table('sub_cajas')->where('estado', 1)->pluck('id');

        // 1. EFECTIVO INICIAL (distribución en apertura)
        $efectivoInicial = $this->obtenerEfectivoInicial($aperturaId, $userId);

        // 2. COBROS POR MÉTODO DE PAGO (solo ventas del vendedor)
        $cobrosPorMetodo = $this->obtenerCobrosPorMetodoVendedor($ventas, $userId);

        // 3. OTROS INGRESOS (ingresos manuales del vendedor, NO ventas)
        $otrosIngresos = $this->obtenerOtrosIngresosVendedor($subCajasIds, $apertura, $userId);

        // 4. GASTOS (egresos del vendedor)
        $gastosYPagos = $this->obtenerGastosVendedor($subCajasIds, $apertura, $userId);

        // 5. PRÉSTAMOS RECIBIDOS (de otros vendedores)
        $prestamosRecibidos = $this->obtenerPrestamosRecibidosVendedor($apertura, $userId);

        // 6. PRÉSTAMOS DADOS (a otros vendedores)
        $prestamosDados = $this->obtenerPrestamosDadosVendedor($apertura, $userId);

        // 7. MOVIMIENTOS INTERNOS (solo informativo, NO afecta total)
        $movimientosInternos = $this->obtenerMovimientosInternosVendedor($apertura, $userId);

        // 8. CALCULAR TOTALES
        $totalCobros = $cobrosPorMetodo->sum('total');
        $totalOtrosIngresos = $otrosIngresos->sum('monto');
        $totalGastos = $gastosYPagos->sum('monto');
        $totalPrestamosRecibidos = $prestamosRecibidos->sum('monto');
        $totalPrestamosDados = $prestamosDados->sum('monto');
        
        \Log::info('Préstamos calculados', [
            'prestamos_recibidos_count' => $prestamosRecibidos->count(),
            'total_prestamos_recibidos' => $totalPrestamosRecibidos,
            'prestamos_dados_count' => $prestamosDados->count(),
            'total_prestamos_dados' => $totalPrestamosDados,
        ]);

        return [
            // Efectivo inicial
            'efectivo_inicial' => $efectivoInicial,
            
            // Ventas
            'ventas' => $ventas,
            
            // Cobros por método de pago (SOLO ventas del vendedor)
            'cobros_por_metodo' => $cobrosPorMetodo,
            'total_cobros' => $totalCobros,
            
            // Otros ingresos (NO ventas)
            'otros_ingresos' => $otrosIngresos,
            'total_otros_ingresos' => $totalOtrosIngresos,
            
            // Egresos
            'gastos_y_pagos' => $gastosYPagos,
            'total_gastos' => $totalGastos,
            
            // Préstamos entre vendedores
            'prestamos_recibidos' => $prestamosRecibidos,
            'total_prestamos_recibidos' => $totalPrestamosRecibidos,
            'prestamos_dados' => $prestamosDados,
            'total_prestamos_dados' => $totalPrestamosDados,
            
            // Movimientos internos (informativo)
            'movimientos_internos' => $movimientosInternos,
            'prestamos' => collect([]), // Deprecated
            'prestamos_vendedores' => $prestamosRecibidos->merge($prestamosDados),
            
            // Resúmenes
            'resumen_ventas' => $totalCobros,
            'resumen_ingresos' => $totalCobros + $totalOtrosIngresos + $totalPrestamosRecibidos,
            'resumen_egresos' => $totalGastos + $totalPrestamosDados,
        ];
    }

    /**
     * Obtener efectivo inicial del vendedor (distribución en apertura)
     */
    private function obtenerEfectivoInicial(string $aperturaId, string $userId): float
    {
        return DB::table('distribucion_efectivo_vendedores')
            ->where('apertura_cierre_caja_id', $aperturaId)
            ->where('user_id', $userId)
            ->sum('monto');
    }

    /**
     * Obtener cobros agrupados por método de pago (SOLO ventas del vendedor)
     * Agrupa por despliegue de pago para mostrar cada método por separado
     */
    private function obtenerCobrosPorMetodoVendedor(Collection $ventas, string $userId): Collection
    {
        \Log::info('🔍 obtenerCobrosPorMetodoVendedor - Inicio', [
            'total_ventas' => $ventas->count(),
            'user_id' => $userId,
        ]);
        
        // Filtrar ventas del vendedor
        $ventasVendedor = $ventas->where('user_id', $userId);
        
        \Log::info('🔍 Ventas filtradas por vendedor', [
            'ventas_vendedor_count' => $ventasVendedor->count(),
            'ventas_vendedor_ids' => $ventasVendedor->pluck('id')->toArray(),
        ]);
        
        if ($ventasVendedor->isEmpty()) {
            \Log::warning('⚠️ No hay ventas del vendedor');
            return collect([]);
        }

        // Obtener los pagos de las ventas desde despliegue_de_pago_ventas (TODOS los pagos)
        $ventaIds = $ventasVendedor->pluck('id');
        
        \Log::info('🔍 Buscando pagos en despliegue_de_pago_ventas', [
            'venta_ids' => $ventaIds->toArray(),
        ]);
        
        $pagos = DB::table('desplieguedepagoventa as dpv')
            ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->leftJoin('sub_cajas as sc', 'mp.subcaja_id', '=', 'sc.id')
            ->leftJoin('numeros_operacion_pago as nop', 'dpv.numero_operacion_id', '=', 'nop.id')
            ->whereIn('dpv.venta_id', $ventaIds)
            ->select([
                'mp.id as metodo_pago_id',
                'mp.name as banco',
                'dp.name as metodo_pago',
                'sc.nombre as sub_caja',
                'mp.nombre_titular as titular',
                'dpv.monto',
                'dpv.venta_id',
                'nop.numero_operacion'
            ])
            ->get();

        \Log::info('🔍 Pagos encontrados', [
            'pagos_count' => $pagos->count(),
            'pagos' => $pagos->toArray(),
        ]);

        // Agrupar por método de pago (sin importar titular o sub-caja)
        // Esto suma todas las transferencias BCP, BBVA, etc. juntas
        $resultado = $pagos->groupBy(function ($pago) {
            // Agrupar solo por banco y método, ignorando titular y sub-caja
            return $pago->banco . '/' . $pago->metodo_pago;
        })->map(function ($grupo) {
            $primer = $grupo->first();
            // Construir label con formato: Banco/Método
            $label = "{$primer->banco}/{$primer->metodo_pago}";
                
            return [
                'metodo_pago_id' => $primer->metodo_pago_id,
                'banco' => $primer->banco,
                'metodo_pago' => $primer->metodo_pago,
                'label' => $label,
                'total' => $grupo->sum('monto'),
                'cantidad_transacciones' => $grupo->count(),
                'tipo' => 'cobro_venta',
                // Agregar detalle de sub-cajas y titulares para el detalle
                'detalle' => $grupo->map(function ($pago) {
                    return [
                        'sub_caja' => $pago->sub_caja,
                        'titular' => $pago->titular,
                        'monto' => $pago->monto,
                        'numero_operacion' => $pago->numero_operacion,
                    ];
                })->toArray()
            ];
        })->values();
        
        \Log::info('🔍 Cobros agrupados', [
            'cobros_count' => $resultado->count(),
            'cobros' => $resultado->toArray(),
        ]);
        
        return $resultado;
    }

    /**
     * Obtener otros ingresos del vendedor (ingresos manuales, NO ventas)
     */
    private function obtenerOtrosIngresosVendedor($subCajasIds, $apertura, string $userId): Collection
    {
        \Log::info('Obteniendo otros ingresos del vendedor', [
            'user_id' => $userId,
            'sub_cajas_count' => count($subCajasIds)
        ]);
        
        $ingresos = DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.user_id', $userId) // ✅ FILTRAR POR VENDEDOR
            ->where('tc.tipo_transaccion', 'ingreso')
            // EXCLUIR ingresos que son de ventas, aperturas, transferencias entre vendedores o movimientos internos
            ->where(function($query) {
                $query->whereNull('tc.referencia_tipo')
                      ->orWhereNotIn('tc.referencia_tipo', ['venta', 'apertura', 'transferencia_vendedor', 'movimiento_interno']);
            })
            ->where('tc.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('tc.fecha', '<=', $fechaCierre);
            })
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.referencia_tipo',
                'tc.created_at',
                'sc.nombre as sub_caja'
            ])
            ->get();
            
        \Log::info('Otros ingresos obtenidos', [
            'count' => $ingresos->count(),
            'total' => $ingresos->sum('monto')
        ]);
        
        return $ingresos;
    }

    /**
     * Obtener gastos del vendedor (egresos reales)
     */
    private function obtenerGastosVendedor($subCajasIds, $apertura, string $userId): Collection
    {
        \Log::info('Obteniendo gastos del vendedor', [
            'user_id' => $userId,
            'sub_cajas_count' => count($subCajasIds)
        ]);
        
        $gastos = DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.user_id', $userId) // ✅ FILTRAR POR VENDEDOR
            ->where('tc.tipo_transaccion', 'egreso')
            // EXCLUIR egresos que son préstamos a vendedores o movimientos internos
            ->where(function($query) {
                $query->whereNull('tc.referencia_tipo')
                      ->orWhereNotIn('tc.referencia_tipo', ['transferencia_vendedor', 'movimiento_interno']);
            })
            ->where('tc.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('tc.fecha', '<=', $fechaCierre);
            })
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.created_at',
                'sc.nombre as sub_caja',
                DB::raw("'gasto' as tipo")
            ])
            ->get();
            
        \Log::info('Gastos obtenidos', [
            'count' => $gastos->count(),
            'total' => $gastos->sum('monto')
        ]);
        
        return $gastos;
    }

    /**
     * Obtener préstamos recibidos por el vendedor
     */
    private function obtenerPrestamosRecibidosVendedor($apertura, string $userId): Collection
    {
        try {
            \Log::info('🔍🔍🔍 obtenerPrestamosRecibidosVendedor - INICIO', [
                'apertura_id' => $apertura->id,
                'user_id' => $userId,
            ]);
            
            // Verificar cuántas transferencias hay en total
            $totalTransferencias = DB::table('transferencias_efectivo_vendedores')->count();
            \Log::info('📊 Total transferencias en DB', ['count' => $totalTransferencias]);
            
            // Verificar transferencias con esta apertura
            $transferenciasApertura = DB::table('transferencias_efectivo_vendedores')
                ->where('apertura_cierre_caja_id', $apertura->id)
                ->get(['id', 'vendedor_origen_id', 'vendedor_destino_id', 'monto']);
            \Log::info('📊 Transferencias de esta apertura', [
                'count' => $transferenciasApertura->count(),
                'transferencias' => $transferenciasApertura->toArray()
            ]);
            
            $prestamos = DB::table('transferencias_efectivo_vendedores as tev')
                ->join('user as u_origen', 'tev.vendedor_origen_id', '=', 'u_origen.id')
                ->leftJoin('sub_cajas as sc_origen', 'tev.sub_caja_origen_id', '=', 'sc_origen.id')
                ->leftJoin('sub_cajas as sc_destino', 'tev.sub_caja_destino_id', '=', 'sc_destino.id')
                ->leftJoin('solicitudes_efectivo_vendedores as sev', 'tev.solicitud_id', '=', 'sev.id')
                ->where('tev.apertura_cierre_caja_id', $apertura->id) // ✅ Filtrar por apertura
                ->where('tev.vendedor_destino_id', $userId) // Recibidos por este vendedor
                ->select([
                    'tev.id',
                    'tev.monto',
                    'tev.fecha_transferencia',
                    'u_origen.name as vendedor_origen',
                    'sc_origen.nombre as sub_caja_origen',
                    'sc_destino.nombre as sub_caja_destino',
                    'sev.motivo',
                    DB::raw("'recibido' as tipo_prestamo")
                ])
                ->get();
                
            \Log::info('✅ Préstamos recibidos encontrados', [
                'count' => $prestamos->count(),
                'prestamos' => $prestamos->toArray(),
            ]);
            
            return $prestamos;
        } catch (\Exception $e) {
            \Log::error('❌ Error en obtenerPrestamosRecibidosVendedor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect([]);
        }
    }

    /**
     * Obtener préstamos dados por el vendedor
     */
    private function obtenerPrestamosDadosVendedor($apertura, string $userId): Collection
    {
        \Log::info('🔍🔍🔍 obtenerPrestamosDadosVendedor - INICIO', [
            'apertura_id' => $apertura->id,
            'user_id' => $userId,
        ]);
        
        $prestamos = DB::table('transferencias_efectivo_vendedores as tev')
            ->join('user as u_destino', 'tev.vendedor_destino_id', '=', 'u_destino.id')
            ->leftJoin('sub_cajas as sc_origen', 'tev.sub_caja_origen_id', '=', 'sc_origen.id')
            ->leftJoin('sub_cajas as sc_destino', 'tev.sub_caja_destino_id', '=', 'sc_destino.id')
            ->leftJoin('solicitudes_efectivo_vendedores as sev', 'tev.solicitud_id', '=', 'sev.id')
            ->where('tev.apertura_cierre_caja_id', $apertura->id) // ✅ Filtrar por apertura
            ->where('tev.vendedor_origen_id', $userId) // Dados por este vendedor
            ->select([
                'tev.id',
                'tev.monto',
                'tev.fecha_transferencia',
                'u_destino.name as vendedor_destino',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino',
                'sev.motivo',
                DB::raw("'dado' as tipo_prestamo")
            ])
            ->get();
            
        \Log::info('✅ Préstamos dados encontrados', [
            'count' => $prestamos->count(),
            'prestamos' => $prestamos->toArray(),
        ]);
        
        return $prestamos;
    }

    /**
     * Obtener movimientos internos del vendedor (solo informativo)
     */
    private function obtenerMovimientosInternosVendedor($apertura, string $userId): Collection
    {
        return DB::table('movimientos_internos as mi')
            ->join('sub_cajas as sc_origen', 'mi.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'mi.sub_caja_destino_id', '=', 'sc_destino.id')
            ->join('cajas_principales as cp_origen', 'sc_origen.caja_principal_id', '=', 'cp_origen.id')
            ->where('cp_origen.user_id', $userId)
            ->where('mi.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('mi.fecha', '<=', $fechaCierre);
            })
            ->select([
                'mi.id',
                'mi.monto',
                'mi.justificacion',
                'mi.fecha',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino'
            ])
            ->get();
    }

    /**
     * Obtener cobros agrupados por método de pago (SOLO de ventas)
     */
    private function obtenerCobrosPorMetodo(Collection $ventas): Collection
    {
        if ($ventas->isEmpty()) {
            return collect([]);
        }

        // Obtener los pagos de las ventas desde la tabla correcta
        $ventaIds = $ventas->pluck('id');
        
        $pagos = DB::table('numeros_operacion_pago as nop')
            ->join('desplieguedepago as dp', 'nop.despliegue_pago_id', '=', 'dp.id')
            ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->whereIn('nop.venta_id', $ventaIds)
            ->whereNotNull('nop.venta_id')
            ->select([
                'mp.id as metodo_pago_id',
                'mp.name as metodo_pago',
                'dp.name as despliegue_pago',
                'nop.monto',
                'nop.venta_id'
            ])
            ->get();

        // Agrupar por método de pago
        return $pagos->groupBy('metodo_pago_id')->map(function ($grupo) {
            $primer = $grupo->first();
            return [
                'metodo_pago_id' => $primer->metodo_pago_id,
                'metodo_pago' => $primer->metodo_pago,
                'despliegue_pago' => $primer->despliegue_pago,
                'total' => $grupo->sum('monto'),
                'cantidad_transacciones' => $grupo->count(),
                'tipo' => 'cobro_venta'
            ];
        })->values();
    }

    /**
     * Obtener otros ingresos (ingresos manuales, NO ventas)
     * EXCLUYE ingresos que son de ventas para no duplicar
     */
    private function obtenerOtrosIngresos($subCajasIds, $apertura): Collection
    {
        return DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.tipo_transaccion', 'ingreso')
            // EXCLUIR ingresos que son de ventas (ya están en cobros_por_metodo)
            ->where(function($query) {
                $query->whereNull('tc.referencia_tipo')
                      ->orWhereNotIn('tc.referencia_tipo', ['venta']);
            })
            ->where('tc.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('tc.fecha', '<=', $fechaCierre);
            })
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.referencia_tipo',
                'tc.created_at',
                'sc.nombre as sub_caja'
            ])
            ->get();
    }

    /**
     * Obtener gastos y pagos (egresos reales)
     */
    private function obtenerGastosYPagos($subCajasIds, $apertura): Collection
    {
        return DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.tipo_transaccion', 'egreso')
            ->where('tc.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('tc.fecha', '<=', $fechaCierre);
            })
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.created_at',
                'sc.nombre as sub_caja',
                DB::raw("'gasto' as tipo")
            ])
            ->get();
    }

    /**
     * Obtener movimientos internos (solo informativo)
     */
    private function obtenerMovimientosInternos($apertura): Collection
    {
        return DB::table('movimientos_internos as mi')
            ->join('sub_cajas as sc_origen', 'mi.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'mi.sub_caja_destino_id', '=', 'sc_destino.id')
            ->join('cajas_principales as cp_origen', 'sc_origen.caja_principal_id', '=', 'cp_origen.id')
            ->where('cp_origen.user_id', $apertura->user_id)
            ->where('mi.fecha', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('mi.fecha', '<=', $fechaCierre);
            })
            ->select([
                'mi.id',
                'mi.monto',
                'mi.justificacion',
                'mi.fecha',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino'
            ])
            ->get();
    }

    /**
     * Obtener préstamos entre cajas (solo informativo)
     */
    private function obtenerPrestamos($apertura): Collection
    {
        return DB::table('prestamos_entre_cajas as pec')
            ->leftJoin('sub_cajas as sc_origen', 'pec.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'pec.sub_caja_destino_id', '=', 'sc_destino.id')
            ->where(function ($query) use ($apertura) {
                $query->where('pec.user_presta_id', $apertura->user_id)
                      ->orWhere('pec.user_recibe_id', $apertura->user_id);
            })
            ->where('pec.fecha_prestamo', '>=', $apertura->fecha_apertura)
            ->when($apertura->fecha_cierre, function ($query, $fechaCierre) {
                return $query->where('pec.fecha_prestamo', '<=', $fechaCierre);
            })
            ->select([
                'pec.id',
                'pec.monto',
                'pec.estado',
                'pec.estado_aprobacion',
                'pec.motivo',
                'pec.fecha_prestamo',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino'
            ])
            ->get();
    }

    /**
     * Obtener préstamos entre vendedores (solo informativo)
     */
    private function obtenerPrestamosVendedores($apertura): Collection
    {
        return DB::table('transferencias_efectivo_vendedores as tev')
            ->join('users as u_origen', 'tev.vendedor_origen_id', '=', 'u_origen.id')
            ->join('users as u_destino', 'tev.vendedor_destino_id', '=', 'u_destino.id')
            ->leftJoin('sub_cajas as sc_origen', 'tev.sub_caja_origen_id', '=', 'sc_origen.id')
            ->leftJoin('sub_cajas as sc_destino', 'tev.sub_caja_destino_id', '=', 'sc_destino.id')
            ->leftJoin('solicitudes_efectivo_vendedores as sev', 'tev.solicitud_id', '=', 'sev.id')
            ->where('tev.apertura_cierre_caja_id', $apertura->id)
            ->where(function ($query) use ($apertura) {
                $query->where('tev.vendedor_origen_id', $apertura->user_id)
                      ->orWhere('tev.vendedor_destino_id', $apertura->user_id);
            })
            ->select([
                'tev.id',
                'tev.monto',
                'tev.fecha_transferencia',
                'u_origen.name as vendedor_origen',
                'u_destino.name as vendedor_destino',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino',
                'sev.motivo',
                DB::raw("CASE 
                    WHEN tev.vendedor_origen_id = '{$apertura->user_id}' THEN 'dado'
                    ELSE 'recibido'
                END as tipo_prestamo")
            ])
            ->get();
    }

    private function respuestaVacia(): array
    {
        return [
            'efectivo_inicial' => 0,
            'ventas' => collect([]),
            'cobros_por_metodo' => collect([]),
            'total_cobros' => 0,
            'otros_ingresos' => collect([]),
            'total_otros_ingresos' => 0,
            'gastos_y_pagos' => collect([]),
            'total_gastos' => 0,
            'prestamos_recibidos' => collect([]),
            'total_prestamos_recibidos' => 0,
            'prestamos_dados' => collect([]),
            'total_prestamos_dados' => 0,
            'movimientos_internos' => collect([]),
            'prestamos' => collect([]),
            'prestamos_vendedores' => collect([]),
            'resumen_ventas' => 0,
            'resumen_ingresos' => 0,
            'resumen_egresos' => 0,
        ];
    }

    private function agruparPorMetodoPago(Collection $ventas): Collection
    {
        return $ventas->flatMap(function ($venta) {
            return $venta->desplieguesPago;
        })->groupBy('metodo_pago_id')->map(function ($pagos) {
            return [
                'metodo_pago' => $pagos->first()->metodoPago->nombre ?? 'Desconocido',
                'total' => $pagos->sum('monto')
            ];
        })->values();
    }
}
