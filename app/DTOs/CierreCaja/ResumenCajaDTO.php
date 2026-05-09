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
        public Collection $prestamosVendedores = new Collection(),
        public Collection $resumenBancos = new Collection(),
        public float $totalIngresosExtras = 0,
        public float $totalGastosExtras = 0,
        public Collection $detalleIngresosExtras = new Collection(),
        public Collection $detalleGastosExtras = new Collection(),
        public Collection $trasladosBoveda = new Collection(),
        public float $totalTrasladosBoveda = 0
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
            'resumen_bancos' => $this->resumenBancos,
            'total_ingresos_extras' => (float) $this->totalIngresosExtras,
            'total_gastos_extras' => (float) $this->totalGastosExtras,
            'detalle_ingresos_extras' => $this->detalleIngresosExtras,
            'detalle_gastos_extras' => $this->detalleGastosExtras,
            'traslados_boveda' => $this->trasladosBoveda,
            'total_traslados_boveda' => (float) $this->totalTrasladosBoveda,
        ];
    }
}
