<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use Illuminate\Support\Facades\DB;

class EntregaResumenService
{
    /**
     * Devuelve el resumen de entregas agrupado por venta.
     * Usado para poblar la tabla maestra en el frontend.
     *
     * @return array<string, array{
     *   total: int, completadas: int, en_camino: int,
     *   pendientes: int, canceladas: int, proxima_fecha: string|null
     * }>
     */
    public function resumenPorVenta(array $ventaIds): array
    {
        if (empty($ventaIds)) {
            return [];
        }

        $rows = DB::table('entrega as e')
            ->join('estado_entrega as ee', 'ee.id', '=', 'e.estado_entrega_id')
            ->whereIn('e.venta_id', $ventaIds)
            ->groupBy('e.venta_id')
            ->select([
                'e.venta_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN ee.codigo = 'en' THEN 1 ELSE 0 END) as completadas"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'ec' THEN 1 ELSE 0 END) as en_camino"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'pe' THEN 1 ELSE 0 END) as pendientes"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'ca' THEN 1 ELSE 0 END) as canceladas"),
                DB::raw('MIN(e.fecha_programada) as proxima_fecha'),
            ])
            ->get();

        return $rows->keyBy('venta_id')->map(fn ($r) => [
            'total'       => (int) $r->total,
            'completadas' => (int) $r->completadas,
            'en_camino'   => (int) $r->en_camino,
            'pendientes'  => (int) $r->pendientes,
            'canceladas'  => (int) $r->canceladas,
            'proxima_fecha' => $r->proxima_fecha,
        ])->all();
    }

    /**
     * Devuelve el resumen de una sola venta.
     */
    public function resumenDeVenta(string $ventaId): array
    {
        $resultado = $this->resumenPorVenta([$ventaId]);
        return $resultado[$ventaId] ?? [
            'total' => 0, 'completadas' => 0, 'en_camino' => 0,
            'pendientes' => 0, 'canceladas' => 0, 'proxima_fecha' => null,
        ];
    }

    /**
     * Calcula la próxima secuencia de entrega para una venta.
     */
    public function siguienteSecuencia(string $ventaId): int
    {
        $max = Entrega::where('venta_id', $ventaId)->max('venta_entrega_secuencia');
        return ($max ?? 0) + 1;
    }
}
