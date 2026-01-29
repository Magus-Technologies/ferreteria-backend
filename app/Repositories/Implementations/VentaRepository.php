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

        // Obtener el día de la apertura (sin hora)
        $fechaApertura = \Carbon\Carbon::parse($apertura->fecha_apertura);
        $inicioDia = $fechaApertura->copy()->startOfDay();
        $finDia = $apertura->fecha_cierre 
            ? \Carbon\Carbon::parse($apertura->fecha_cierre)
            : $fechaApertura->copy()->endOfDay();

        $ventas = Venta::with(['cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social'])
            ->where('user_id', $apertura->user_id)
            ->whereBetween('fecha', [$inicioDia, $finDia])
            ->get();

        return $ventas;
    }
}
