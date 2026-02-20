<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\CompraReporteServiceInterface;
use Illuminate\Support\Facades\DB;

class CompraReporteService implements CompraReporteServiceInterface
{
    public function obtenerResumenMensual(array $filtros): array
    {
        $query = DB::table('compra as c')
            ->join('productoalmacencompra as pac', 'c.id', '=', 'pac.compra_id')
            ->join('unidadderivadainmutablecompra as udic', 'pac.id', '=', 'udic.producto_almacen_compra_id')
            ->where('c.estado_de_compra', '!=', 'an')
            ->select([
                DB::raw("DATE_FORMAT(c.fecha, '%Y-%m') as mes"),
                DB::raw("SUM(CASE WHEN udic.bonificacion = 0 THEN pac.costo * udic.cantidad * udic.factor ELSE 0 END + udic.flete) as total"),
                DB::raw("COUNT(DISTINCT c.id) as cantidad"),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(c.fecha, '%Y-%m')"))
            ->orderBy('mes', 'asc');

        $this->aplicarFiltrosBase($query, $filtros);

        return $query->get()->toArray();
    }

    public function obtenerResumenCompras(array $filtros): array
    {
        $baseQuery = DB::table('compra as c')
            ->join('productoalmacencompra as pac', 'c.id', '=', 'pac.compra_id')
            ->join('unidadderivadainmutablecompra as udic', 'pac.id', '=', 'udic.producto_almacen_compra_id')
            ->where('c.estado_de_compra', '!=', 'an');

        $this->aplicarFiltrosBase($baseQuery, $filtros);

        $resumen = (clone $baseQuery)->selectRaw('
            SUM(CASE WHEN udic.bonificacion = 0 THEN pac.costo * udic.cantidad * udic.factor ELSE 0 END + udic.flete) as total_compras,
            COUNT(DISTINCT c.id) as total_transacciones,
            SUM(CASE WHEN c.forma_de_pago = "co" THEN (CASE WHEN udic.bonificacion = 0 THEN pac.costo * udic.cantidad * udic.factor ELSE 0 END + udic.flete) ELSE 0 END) as total_contado,
            SUM(CASE WHEN c.forma_de_pago = "cr" THEN (CASE WHEN udic.bonificacion = 0 THEN pac.costo * udic.cantidad * udic.factor ELSE 0 END + udic.flete) ELSE 0 END) as total_credito
        ')->first();

        // Total pagado desde pagodecompra
        $pagadoQuery = DB::table('compra as c')
            ->leftJoin('pagodecompra as pc', function ($join) {
                $join->on('c.id', '=', 'pc.compra_id')
                     ->where('pc.estado', '=', 1);
            })
            ->where('c.estado_de_compra', '!=', 'an');

        $this->aplicarFiltrosBaseSimple($pagadoQuery, $filtros);

        $pagado = $pagadoQuery->selectRaw('
            COALESCE(SUM(pc.monto), 0) as total_pagado
        ')->first();

        $totalCompras = round((float)($resumen->total_compras ?? 0), 2);
        $totalPagado = round((float)($pagado->total_pagado ?? 0), 2);

        return [
            'total_compras' => $totalCompras,
            'total_transacciones' => (int)($resumen->total_transacciones ?? 0),
            'total_contado' => round((float)($resumen->total_contado ?? 0), 2),
            'total_credito' => round((float)($resumen->total_credito ?? 0), 2),
            'total_pagado' => $totalPagado,
            'saldo_pendiente' => round($totalCompras - $totalPagado, 2),
        ];
    }

    public function obtenerReporteCompras(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = $this->construirQueryReporte($filtros);

        $total = (clone $query)->count();
        $datos = $query->offset(($page - 1) * $perPage)
                       ->limit($perPage)
                       ->get();

        return [
            'data' => $datos,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            ],
        ];
    }

    private function construirQueryReporte(array $filtros)
    {
        // Subconsulta para calcular total por compra
        $totalSubquery = DB::raw('(
            SELECT SUM(
                CASE WHEN udic2.bonificacion = 0 THEN pac2.costo * udic2.cantidad * udic2.factor ELSE 0 END + udic2.flete
            )
            FROM productoalmacencompra pac2
            JOIN unidadderivadainmutablecompra udic2 ON pac2.id = udic2.producto_almacen_compra_id
            WHERE pac2.compra_id = c.id
        )');

        // Subconsulta para total pagado
        $pagadoSubquery = DB::raw('(
            SELECT COALESCE(SUM(pc2.monto), 0)
            FROM pagodecompra pc2
            WHERE pc2.compra_id = c.id AND pc2.estado = 1
        )');

        $query = DB::table('compra as c')
            ->leftJoin('proveedor as prov', 'c.proveedor_id', '=', 'prov.id')
            ->leftJoin('user as u', 'c.user_id', '=', 'u.id')
            ->select([
                'c.id',
                DB::raw("DATE_FORMAT(c.fecha, '%d/%m/%Y') as fecha"),
                'c.tipo_documento',
                'c.serie',
                'c.numero',
                DB::raw("COALESCE(prov.ruc, '') as ruc"),
                DB::raw("COALESCE(prov.razon_social, '') as proveedor"),
                DB::raw("({$totalSubquery->getValue(DB::connection()->getQueryGrammar())}) as total"),
                'c.forma_de_pago',
                'c.estado_de_compra as estado',
                'c.tipo_moneda',
                'c.tipo_de_cambio',
                'c.percepcion',
                DB::raw("({$pagadoSubquery->getValue(DB::connection()->getQueryGrammar())}) as total_pagado"),
                DB::raw("COALESCE(u.name, '') as registrador"),
                'c.fecha as fecha_orden',
            ])
            ->where('c.estado_de_compra', '!=', 'an');

        $this->aplicarFiltrosBaseSimple($query, $filtros);

        $query->orderBy('c.fecha', 'desc');

        return $query;
    }

    private function aplicarFiltrosBase($query, array $filtros): void
    {
        if (!empty($filtros['almacen_id'])) {
            $query->where('c.almacen_id', $filtros['almacen_id']);
        }
        if (!empty($filtros['desde'])) {
            $query->whereDate('c.fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('c.fecha', '<=', $filtros['hasta']);
        }
        if (!empty($filtros['proveedor_id'])) {
            $query->where('c.proveedor_id', $filtros['proveedor_id']);
        }
        if (!empty($filtros['forma_de_pago'])) {
            $query->where('c.forma_de_pago', $filtros['forma_de_pago']);
        }
        if (!empty($filtros['tipo_documento'])) {
            $query->where('c.tipo_documento', $filtros['tipo_documento']);
        }
        if (!empty($filtros['user_id'])) {
            $query->where('c.user_id', $filtros['user_id']);
        }
    }

    private function aplicarFiltrosBaseSimple($query, array $filtros): void
    {
        if (!empty($filtros['almacen_id'])) {
            $query->where('c.almacen_id', $filtros['almacen_id']);
        }
        if (!empty($filtros['desde'])) {
            $query->whereDate('c.fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('c.fecha', '<=', $filtros['hasta']);
        }
        if (!empty($filtros['proveedor_id'])) {
            $query->where('c.proveedor_id', $filtros['proveedor_id']);
        }
        if (!empty($filtros['forma_de_pago'])) {
            $query->where('c.forma_de_pago', $filtros['forma_de_pago']);
        }
        if (!empty($filtros['tipo_documento'])) {
            $query->where('c.tipo_documento', $filtros['tipo_documento']);
        }
        if (!empty($filtros['user_id'])) {
            $query->where('c.user_id', $filtros['user_id']);
        }
    }
}
