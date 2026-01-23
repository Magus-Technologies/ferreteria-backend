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
        
        // Consolidar información de todas las subcajas
        $clasificacion = $this->clasificador->clasificarPorTodasLasSubCajas($apertura->id, $ventas);

        // FÓRMULA DEL CIERRE:
        // Total en Caja = Apertura + Total Cobros + Otros Ingresos - Gastos - Pagos
        // (Movimientos internos y préstamos NO afectan el total)
        
        $montoEsperado = $apertura->monto_apertura 
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
            detalleMetodosPago: $clasificacion['cobros_por_metodo']
        );
    }
}
