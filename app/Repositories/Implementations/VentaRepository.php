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
            ->first(['user_id', 'sub_caja_id', 'fecha_apertura', 'fecha_cierre']);

        if (!$apertura) {
            return collect([]);
        }

        // Usar la hora exacta de apertura (no inicio del día)
        $fechaApertura = \Carbon\Carbon::parse($apertura->fecha_apertura);
        $inicioDia = $fechaApertura;
        // Si hay fecha de cierre, usar esa; si no, usar 24 horas desde la apertura
        $finDia = $apertura->fecha_cierre 
            ? \Carbon\Carbon::parse($apertura->fecha_cierre)
            : $fechaApertura->copy()->addHours(24);

        // Obtener ventas de dos formas:
        // 1. Ventas con transacciones de caja en esta sub caja (método original)
        // 2. Ventas sin transacciones de caja pero dentro del rango de fechas (para ventas sin apertura)
        $ventasConTransacciones = Venta::with(['cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social'])
            ->whereIn('id', function($query) use ($apertura) {
                $query->select('referencia_id')
                    ->from('transacciones_caja')
                    ->where('referencia_tipo', 'venta')
                    ->where('sub_caja_id', $apertura->sub_caja_id);
            })
            ->whereBetween('fecha', [$inicioDia, $finDia])
            ->get();

        // Ventas sin transacciones de caja pero dentro del rango de fechas
        $ventasSinTransacciones = Venta::with(['cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social'])
            ->whereNotIn('id', function($query) use ($apertura) {
                $query->select('referencia_id')
                    ->from('transacciones_caja')
                    ->where('referencia_tipo', 'venta')
                    ->where('sub_caja_id', $apertura->sub_caja_id);
            })
            ->whereBetween('fecha', [$inicioDia, $finDia])
            ->where('user_id', $apertura->user_id)
            ->get();

        // Combinar ambas colecciones
        $ventas = $ventasConTransacciones->merge($ventasSinTransacciones);

        return $ventas;
    }
}
