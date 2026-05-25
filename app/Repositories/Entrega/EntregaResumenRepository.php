<?php

namespace App\Repositories\Entrega;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EntregaResumenRepository
{
    /**
     * Devuelve ventas con resumen de entregas para la tabla maestra.
     * Equivale a la vista v_resumen_entregas_por_venta aplicada con filtros.
     *
     * @param  array{
     *   fecha_desde?: string,
     *   fecha_hasta?: string,
     *   search?: string,
     *   solo_con_pendientes?: bool,
     *   chofer_id?: string,
     *   per_page?: int,
     * }  $filtros
     */
    public function listarConResumen(array $filtros): LengthAwarePaginator
    {
        $query = DB::table('venta as v')
            ->join('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin(
                DB::raw('(
                    SELECT
                        e.venta_id,
                        COUNT(*) as total_entregas,
                        SUM(CASE WHEN ee.codigo = \'en\' THEN 1 ELSE 0 END) as completadas,
                        SUM(CASE WHEN ee.codigo = \'ec\' THEN 1 ELSE 0 END) as en_camino,
                        SUM(CASE WHEN ee.codigo = \'pe\' THEN 1 ELSE 0 END) as pendientes,
                        SUM(CASE WHEN ee.codigo = \'ca\' THEN 1 ELSE 0 END) as canceladas,
                        MIN(e.fecha_programada) as proxima_fecha_programada,
                        MAX(e.fecha_ejecutada) as ultima_fecha_ejecutada
                    FROM entrega e
                    INNER JOIN estado_entrega ee ON ee.id = e.estado_entrega_id
                    GROUP BY e.venta_id
                ) as resumen'),
                'resumen.venta_id',
                '=',
                'v.id'
            )
            ->whereNotNull('resumen.venta_id') // solo ventas que tienen al menos una entrega
            ->select([
                'v.id as venta_id',
                'v.serie',
                'v.numero',
                'v.fecha',
                'c.razon_social',
                'c.nombres',
                'c.apellidos',
                'c.numero_documento',
                'c.telefono',
                'resumen.total_entregas',
                'resumen.completadas',
                'resumen.en_camino',
                'resumen.pendientes',
                'resumen.canceladas',
                'resumen.proxima_fecha_programada',
                'resumen.ultima_fecha_ejecutada',
            ]);

        // Filtros de fecha
        if (! empty($filtros['fecha_desde'])) {
            $query->where('v.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->where('v.fecha', '<=', $filtros['fecha_hasta']);
        }

        // Solo ventas con entregas pendientes
        if (! empty($filtros['solo_con_pendientes'])) {
            $query->where('resumen.pendientes', '>', 0);
        }

        // Solo ventas con entregas en camino (útil para pantalla de chofer)
        if (! empty($filtros['solo_en_camino'])) {
            $query->where('resumen.en_camino', '>', 0);
        }

        // Búsqueda de texto
        if (! empty($filtros['search'])) {
            $q = $filtros['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('v.serie', 'like', "%{$q}%")
                    ->orWhere('v.numero', 'like', "%{$q}%")
                    ->orWhere('c.razon_social', 'like', "%{$q}%")
                    ->orWhere('c.nombres', 'like', "%{$q}%")
                    ->orWhere('c.numero_documento', 'like', "%{$q}%");
            });
        }

        // Filtro por chofer (ventas que tienen al menos 1 entrega de ese chofer)
        if (! empty($filtros['chofer_id'])) {
            $choferVentaIds = DB::table('entrega')
                ->where('chofer_id', $filtros['chofer_id'])
                ->pluck('venta_id');
            $query->whereIn('v.id', $choferVentaIds);
        }

        $perPage = $filtros['per_page'] ?? 30;

        return $query
            ->orderByDesc('v.created_at')
            ->paginate($perPage);
    }

    /**
     * Resumen rápido de una sola venta (sin paginación).
     */
    public function resumenDeVenta(string $ventaId): ?object
    {
        return DB::table('entrega as e')
            ->join('estado_entrega as ee', 'ee.id', '=', 'e.estado_entrega_id')
            ->where('e.venta_id', $ventaId)
            ->select([
                DB::raw('COUNT(*) as total_entregas'),
                DB::raw("SUM(CASE WHEN ee.codigo = 'en' THEN 1 ELSE 0 END) as completadas"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'ec' THEN 1 ELSE 0 END) as en_camino"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'pe' THEN 1 ELSE 0 END) as pendientes"),
                DB::raw("SUM(CASE WHEN ee.codigo = 'ca' THEN 1 ELSE 0 END) as canceladas"),
                DB::raw('MIN(e.fecha_programada) as proxima_fecha_programada'),
            ])
            ->first();
    }
}
