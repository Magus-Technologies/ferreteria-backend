<?php

namespace App\Repositories\Implementations;

use App\Models\Venta;
use App\Repositories\Interfaces\VentaRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VentaRepository implements VentaRepositoryInterface
{
    public function obtenerPorApertura(string $aperturaId): Collection
    {
        // Obtener la apertura con el user_id que aperturó la caja
        $apertura = DB::table('apertura_cierre_caja')
            ->where('id', $aperturaId)
            ->first(['user_id', 'fecha_apertura', 'fecha_cierre']);

        if (!$apertura) {
            return collect([]);
        }

        // Usar la hora exacta de apertura (no inicio del día)
        $fechaApertura = \Carbon\Carbon::parse($apertura->fecha_apertura);
        $inicioDia = $fechaApertura;
        // Limitar a 12 horas máximo desde la apertura
        $limiteMaximo = $fechaApertura->copy()->addHours(12);
        $finDia = $apertura->fecha_cierre 
            ? \Carbon\Carbon::parse($apertura->fecha_cierre)
            : $limiteMaximo;

        $ventas = Venta::with(['cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social'])
            ->where('user_id', $apertura->user_id)
            ->whereBetween('fecha', [$inicioDia, $finDia])
            ->get();

        return $ventas;
    }
}
