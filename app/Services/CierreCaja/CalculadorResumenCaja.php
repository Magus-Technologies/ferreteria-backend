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
        // Obtener datos
        $movimientos = $this->movimientosQuery->obtenerPorApertura($apertura->id);
        $ventas = $this->ventaRepository->obtenerPorApertura($apertura->id);

        // Clasificar
        $clasificacion = $this->clasificador->clasificar($movimientos, $ventas);

        // Calcular totales
        $totalIngresos = $clasificacion['ingresos']->sum('monto');
        $totalEgresos = $clasificacion['egresos']->sum('monto');
        $totalVentas = $clasificacion['ventas']->sum('total');

        $montoEsperado = $apertura->monto_apertura + $totalIngresos - $totalEgresos + $totalVentas;
        
        // Si la caja está abierta, monto_cierre será null
        $montoCierre = $apertura->monto_cierre;
        $diferencia = $montoCierre !== null ? ($montoCierre - $montoEsperado) : null;

        return new ResumenCajaDTO(
            montoApertura: $apertura->monto_apertura,
            totalIngresos: $totalIngresos,
            totalEgresos: $totalEgresos,
            totalVentas: $totalVentas,
            montoEsperado: $montoEsperado,
            montoCierre: $montoCierre,
            diferencia: $diferencia,
            detalleIngresos: $clasificacion['ingresos'],
            detalleEgresos: $clasificacion['egresos'],
            detalleVentas: $clasificacion['ventas'],
            detalleMetodosPago: $clasificacion['metodosPago']
        );
    }
}
