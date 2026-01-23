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
     * Consolidar cierre de caja de TODAS las subcajas del vendedor
     * SIN modificar la lógica de cajas, SOLO consolidando información
     */
    public function clasificarPorTodasLasSubCajas(string $aperturaId, Collection $ventas): array
    {
        // Obtener la apertura para saber el user_id y las fechas
        $apertura = DB::table('apertura_cierre_caja as acc')
            ->join('cajas_principales as cp', 'acc.caja_principal_id', '=', 'cp.id')
            ->where('acc.id', $aperturaId)
            ->select('acc.*', 'cp.user_id')
            ->first();

        if (!$apertura) {
            return $this->respuestaVacia();
        }

        // Obtener todas las sub-cajas del vendedor
        $subCajasIds = DB::table('sub_cajas as sc')
            ->join('cajas_principales as cp', 'sc.caja_principal_id', '=', 'cp.id')
            ->where('cp.user_id', $apertura->user_id)
            ->pluck('sc.id');

        // 1. COBROS POR MÉTODO DE PAGO (solo ventas reales)
        $cobrosPorMetodo = $this->obtenerCobrosPorMetodo($ventas);

        // 2. OTROS INGRESOS (ingresos manuales, NO ventas)
        $otrosIngresos = $this->obtenerOtrosIngresos($subCajasIds, $apertura);

        // 3. GASTOS Y PAGOS (egresos reales)
        $gastosYPagos = $this->obtenerGastosYPagos($subCajasIds, $apertura);

        // 4. MOVIMIENTOS INTERNOS (solo informativo, NO afecta total)
        $movimientosInternos = $this->obtenerMovimientosInternos($apertura);

        // 5. PRÉSTAMOS (solo informativo, NO afecta total)
        $prestamos = $this->obtenerPrestamos($apertura);

        // 6. CALCULAR TOTALES
        $totalCobros = $cobrosPorMetodo->sum('total');
        $totalOtrosIngresos = $otrosIngresos->sum('monto');
        $totalGastos = $gastosYPagos->where('tipo', 'gasto')->sum('monto');
        $totalPagos = $gastosYPagos->where('tipo', 'pago')->sum('monto');

        return [
            // Ventas
            'ventas' => $ventas,
            
            // Cobros por método de pago (SOLO ventas)
            'cobros_por_metodo' => $cobrosPorMetodo,
            'total_cobros' => $totalCobros,
            
            // Otros ingresos (NO ventas)
            'otros_ingresos' => $otrosIngresos,
            'total_otros_ingresos' => $totalOtrosIngresos,
            
            // Egresos
            'gastos_y_pagos' => $gastosYPagos,
            'total_gastos' => $totalGastos,
            'total_pagos' => $totalPagos,
            
            // Movimientos internos (informativo)
            'movimientos_internos' => $movimientosInternos,
            'prestamos' => $prestamos,
            
            // Resúmenes
            'resumen_ventas' => $totalCobros,
            'resumen_ingresos' => $totalCobros + $totalOtrosIngresos,
            'resumen_egresos' => $totalGastos + $totalPagos,
        ];
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
     * Obtener préstamos (solo informativo)
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

    private function respuestaVacia(): array
    {
        return [
            'ventas' => collect([]),
            'cobros_por_metodo' => collect([]),
            'total_cobros' => 0,
            'otros_ingresos' => collect([]),
            'total_otros_ingresos' => 0,
            'gastos_y_pagos' => collect([]),
            'total_gastos' => 0,
            'total_pagos' => 0,
            'movimientos_internos' => collect([]),
            'prestamos' => collect([]),
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
