<?php

namespace App\Services\CierreCaja;

use App\DTOs\CierreCaja\ResumenCajaDTO;
use App\Models\AperturaCierreCaja;
use App\Queries\CierreCaja\MovimientosCajaQuery;
use App\Repositories\Interfaces\VentaRepositoryInterface;

class CalculadorResumenCaja
{
    public function __construct(
        private MovimientosCajaQuery $movimientosQuery,
        private VentaRepositoryInterface $ventaRepository,
        private ClasificadorMovimientos $clasificador
    ) {}

    public function calcular(AperturaCierreCaja $apertura, bool $soloDiaActual = false, ?\Carbon\Carbon $fechaFiltro = null): ResumenCajaDTO
    {
        \Log::info(' CalculadorResumenCaja::calcular - Inicio', [
            'apertura_id' => $apertura->id,
            'solo_dia_actual' => $soloDiaActual,
        ]);

        // Obtener ventas usando el repositorio existente
        $ventas = $this->ventaRepository->obtenerPorApertura($apertura->id);

        // Si es arqueo diario, filtrar ventas solo del día especificado (o hoy por defecto)
        if ($soloDiaActual) {
            $fechaDia = $fechaFiltro ?? \Carbon\Carbon::today();
            $ventas = $ventas->filter(function ($venta) use ($fechaDia) {
                return \Carbon\Carbon::parse($venta->created_at)->isSameDay($fechaDia);
            });
        }

        \Log::info(' Ventas obtenidas del repositorio', [
            'total_ventas' => $ventas->count(),
            'ventas_ids' => $ventas->pluck('id')->toArray(),
            'ventas_user_ids' => $ventas->pluck('user_id')->unique()->toArray(),
        ]);

        // Consolidar información de todas las subcajas
        $clasificacion = $this->clasificador->clasificarPorTodasLasSubCajas($apertura->id, $ventas, $soloDiaActual, $fechaFiltro);

        \Log::info(' Clasificación obtenida', [
            'efectivo_inicial' => $clasificacion['efectivo_inicial'],
            'ventas_count' => $clasificacion['ventas']->count(),
            'cobros_por_metodo_count' => $clasificacion['cobros_por_metodo']->count(),
            'cobros_por_metodo' => $clasificacion['cobros_por_metodo']->toArray(),
            'total_cobros' => $clasificacion['total_cobros'],
            'total_otros_ingresos' => $clasificacion['total_otros_ingresos'],
            'total_gastos' => $clasificacion['total_gastos'],
        ]);

        // FÓRMULA DEL CIERRE (SOLO EFECTIVO):
        // Total en Caja = Efectivo Inicial + Cobros en Efectivo + Otros Ingresos en Efectivo + Préstamos Recibidos - Gastos en Efectivo - Préstamos Dados
        // (Movimientos internos NO afectan el total)
        // (Pagos digitales NO afectan el efectivo en caja)

        // Filtrar solo cobros en EFECTIVO (método de pago "Efectivo")
        $cobrosEfectivo = $clasificacion['cobros_por_metodo']
            ->filter(function ($metodo) {
                return stripos($metodo['label'], 'Efectivo') !== false;
            })
            ->sum('total');

        // Filtrar solo otros ingresos en EFECTIVO (unir normales + extras para el cálculo de efectivo)
        $todosLosIngresosManuales = $clasificacion['otros_ingresos']->concat($clasificacion['ingresos_extras']);
        $otrosIngresosEfectivo = $todosLosIngresosManuales
            ->filter(function ($ingreso) {
                // Verificar si el ingreso es en efectivo (sub_caja contiene "Chica" o es efectivo)
                return stripos($ingreso->sub_caja ?? '', 'Chica') !== false;
            })
            ->sum('monto');

        // Filtrar solo gastos en EFECTIVO (unir normales + extras para el cálculo de efectivo)
        $todosLosEgresosManuales = $clasificacion['gastos_y_pagos']->concat($clasificacion['gastos_extras']);
        $gastosEfectivo = $todosLosEgresosManuales
            ->filter(function ($gasto) {
                // Verificar si el gasto es en efectivo (sub_caja contiene "Chica" o es efectivo)
                return stripos($gasto->sub_caja ?? '', 'Chica') !== false;
            })
            ->sum('monto');

        $montoEsperado = $clasificacion['efectivo_inicial']
            + $cobrosEfectivo
            + $otrosIngresosEfectivo
            + $clasificacion['total_prestamos_recibidos']
            - $gastosEfectivo
            - $clasificacion['total_prestamos_dados'];

        $montoCierre = $apertura->monto_cierre;
        $diferencia = $montoCierre !== null ? ($montoCierre - $montoEsperado) : null;

        // Formatear detalles de ventas con información de pagos
        $detalleVentas = $this->formatearDetalleVentas($clasificacion['ventas']);

        // Función auxiliar para formatear movimientos manuales
        $formatearManual = function ($item, $tipoDefault = 'ingreso_manual') {
            return [
                'id' => $item->id,
                'tipo' => $item->tipo ?? $tipoDefault,
                'monto' => number_format($item->monto, 2, '.', ''),
                'concepto' => $item->descripcion,
                'sub_caja' => $item->sub_caja,
                'created_at' => $item->created_at,
            ];
        };

        // Formatear detalles
        $detalleIngresos = $clasificacion['otros_ingresos']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item)]);
        $detalleEgresos = $clasificacion['gastos_y_pagos']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'gasto_manual')]);
        
        $detalleIngresosExtras = $clasificacion['ingresos_extras']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'ingreso_extra')]);
        $detalleGastosExtras = $clasificacion['gastos_extras']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'gasto_extra')]);

        $resultado = new ResumenCajaDTO(
            efectivoInicial: $clasificacion['efectivo_inicial'],
            montoApertura: $apertura->monto_apertura,
            totalIngresos: $clasificacion['resumen_ingresos'],
            totalEgresos: $clasificacion['resumen_egresos'],
            totalVentas: $clasificacion['resumen_ventas'],
            montoEsperado: $montoEsperado,
            montoCierre: $montoCierre,
            diferencia: $diferencia,
            detalleIngresos: $detalleIngresos,
            detalleEgresos: $detalleEgresos,
            detalleVentas: $detalleVentas,
            detalleMetodosPago: $clasificacion['cobros_por_metodo'],
            prestamosRecibidos: $clasificacion['prestamos_recibidos'],
            totalPrestamosRecibidos: $clasificacion['total_prestamos_recibidos'],
            prestamosDados: $clasificacion['prestamos_dados'],
            totalPrestamosDados: $clasificacion['total_prestamos_dados'],
            movimientosInternos: $clasificacion['movimientos_internos'],
            prestamos: $clasificacion['prestamos'],
            prestamosVendedores: $clasificacion['prestamos_vendedores'],
            resumenBancos: $clasificacion['resumen_bancos'],
            totalIngresosExtras: $clasificacion['total_ingresos_extras'],
            totalGastosExtras: $clasificacion['total_gastos_extras'],
            detalleIngresosExtras: $detalleIngresosExtras,
            detalleGastosExtras: $detalleGastosExtras
        );

        \Log::info('🔍 ResumenCajaDTO creado', [
            'total_ventas' => $resultado->totalVentas,
            'detalle_ventas_count' => $resultado->detalleVentas->count(),
            'detalle_metodos_pago_count' => $resultado->detalleMetodosPago->count(),
        ]);

        return $resultado;
    }

    private function formatearDetalleVentas($ventas)
    {
        return $ventas->map(function ($venta) {
            // Obtener los pagos de esta venta con información de sub-caja
            // La sub-caja se obtiene desde transacciones_caja, no desde metododepago
            $pagos = \Illuminate\Support\Facades\DB::table('desplieguedepagoventa as dpv')
                ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
                ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
                ->leftJoin('numeros_operacion_pago as nop', 'dpv.numero_operacion_id', '=', 'nop.id')
                // Obtener la sub-caja desde transacciones_caja
                ->leftJoin('transacciones_caja as tc', function ($join) use ($venta) {
                    $join->on('tc.despliegue_pago_id', '=', 'dpv.despliegue_de_pago_id')
                        ->where('tc.referencia_id', '=', $venta->id)
                        ->where('tc.referencia_tipo', '=', 'venta');
                })
                ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
                ->where('dpv.venta_id', $venta->id)
                ->select([
                    'sc.nombre as sub_caja',
                    'mp.name as banco',
                    'dp.name as metodo_pago',
                    'dpv.monto',
                    'nop.numero_operacion'
                ])
                ->get();

            // Calcular el total desde los pagos
            $totalVenta = $pagos->sum('monto');

            // Construir array de la venta con información adicional
            return [
                'id' => $venta->id,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'cliente_nombre' => $venta->cliente->razon_social ?? ($venta->cliente->nombres . ' ' . $venta->cliente->apellidos) ?? 'Sin cliente',
                'total' => (float) $totalVenta,
                'created_at' => $venta->created_at,
                'pagos' => $pagos->map(function ($pago) {
                    return [
                        'sub_caja' => $pago->sub_caja ?? 'N/A',
                        'metodo_pago' => "{$pago->banco}/{$pago->metodo_pago}",
                        'monto' => (float) $pago->monto,
                        'numero_operacion' => $pago->numero_operacion,
                    ];
                })->toArray(),
            ];
        });
    }
}
