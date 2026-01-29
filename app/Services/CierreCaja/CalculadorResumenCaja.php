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

    public function calcular(AperturaCierreCaja $apertura): ResumenCajaDTO
    {
        \Log::info('🔍 CalculadorResumenCaja::calcular - Inicio', [
            'apertura_id' => $apertura->id,
        ]);
        
        // Obtener ventas usando el repositorio existente
        $ventas = $this->ventaRepository->obtenerPorApertura($apertura->id);
        
        \Log::info('🔍 Ventas obtenidas del repositorio', [
            'total_ventas' => $ventas->count(),
            'ventas_ids' => $ventas->pluck('id')->toArray(),
            'ventas_user_ids' => $ventas->pluck('user_id')->unique()->toArray(),
        ]);
        
        // Consolidar información de todas las subcajas
        $clasificacion = $this->clasificador->clasificarPorTodasLasSubCajas($apertura->id, $ventas);

        \Log::info('🔍 Clasificación obtenida', [
            'efectivo_inicial' => $clasificacion['efectivo_inicial'],
            'ventas_count' => $clasificacion['ventas']->count(),
            'cobros_por_metodo_count' => $clasificacion['cobros_por_metodo']->count(),
            'cobros_por_metodo' => $clasificacion['cobros_por_metodo']->toArray(),
            'total_cobros' => $clasificacion['total_cobros'],
            'total_otros_ingresos' => $clasificacion['total_otros_ingresos'],
            'total_gastos' => $clasificacion['total_gastos'],
        ]);

        // FÓRMULA DEL CIERRE:
        // Total en Caja = Efectivo Inicial + Total Cobros + Otros Ingresos + Préstamos Recibidos - Gastos - Préstamos Dados
        // (Movimientos internos NO afectan el total)
        
        $montoEsperado = $clasificacion['efectivo_inicial']
                       + $clasificacion['resumen_ingresos'] 
                       - $clasificacion['resumen_egresos'];
        
        $montoCierre = $apertura->monto_cierre;
        $diferencia = $montoCierre !== null ? ($montoCierre - $montoEsperado) : null;

        // Formatear detalles de ventas con información de pagos
        $detalleVentas = $this->formatearDetalleVentas($clasificacion['ventas']);

        // Formatear detalles
        $detalleIngresos = $clasificacion['otros_ingresos']->mapWithKeys(function ($item) {
            return [$item->id => [
                'id' => $item->id,
                'tipo' => 'ingreso_manual',
                'monto' => number_format($item->monto, 2, '.', ''),
                'concepto' => $item->descripcion,
                'sub_caja' => $item->sub_caja,
                'created_at' => $item->created_at,
            ]];
        });

        $detalleEgresos = $clasificacion['gastos_y_pagos']->mapWithKeys(function ($item) {
            return [$item->id => [
                'id' => $item->id,
                'tipo' => $item->tipo,
                'monto' => number_format($item->monto, 2, '.', ''),
                'concepto' => $item->descripcion,
                'sub_caja' => $item->sub_caja,
                'created_at' => $item->created_at,
            ]];
        });

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
            prestamosVendedores: $clasificacion['prestamos_vendedores']
        );
        
        \Log::info('🔍 ResumenCajaDTO creado', [
            'total_ventas' => $resultado->totalVentas,
            'detalle_ventas_count' => $resultado->detalleVentas->count(),
            'detalle_metodos_pago_count' => $resultado->detalleMetodosPago->count(),
        ]);
        
        return $resultado;
    }

    /**
     * Formatear detalle de ventas con información de pagos por sub-caja
     */
    private function formatearDetalleVentas($ventas)
    {
        return $ventas->map(function ($venta) {
            // Obtener los pagos de esta venta con información de sub-caja
            $pagos = \Illuminate\Support\Facades\DB::table('desplieguedepagoventa as dpv')
                ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
                ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
                ->leftJoin('sub_cajas as sc', 'mp.subcaja_id', '=', 'sc.id')
                ->leftJoin('numeros_operacion_pago as nop', 'dpv.numero_operacion_id', '=', 'nop.id')
                ->where('dpv.venta_id', $venta->id)
                ->select([
                    'sc.nombre as sub_caja',
                    'mp.name as banco',
                    'dp.name as metodo_pago',
                    'dpv.monto',
                    'nop.numero_operacion'
                ])
                ->get();

            // Construir array de la venta con información adicional
            return [
                'id' => $venta->id,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'cliente_nombre' => $venta->cliente->razon_social ?? $venta->cliente->nombres . ' ' . $venta->cliente->apellidos ?? 'Sin cliente',
                'total' => $venta->total,
                'created_at' => $venta->created_at,
                'pagos' => $pagos->map(function ($pago) {
                    return [
                        'sub_caja' => $pago->sub_caja ?? 'N/A',
                        'metodo_pago' => "{$pago->banco}/{$pago->metodo_pago}",
                        'monto' => $pago->monto,
                        'numero_operacion' => $pago->numero_operacion,
                    ];
                })->toArray(),
            ];
        });
    }
}
