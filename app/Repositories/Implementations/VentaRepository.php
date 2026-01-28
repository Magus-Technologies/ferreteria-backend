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
        // Obtener la apertura con el user_id de la caja principal
        $apertura = DB::table('apertura_cierre_caja as acc')
            ->join('cajas_principales as cp', 'acc.caja_principal_id', '=', 'cp.id')
            ->where('acc.id', $aperturaId)
            ->first(['cp.user_id', 'acc.fecha_apertura', 'acc.fecha_cierre']);

        if (!$apertura) {
            return collect([]);
        }

        $query = Venta::where('user_id', $apertura->user_id)
            ->where('fecha', '>=', $apertura->fecha_apertura);

        // Si hay fecha de cierre, filtrar hasta esa fecha
        if ($apertura->fecha_cierre) {
            $query->where('fecha', '<=', $apertura->fecha_cierre);
        }

        return $query->get();
    }
}
