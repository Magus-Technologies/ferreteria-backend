<?php

namespace App\Queries\CierreCaja;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovimientosCajaQuery
{
    public function obtenerPorApertura(string $aperturaId): Collection
    {
        // Primero obtenemos la apertura para saber la sub_caja_id y las fechas
        $apertura = DB::table('apertura_cierre_caja')
            ->where('id', $aperturaId)
            ->first(['sub_caja_id', 'fecha_apertura', 'fecha_cierre']);

        if (!$apertura) {
            return collect([]);
        }

        $query = DB::table('transacciones_caja')
            ->where('sub_caja_id', $apertura->sub_caja_id)
            ->where('fecha', '>=', $apertura->fecha_apertura);

        // Si hay fecha de cierre, filtrar hasta esa fecha
        if ($apertura->fecha_cierre) {
            $query->where('fecha', '<=', $apertura->fecha_cierre);
        }

        return $query
            ->select([
                'id',
                'tipo_transaccion as tipo',
                'monto',
                'descripcion as concepto',
                'created_at'
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function obtenerDetalleCompleto(string $aperturaId): Collection
    {
        // Primero obtenemos la apertura para saber la sub_caja_id y las fechas
        $apertura = DB::table('apertura_cierre_caja')
            ->where('id', $aperturaId)
            ->first(['sub_caja_id', 'fecha_apertura', 'fecha_cierre']);

        if (!$apertura) {
            return collect([]);
        }

        $query = DB::table('transacciones_caja as tc')
            ->leftJoin('despliegues_pago as dp', 'tc.despliegue_pago_id', '=', 'dp.id')
            ->leftJoin('metodos_pago as mp', 'dp.metodo_pago_id', '=', 'mp.id')
            ->where('tc.sub_caja_id', $apertura->sub_caja_id)
            ->where('tc.fecha', '>=', $apertura->fecha_apertura);

        // Si hay fecha de cierre, filtrar hasta esa fecha
        if ($apertura->fecha_cierre) {
            $query->where('tc.fecha', '<=', $apertura->fecha_cierre);
        }

        return $query
            ->select([
                'tc.id',
                'tc.tipo_transaccion as tipo',
                'tc.monto',
                'tc.descripcion as concepto',
                'tc.created_at',
                'mp.nombre as metodo_pago'
            ])
            ->orderBy('tc.created_at')
            ->get();
    }
}
