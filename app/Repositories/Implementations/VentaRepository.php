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
        // Si hay fecha de cierre, usar esa; si no, usar la fecha/hora actual
        // (la apertura puede durar varios días según la operación del cliente)
        $finDia = $apertura->fecha_cierre 
            ? \Carbon\Carbon::parse($apertura->fecha_cierre)
            : now();

        // Obtener ventas de dos formas:
        // 1. Ventas con transacciones de caja en esta sub caja (método original)
        // 2. Ventas sin transacciones de caja pero dentro del rango de fechas (para ventas sin apertura)
        //
        // "Caja Chica" (sub_caja_id) es COMPARTIDA: varios vendedores pueden tener su
        // propia apertura abierta usando la MISMA sub_caja física. Filtrar solo por
        // sub_caja_id (sin user_id) hacía que la venta de UN vendedor apareciera en el
        // cierre de TODOS los demás que comparten esa sub-caja, duplicando el efectivo
        // esperado en cada uno. Se agrega el filtro por user_id de la transacción (quien
        // realmente procesó la venta) para que cada apertura solo vea lo suyo.
        // estado_de_venta != 'an' (Anulado) en AMBAS ramas: una venta anulada puede
        // quedarse sin transacciones_caja (se revierten al anular) y caer igual en la
        // rama "sin transacciones" de abajo si no se excluye explícitamente por estado.
        $ventasConTransacciones = Venta::with(['cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social'])
            ->whereIn('id', function($query) use ($apertura) {
                $query->select('referencia_id')
                    ->from('transacciones_caja')
                    ->where('referencia_tipo', 'venta')
                    ->where('sub_caja_id', $apertura->sub_caja_id)
                    ->where('user_id', $apertura->user_id);
            })
            ->whereBetween('fecha', [$inicioDia, $finDia])
            ->where('estado_de_venta', '!=', 'an')
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
            ->where('estado_de_venta', '!=', 'an')
            ->get();

        // Combinar ambas colecciones
        $ventas = $ventasConTransacciones->merge($ventasSinTransacciones);

        return $ventas;
    }
}
