<?php

namespace App\DTOs\CierreCaja;

use Illuminate\Support\Collection;

class ResumenCajaDTO
{
    public function __construct(
        public float $efectivoInicial,
        public float $montoApertura,
        public float $totalIngresos,
        public float $totalEgresos,
        public float $totalVentas,
        public float $montoEsperado,
        public ?float $montoCierre,
        public ?float $diferencia,
        public Collection $detalleIngresos,
        public Collection $detalleEgresos,
        public Collection $detalleVentas,
        public Collection $detalleMetodosPago,
        public Collection $prestamosRecibidos = new Collection(),
        public float $totalPrestamosRecibidos = 0,
        public Collection $prestamosDados = new Collection(),
        public float $totalPrestamosDados = 0,
        public Collection $movimientosInternos = new Collection(),
        public Collection $prestamos = new Collection(),
        public Collection $prestamosVendedores = new Collection()
    ) {}

    public function toArray(): array
    {
        return [
            'efectivo_inicial' => (float) $this->efectivoInicial,
            'monto_apertura' => (float) $this->montoApertura,
            'total_ingresos' => (float) $this->totalIngresos,
            'total_egresos' => (float) $this->totalEgresos,
            'total_ventas' => (float) $this->totalVentas,
            'monto_esperado' => (float) $this->montoEsperado,
            'monto_cierre' => $this->montoCierre !== null ? (float) $this->montoCierre : null,
            'diferencia' => $this->diferencia !== null ? (float) $this->diferencia : null,
            'detalle_ingresos' => $this->detalleIngresos,
            'detalle_egresos' => $this->detalleEgresos,
            'detalle_ventas' => $this->detalleVentas,
            'detalle_metodos_pago' => $this->detalleMetodosPago,
            'prestamos_recibidos' => $this->prestamosRecibidos,
            'total_prestamos_recibidos' => (float) $this->totalPrestamosRecibidos,
            'prestamos_dados' => $this->prestamosDados,
            'total_prestamos_dados' => (float) $this->totalPrestamosDados,
            'movimientos_internos' => $this->movimientosInternos,
            'prestamos' => $this->prestamos,
            'prestamos_vendedores' => $this->prestamosVendedores,
        ];
    }
}
