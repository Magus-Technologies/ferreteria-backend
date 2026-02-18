<?php

namespace App\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResumenBancoQuery
{
    /**
     * Obtener resumen detallado de un banco con filtros
     */
    public function obtenerResumenDetallado(
        string $metodoPagoId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $vendedorId = null,
        ?string $subCajaId = null,
        ?string $desplieguePagoId = null
    ): array {
        $fechaInicio = $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay() : Carbon::now()->startOfDay();
        $fechaFin = $fechaFin ? Carbon::parse($fechaFin)->endOfDay() : Carbon::now()->endOfDay();

        // Obtener información del banco
        $banco = DB::table('metododepago')
            ->where('id', $metodoPagoId)
            ->first();

        if (!$banco) {
            return $this->respuestaVacia();
        }

        // Obtener monto inicial del banco
        $montoInicial = (float) $banco->monto_inicial;

        // Obtener ventas del banco
        $ventas = $this->obtenerVentas($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId, $desplieguePagoId);
        
        // Obtener otros ingresos
        $otrosIngresos = $this->obtenerOtrosIngresos($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId, $desplieguePagoId);
        
        // Obtener préstamos recibidos
        $prestamosRecibidos = $this->obtenerPrestamosRecibidos($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId);
        
        // Obtener gastos
        $gastos = $this->obtenerGastos($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId, $desplieguePagoId);
        
        // Obtener préstamos dados
        $prestamosDados = $this->obtenerPrestamosDados($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId);
        
        // Obtener movimientos internos
        $movimientosInternos = $this->obtenerMovimientosInternos($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId);
        
        // Obtener desglose por método de pago
        $desglosePorMetodo = $this->obtenerDesglosePorMetodo($metodoPagoId, $fechaInicio, $fechaFin, $vendedorId, $subCajaId);

        // Calcular totales
        $totalIngresos = $ventas->sum('monto') + $otrosIngresos->sum('monto') + $prestamosRecibidos->sum('monto');
        $totalEgresos = $gastos->sum('monto') + $prestamosDados->sum('monto');
        $saldoFinal = $montoInicial + $totalIngresos - $totalEgresos;

        return [
            'banco' => [
                'id' => $banco->id,
                'nombre' => $banco->name,
                'titular' => $banco->nombre_titular,
                'cuenta' => $banco->cuenta_bancaria,
            ],
            'periodo' => [
                'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                'fecha_fin' => $fechaFin->format('Y-m-d'),
            ],
            'resumen' => [
                'monto_inicial' => $montoInicial,
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'saldo_final' => $saldoFinal,
            ],
            'desglose_por_metodo' => $desglosePorMetodo,
            'ventas' => $ventas,
            'otros_ingresos' => $otrosIngresos,
            'prestamos_recibidos' => $prestamosRecibidos,
            'gastos' => $gastos,
            'prestamos_dados' => $prestamosDados,
            'movimientos_internos' => $movimientosInternos,
        ];
    }

    /**
     * Obtener ventas del banco
     */
    private function obtenerVentas(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId,
        ?string $desplieguePagoId
    ): Collection {
        $query = DB::table('desplieguedepagoventa as dpv')
            ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->join('venta as v', 'dpv.venta_id', '=', 'v.id')
            ->join('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('numeros_operacion_pago as nop', 'dpv.numero_operacion_id', '=', 'nop.id')
            // ✅ Obtener sub-caja a través de la ruta correcta: metododepago → sub_cajas
            ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->leftJoin('sub_cajas as sc', 'mp.subcaja_id', '=', 'sc.id')
            ->where('dp.metodo_de_pago_id', $metodoPagoId)
            ->whereBetween('v.fecha', [$fechaInicio, $fechaFin]);

        if ($vendedorId) {
            $query->where('v.user_id', $vendedorId);
        }

        // ✅ Filtrar por sub-caja a través de metododepago, no de venta
        if ($subCajaId) {
            $query->where('mp.subcaja_id', $subCajaId);
        }

        if ($desplieguePagoId) {
            $query->where('dpv.despliegue_de_pago_id', $desplieguePagoId);
        }

        return $query->select([
            'v.id as venta_id',
            DB::raw("CONCAT(v.serie, '-', v.numero) as numero_comprobante"),
            'v.fecha',
            DB::raw('TIME(v.fecha) as hora'),
            DB::raw("COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)) as cliente"),
            'u.name as vendedor',
            'dp.name as metodo_pago',
            'sc.nombre as sub_caja',
            'dpv.monto',
            'nop.numero_operacion',
        ])->get();
    }

    /**
     * Obtener otros ingresos (NO ventas, NO montos iniciales)
     */
    private function obtenerOtrosIngresos(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId,
        ?string $desplieguePagoId
    ): Collection {
        $query = DB::table('transacciones_caja as tc')
            ->join('desplieguedepago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->join('user as u', 'tc.user_id', '=', 'u.id')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->where('dp.metodo_de_pago_id', $metodoPagoId)
            ->where('tc.tipo_transaccion', 'ingreso')
            ->where(function ($q) {
                $q->whereNull('tc.referencia_tipo')
                    ->orWhereNotIn('tc.referencia_tipo', ['venta', 'apertura', 'transferencia_vendedor', 'movimiento_interno', 'monto_inicial']);
            })
            ->whereBetween('tc.fecha', [$fechaInicio, $fechaFin]);

        if ($vendedorId) {
            $query->where('tc.user_id', $vendedorId);
        }

        if ($subCajaId) {
            $query->where('tc.sub_caja_id', $subCajaId);
        }

        if ($desplieguePagoId) {
            $query->where('tc.despliegue_pago_id', $desplieguePagoId);
        }

        return $query->select([
            'tc.id',
            'tc.descripcion as concepto',
            'tc.fecha',
            DB::raw('TIME(tc.fecha) as hora'),
            'u.name as vendedor',
            'dp.name as metodo_pago',
            'sc.nombre as sub_caja',
            'tc.monto',
        ])->get();
    }

    /**
     * Obtener préstamos recibidos
     */
    private function obtenerPrestamosRecibidos(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId
    ): Collection {
        // TODO: Implementar cuando se tenga la estructura de préstamos con métodos de pago
        return collect([]);
    }

    /**
     * Obtener gastos del banco
     */
    private function obtenerGastos(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId,
        ?string $desplieguePagoId
    ): Collection {
        $query = DB::table('transacciones_caja as tc')
            ->join('desplieguedepago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->join('user as u', 'tc.user_id', '=', 'u.id')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->where('dp.metodo_de_pago_id', $metodoPagoId)
            ->where('tc.tipo_transaccion', 'egreso')
            ->where(function ($q) {
                $q->whereNull('tc.referencia_tipo')
                    ->orWhereNotIn('tc.referencia_tipo', ['transferencia_vendedor', 'movimiento_interno']);
            })
            ->whereBetween('tc.fecha', [$fechaInicio, $fechaFin]);

        if ($vendedorId) {
            $query->where('tc.user_id', $vendedorId);
        }

        if ($subCajaId) {
            $query->where('tc.sub_caja_id', $subCajaId);
        }

        if ($desplieguePagoId) {
            $query->where('tc.despliegue_pago_id', $desplieguePagoId);
        }

        return $query->select([
            'tc.id',
            'tc.descripcion as concepto',
            'tc.fecha',
            DB::raw('TIME(tc.fecha) as hora'),
            'u.name as vendedor',
            'dp.name as metodo_pago',
            'sc.nombre as sub_caja',
            'tc.monto',
        ])->get();
    }

    /**
     * Obtener préstamos dados
     */
    private function obtenerPrestamosDados(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId
    ): Collection {
        // TODO: Implementar cuando se tenga la estructura de préstamos con métodos de pago
        return collect([]);
    }

    /**
     * Obtener movimientos internos
     */
    private function obtenerMovimientosInternos(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId
    ): Collection {
        // TODO: Implementar cuando se tenga la estructura de movimientos internos con métodos de pago
        return collect([]);
    }

    /**
     * Obtener desglose por método de pago (Yape, Transferencia, etc.)
     */
    private function obtenerDesglosePorMetodo(
        string $metodoPagoId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?string $vendedorId,
        ?string $subCajaId
    ): Collection {
        // Ingresos por método
        $ingresos = DB::table('desplieguedepagoventa as dpv')
            ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->join('venta as v', 'dpv.venta_id', '=', 'v.id')
            // ✅ Join condicional para filtrar por sub-caja si es necesario
            ->when($subCajaId, function($q) use ($subCajaId) {
                $q->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
                  ->where('mp.subcaja_id', $subCajaId);
            })
            ->where('dp.metodo_de_pago_id', $metodoPagoId)
            ->whereBetween('v.fecha', [$fechaInicio, $fechaFin])
            ->when($vendedorId, fn($q) => $q->where('v.user_id', $vendedorId))
            ->groupBy('dp.id', 'dp.name')
            ->select([
                'dp.id as metodo_id',
                'dp.name as metodo',
                DB::raw('SUM(dpv.monto) as total_ingresos'),
                DB::raw('COUNT(*) as cantidad_ingresos'),
            ])
            ->get()
            ->keyBy('metodo_id');

        // Egresos por método
        $egresos = DB::table('transacciones_caja as tc')
            ->join('desplieguedepago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->where('dp.metodo_de_pago_id', $metodoPagoId)
            ->where('tc.tipo_transaccion', 'egreso')
            ->whereBetween('tc.fecha', [$fechaInicio, $fechaFin])
            ->when($vendedorId, fn($q) => $q->where('tc.user_id', $vendedorId))
            ->when($subCajaId, fn($q) => $q->where('tc.sub_caja_id', $subCajaId))
            ->groupBy('dp.id', 'dp.name')
            ->select([
                'dp.id as metodo_id',
                'dp.name as metodo',
                DB::raw('SUM(tc.monto) as total_egresos'),
                DB::raw('COUNT(*) as cantidad_egresos'),
            ])
            ->get()
            ->keyBy('metodo_id');

        // Combinar ingresos y egresos
        $metodos = DB::table('desplieguedepago')
            ->where('metodo_de_pago_id', $metodoPagoId)
            ->where('activo', true)
            ->get();

        return $metodos->map(function ($metodo) use ($ingresos, $egresos) {
            $ingreso = $ingresos->get($metodo->id);
            $egreso = $egresos->get($metodo->id);

            $totalIngresos = $ingreso ? (float) $ingreso->total_ingresos : 0;
            $totalEgresos = $egreso ? (float) $egreso->total_egresos : 0;
            $neto = $totalIngresos - $totalEgresos;

            return [
                'metodo_id' => $metodo->id,
                'metodo' => $metodo->name,
                'total_ingresos' => $totalIngresos,
                'cantidad_ingresos' => $ingreso ? $ingreso->cantidad_ingresos : 0,
                'total_egresos' => $totalEgresos,
                'cantidad_egresos' => $egreso ? $egreso->cantidad_egresos : 0,
                'neto' => $neto,
            ];
        });
    }

    private function respuestaVacia(): array
    {
        return [
            'banco' => null,
            'periodo' => [],
            'resumen' => [
                'monto_inicial' => 0,
                'total_ingresos' => 0,
                'total_egresos' => 0,
                'saldo_final' => 0,
            ],
            'desglose_por_metodo' => collect([]),
            'ventas' => collect([]),
            'otros_ingresos' => collect([]),
            'prestamos_recibidos' => collect([]),
            'gastos' => collect([]),
            'prestamos_dados' => collect([]),
            'movimientos_internos' => collect([]),
        ];
    }
}
