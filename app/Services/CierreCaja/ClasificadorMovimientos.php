<?php

namespace App\Services\CierreCaja;

use Illuminate\Support\Collection;

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
