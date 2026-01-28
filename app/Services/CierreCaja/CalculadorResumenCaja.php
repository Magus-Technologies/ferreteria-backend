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
        // Obtener ventas usando el repositorio existente
        $ventas = $this->ventaRepository->obtenerPorApertura($apertura->id);
        
        \Log::info('Ventas obtenidas', [
            'total_ventas' => $ventas->count(),
            'ventas_ids' => $ventas->pluck('id')->toArray()
        ]);
        
        // Consolidar información de todas las subcajas
        $clasificacion = $this->clasificador->clasificarPorTodasLasSubCajas($apertura->id, $ventas);

        \Log::info('Clasificación obtenida', [
            'efectivo_inicial' => $clasificacion['efectivo_inicial'],
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

        return new ResumenCajaDTO(
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
            detalleVentas: $clasificacion['ventas'],
            detalleMetodosPago: $clasificacion['cobros_por_metodo'],
            prestamosRecibidos: $clasificacion['prestamos_recibidos'],
            totalPrestamosRecibidos: $clasificacion['total_prestamos_recibidos'],
            prestamosDados: $clasificacion['prestamos_dados'],
            totalPrestamosDados: $clasificacion['total_prestamos_dados'],
            movimientosInternos: $clasificacion['movimientos_internos'],
            prestamos: $clasificacion['prestamos'],
            prestamosVendedores: $clasificacion['prestamos_vendedores']
        );
    }
}
