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
     * 
     * @param string $aperturaId ID de la apertura
     * @param Collection $ventas Colección de ventas
     * @param bool $soloDiaActual Si es true, filtra solo movimientos del día actual (para arqueo diario)
     */
    public function clasificarPorTodasLasSubCajas(string $aperturaId, Collection $ventas, bool $soloDiaActual = false, ?\Carbon\Carbon $fechaFiltro = null): array
    {


        // Obtener la apertura para saber el user_id (quien aperturó) y las fechas
        $apertura = DB::table('apertura_cierre_caja')
            ->where('id', $aperturaId)
            ->first();

        if (!$apertura) {

            return $this->respuestaVacia();
        }

        // Determinar el rango de fechas según el tipo de cierre
        if ($soloDiaActual) {
            // Para arqueo diario: solo movimientos del día especificado (o hoy por defecto)
            $fechaDia = $fechaFiltro ?? \Carbon\Carbon::today();
            $fechaInicio = $fechaDia->copy()->startOfDay();
            $fechaFin = $fechaDia->copy()->endOfDay();
        } else {
            // Para cierre definitivo: desde apertura hasta cierre (o +12 horas)
            $fechaInicio = $apertura->fecha_apertura;
            $fechaFin = $apertura->fecha_cierre ?? \Carbon\Carbon::parse($apertura->fecha_apertura)->addHours(12);
        }

        $userId = $apertura->user_id;

        // Obtener todas las sub-cajas (no solo del vendedor, porque puede interactuar con cualquiera)
        $subCajasIds = DB::table('sub_cajas')->where('estado', 1)->pluck('id');

        // 1. EFECTIVO INICIAL (distribución en apertura)
        $efectivoInicial = $this->obtenerEfectivoInicial($aperturaId, $userId, $soloDiaActual, $fechaInicio, $fechaFin);

        // 2. COBROS POR MÉTODO DE PAGO (solo ventas del vendedor)
        $cobrosPorMetodo = $this->obtenerCobrosPorMetodoVendedor($ventas, $userId);

        // 3. OTROS INGRESOS (ingresos manuales del vendedor, NO ventas)
        $otrosIngresos = $this->obtenerOtrosIngresosVendedor($subCajasIds, $apertura, $userId, $fechaInicio, $fechaFin);

        // 4. GASTOS (egresos del vendedor)
        $gastosYPagos = $this->obtenerGastosVendedor($subCajasIds, $apertura, $userId, $fechaInicio, $fechaFin);

        // 5. PRÉSTAMOS RECIBIDOS (de otros vendedores)
        $prestamosRecibidos = $this->obtenerPrestamosRecibidosVendedor($apertura, $userId, $fechaInicio, $fechaFin);

        // 6. PRÉSTAMOS DADOS (a otros vendedores)
        $prestamosDados = $this->obtenerPrestamosDadosVendedor($apertura, $userId, $fechaInicio, $fechaFin);

        // 7. MOVIMIENTOS INTERNOS (solo informativo, NO afecta total)
        $movimientosInternos = $this->obtenerMovimientosInternosVendedor($apertura, $userId, $fechaInicio, $fechaFin);

        // 8. RESUMEN DE BANCOS (montos iniciales + ingresos - egresos por banco)
        $resumenBancos = $this->obtenerResumenBancosVendedor($subCajasIds, $apertura, $userId, $ventas, $fechaInicio, $fechaFin);

        // 9. CALCULAR TOTALES
        $totalCobros = $cobrosPorMetodo->sum('total');
        $totalOtrosIngresos = $otrosIngresos->sum('monto');
        $totalGastos = $gastosYPagos->sum('monto');
        $totalPrestamosRecibidos = $prestamosRecibidos->sum('monto');
        $totalPrestamosDados = $prestamosDados->sum('monto');



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

            // Resumen de bancos (nuevo)
            'resumen_bancos' => $resumenBancos,

            // Resúmenes
            'resumen_ventas' => $totalCobros,
            'resumen_ingresos' => $totalCobros + $totalOtrosIngresos + $totalPrestamosRecibidos,
            'resumen_egresos' => $totalGastos + $totalPrestamosDados,
        ];
    }

    /**
     * Obtener efectivo inicial del vendedor (distribución en apertura)
     */
    private function obtenerEfectivoInicial(string $aperturaId, string $userId, bool $soloDiaActual = false, $fechaInicio = null, $fechaFin = null): float
    {
        $query = DB::table('distribucion_efectivo_vendedores')
            ->where('apertura_cierre_caja_id', $aperturaId)
            ->where('user_id', $userId);

        if ($soloDiaActual && $fechaInicio && $fechaFin) {
            $query->where('created_at', '>=', $fechaInicio)
                  ->where('created_at', '<=', $fechaFin);
        }

        return $query->sum('monto');
    }

    /**
     * Obtener cobros agrupados por método de pago (SOLO ventas del vendedor)
     * Agrupa por despliegue de pago para mostrar cada método por separado
     */
    private function obtenerCobrosPorMetodoVendedor(Collection $ventas, string $userId): Collection
    {


        // Filtrar ventas del vendedor
        $ventasVendedor = $ventas->where('user_id', $userId);



        if ($ventasVendedor->isEmpty()) {

            return collect([]);
        }

        // Obtener los pagos de las ventas desde despliegue_de_pago_ventas (TODOS los pagos)
        $ventaIds = $ventasVendedor->pluck('id');



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



        return $resultado;
    }

    /**
     * Obtener otros ingresos del vendedor (ingresos manuales, NO ventas, NO montos iniciales)
     * Los montos iniciales de bancos NO deben aparecer aquí
     */
    private function obtenerOtrosIngresosVendedor($subCajasIds, $apertura, string $userId, $fechaInicio, $fechaFin): Collection
    {
        $ingresos = DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.user_id', $userId)
            ->where('tc.tipo_transaccion', 'ingreso')
            // EXCLUIR: ventas, aperturas, transferencias, movimientos internos Y MONTOS INICIALES
            ->where(function ($query) {
                $query->whereNull('tc.referencia_tipo')
                    ->orWhereNotIn('tc.referencia_tipo', [
                        'venta',
                        'apertura',
                        'transferencia_vendedor',
                        'movimiento_interno',
                        'monto_inicial' // ✅ EXCLUIR montos iniciales de bancos
                    ]);
            })
            ->where('tc.fecha', '>=', $fechaInicio)
            ->where('tc.fecha', '<=', $fechaFin)
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.referencia_tipo',
                'tc.created_at',
                'sc.nombre as sub_caja'
            ])
            ->get();

        return $ingresos;
    }

    /**
     * Obtener gastos del vendedor (egresos reales)
     */
    private function obtenerGastosVendedor($subCajasIds, $apertura, string $userId, $fechaInicio, $fechaFin): Collection
    {


        $gastos = DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.user_id', $userId) // ✅ FILTRAR POR VENDEDOR
            ->where('tc.tipo_transaccion', 'egreso')
            // EXCLUIR egresos que son préstamos a vendedores o movimientos internos
            ->where(function ($query) {
                $query->whereNull('tc.referencia_tipo')
                    ->orWhereNotIn('tc.referencia_tipo', ['transferencia_vendedor', 'movimiento_interno']);
            })
            ->where('tc.fecha', '>=', $fechaInicio)
            ->where('tc.fecha', '<=', $fechaFin)
            ->select([
                'tc.id',
                'tc.monto',
                'tc.descripcion',
                'tc.created_at',
                'sc.nombre as sub_caja',
                DB::raw("'gasto' as tipo")
            ])
            ->get();



        return $gastos;
    }

    /**
     * Obtener préstamos recibidos por el vendedor
     */
    private function obtenerPrestamosRecibidosVendedor($apertura, string $userId, $fechaInicio, $fechaFin): Collection
    {
        try {


            // Verificar cuántas transferencias hay en total
            $totalTransferencias = DB::table('transferencias_efectivo_vendedores')->count();


            // Verificar transferencias con esta apertura
            $transferenciasApertura = DB::table('transferencias_efectivo_vendedores')
                ->where('apertura_cierre_caja_id', $apertura->id)
                ->get(['id', 'vendedor_origen_id', 'vendedor_destino_id', 'monto']);


            $prestamos = DB::table('transferencias_efectivo_vendedores as tev')
                ->join('user as u_origen', 'tev.vendedor_origen_id', '=', 'u_origen.id')
                ->leftJoin('sub_cajas as sc_origen', 'tev.sub_caja_origen_id', '=', 'sc_origen.id')
                ->leftJoin('sub_cajas as sc_destino', 'tev.sub_caja_destino_id', '=', 'sc_destino.id')
                ->leftJoin('solicitudes_efectivo_vendedores as sev', 'tev.solicitud_id', '=', 'sev.id')
                ->where('tev.apertura_cierre_caja_id', $apertura->id) // ✅ Filtrar por apertura
                ->where('tev.vendedor_destino_id', $userId) // Recibidos por este vendedor
                ->where('tev.fecha_transferencia', '>=', $fechaInicio)
                ->where('tev.fecha_transferencia', '<=', $fechaFin)
                ->select([
                    'tev.id',
                    'tev.monto',
                    'tev.fecha_transferencia',
                    'u_origen.name as vendedor_origen',
                    'sc_origen.nombre as sub_caja_origen',
                    'sc_destino.nombre as sub_caja_destino',
                    'sev.motivo',
                    DB::raw("'Efectivo' as metodo_pago"),
                    DB::raw("'recibido' as tipo_prestamo")
                ])
                ->get();



            return $prestamos;
        } catch (\Exception $e) {

            return collect([]);
        }
    }

    /**
     * Obtener préstamos dados por el vendedor
     */
    private function obtenerPrestamosDadosVendedor($apertura, string $userId, $fechaInicio, $fechaFin): Collection
    {


        $prestamos = DB::table('transferencias_efectivo_vendedores as tev')
            ->join('user as u_destino', 'tev.vendedor_destino_id', '=', 'u_destino.id')
            ->leftJoin('sub_cajas as sc_origen', 'tev.sub_caja_origen_id', '=', 'sc_origen.id')
            ->leftJoin('sub_cajas as sc_destino', 'tev.sub_caja_destino_id', '=', 'sc_destino.id')
            ->leftJoin('solicitudes_efectivo_vendedores as sev', 'tev.solicitud_id', '=', 'sev.id')
            ->where('tev.apertura_cierre_caja_id', $apertura->id) // ✅ Filtrar por apertura
            ->where('tev.vendedor_origen_id', $userId) // Dados por este vendedor
            ->where('tev.fecha_transferencia', '>=', $fechaInicio)
            ->where('tev.fecha_transferencia', '<=', $fechaFin)
            ->select([
                'tev.id',
                'tev.monto',
                'tev.fecha_transferencia',
                'u_destino.name as vendedor_destino',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino',
                'sev.motivo',
                DB::raw("'Efectivo' as metodo_pago"),
                DB::raw("'dado' as tipo_prestamo")
            ])
            ->get();



        return $prestamos;
    }

    /**
     * Obtener movimientos internos del vendedor (solo informativo)
     */
    private function obtenerMovimientosInternosVendedor($apertura, string $userId, $fechaInicio, $fechaFin): Collection
    {
        return DB::table('movimientos_internos as mi')
            ->join('sub_cajas as sc_origen', 'mi.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'mi.sub_caja_destino_id', '=', 'sc_destino.id')
            ->join('cajas_principales as cp_origen', 'sc_origen.caja_principal_id', '=', 'cp_origen.id')
            ->leftJoin('desplieguedepago as dp_origen', 'mi.despliegue_de_pago_origen_id', '=', 'dp_origen.id')
            ->where('cp_origen.user_id', $userId)
            ->where('mi.fecha', '>=', $fechaInicio)
            ->where('mi.fecha', '<=', $fechaFin)
            ->select([
                'mi.id',
                'mi.monto',
                'mi.justificacion',
                'mi.fecha',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino',
                'dp_origen.name as metodo_pago'
            ])
            ->get();
    }

    /**
     * Obtener resumen de bancos del vendedor
     * Agrupa por MetodoDePago (banco) y calcula: monto_inicial + ingresos - egresos
     */
    private function obtenerResumenBancosVendedor($subCajasIds, $apertura, string $userId, Collection $ventas, $fechaInicio, $fechaFin): Collection
    {
        // 1. Obtener montos iniciales de bancos (transacciones con referencia_tipo = 'monto_inicial')
        $queryMontos = DB::table('transacciones_caja as tc')
            ->join('desplieguedepago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.referencia_tipo', 'monto_inicial');

        if ($fechaInicio && $fechaFin) {
            $queryMontos->where('tc.fecha', '>=', $fechaInicio)
                        ->where('tc.fecha', '<=', $fechaFin);
        }

        $montosIniciales = $queryMontos->select([
                'mp.id as banco_id',
                'mp.name as banco',
                'mp.nombre_titular as titular',
                'mp.cuenta_bancaria as cuenta',
                DB::raw('SUM(tc.monto) as monto_inicial')
            ])
            ->groupBy('mp.id', 'mp.name', 'mp.nombre_titular', 'mp.cuenta_bancaria')
            ->get();

        // 2. Obtener ingresos digitales por banco (de ventas del vendedor)
        $ventasVendedor = $ventas->where('user_id', $userId);
        $ventaIds = $ventasVendedor->pluck('id');

        $ingresosPorBanco = collect([]);
        if ($ventaIds->isNotEmpty()) {
            $ingresosPorBanco = DB::table('desplieguedepagoventa as dpv')
                ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
                ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
                ->whereIn('dpv.venta_id', $ventaIds)
                ->select([
                    'mp.id as banco_id',
                    'dp.name as metodo_pago',
                    DB::raw('SUM(dpv.monto) as total_ingresos')
                ])
                ->groupBy('mp.id', 'dp.name')
                ->get()
                ->groupBy('banco_id')
                ->map(function ($grupo) {
                    return [
                        'total' => $grupo->sum('total_ingresos'),
                        'detalle' => $grupo->map(function ($item) {
                            return [
                                'metodo_pago' => $item->metodo_pago,
                                'monto' => $item->total_ingresos
                            ];
                        })->toArray()
                    ];
                });
        }

        // 3. Obtener egresos digitales por banco (del vendedor)
        $egresosPorBanco = DB::table('transacciones_caja as tc')
            ->join('desplieguedepago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.user_id', $userId)
            ->where('tc.tipo_transaccion', 'egreso')
            ->where('tc.referencia_tipo', '!=', 'monto_inicial')
            ->where('tc.fecha', '>=', $fechaInicio)
            ->where('tc.fecha', '<=', $fechaFin)
            ->select([
                'mp.id as banco_id',
                DB::raw('SUM(tc.monto) as total_egresos')
            ])
            ->groupBy('mp.id')
            ->get()
            ->keyBy('banco_id');

        // 4. Consolidar resumen por banco
        return $montosIniciales->map(function ($banco) use ($ingresosPorBanco, $egresosPorBanco) {
            $ingresos = $ingresosPorBanco->get($banco->banco_id);
            $egresos = $egresosPorBanco->get($banco->banco_id);

            $totalIngresos = $ingresos ? $ingresos['total'] : 0;
            $totalEgresos = $egresos ? $egresos->total_egresos : 0;
            $saldoFinal = $banco->monto_inicial + $totalIngresos - $totalEgresos;

            return [
                'banco_id' => $banco->banco_id,
                'banco' => $banco->banco,
                'titular' => $banco->titular,
                'cuenta' => $banco->cuenta,
                'monto_inicial' => (float) $banco->monto_inicial,
                'total_ingresos' => (float) $totalIngresos,
                'detalle_ingresos' => $ingresos ? $ingresos['detalle'] : [],
                'total_egresos' => (float) $totalEgresos,
                'saldo_final' => (float) $saldoFinal
            ];
        });
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
    private function obtenerOtrosIngresos($subCajasIds, $apertura, $fechaInicio, $fechaFin): Collection
    {
        return DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.tipo_transaccion', 'ingreso')
            // EXCLUIR ingresos que son de ventas (ya están en cobros_por_metodo)
            ->where(function ($query) {
                $query->whereNull('tc.referencia_tipo')
                    ->orWhereNotIn('tc.referencia_tipo', ['venta']);
            })
            ->where('tc.fecha', '>=', $fechaInicio)
            ->where('tc.fecha', '<=', $fechaFin)
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
    private function obtenerGastosYPagos($subCajasIds, $apertura, $fechaInicio, $fechaFin): Collection
    {
        return DB::table('transacciones_caja as tc')
            ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
            ->whereIn('tc.sub_caja_id', $subCajasIds)
            ->where('tc.tipo_transaccion', 'egreso')
            ->where('tc.fecha', '>=', $fechaInicio)
            ->where('tc.fecha', '<=', $fechaFin)
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
    private function obtenerMovimientosInternos($apertura, $fechaInicio, $fechaFin): Collection
    {
        return DB::table('movimientos_internos as mi')
            ->join('sub_cajas as sc_origen', 'mi.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'mi.sub_caja_destino_id', '=', 'sc_destino.id')
            ->join('cajas_principales as cp_origen', 'sc_origen.caja_principal_id', '=', 'cp_origen.id')
            ->leftJoin('desplieguedepago as dp_origen', 'mi.despliegue_de_pago_origen_id', '=', 'dp_origen.id')
            ->where('cp_origen.user_id', $apertura->user_id)
            ->where('mi.fecha', '>=', $fechaInicio)
            ->where('mi.fecha', '<=', $fechaFin)
            ->select([
                'mi.id',
                'mi.monto',
                'mi.justificacion',
                'mi.fecha',
                'sc_origen.nombre as sub_caja_origen',
                'sc_destino.nombre as sub_caja_destino',
                'dp_origen.name as metodo_pago'
            ])
            ->get();
    }

    /**
     * Obtener préstamos entre cajas (solo informativo)
     */
    private function obtenerPrestamos($apertura, $fechaInicio, $fechaFin): Collection
    {
        return DB::table('prestamos_entre_cajas as pec')
            ->leftJoin('sub_cajas as sc_origen', 'pec.sub_caja_origen_id', '=', 'sc_origen.id')
            ->join('sub_cajas as sc_destino', 'pec.sub_caja_destino_id', '=', 'sc_destino.id')
            ->where(function ($query) use ($apertura) {
                $query->where('pec.user_presta_id', $apertura->user_id)
                    ->orWhere('pec.user_recibe_id', $apertura->user_id);
            })
            ->where('pec.fecha_prestamo', '>=', $fechaInicio)
            ->where('pec.fecha_prestamo', '<=', $fechaFin)
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
