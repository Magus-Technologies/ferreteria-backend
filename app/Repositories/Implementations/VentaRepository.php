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
        // Obtener la apertura para saber el usuario y las fechas
        $apertura = DB::table('apertura_cierre_caja')
            ->where('id', $aperturaId)
            ->first(['user_id', 'fecha_apertura', 'fecha_cierre']);

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
