<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\InventarioReporteServiceInterface;
use Illuminate\Support\Facades\DB;

class InventarioReporteService implements InventarioReporteServiceInterface
{
    /**
     * Top productos por ventas (importe), utilidad o recurrencia.
     * Los precios reales están en unidadderivadainmutableventa (udiv), NO en productoalmacenventa.
     */
    public function obtenerTopProductos(array $filtros, string $tipo = 'ventas', int $limit = 20): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->where('v.estado_de_venta', '!=', 'an');

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'v.fecha');

        $query->groupBy('p.id', 'p.name', 'p.cod_producto', 'm.name');

        switch ($tipo) {
            case 'utilidad':
                $query->selectRaw("
                    p.id,
                    p.cod_producto,
                    p.name as producto,
                    m.name as marca,
                    SUM(udiv.precio * udiv.cantidad) as importe_venta,
                    SUM(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END * udiv.cantidad) as importe_costo,
                    SUM(udiv.precio * udiv.cantidad) - SUM(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END * udiv.cantidad) as importe
                ");
                $query->orderByDesc('importe');
                break;

            case 'recurrencia':
                $query->selectRaw("
                    p.id,
                    p.cod_producto,
                    p.name as producto,
                    m.name as marca,
                    COUNT(DISTINCT pav.venta_id) as importe
                ");
                $query->orderByDesc('importe');
                break;

            default: // ventas
                $query->selectRaw("
                    p.id,
                    p.cod_producto,
                    p.name as producto,
                    m.name as marca,
                    SUM(udiv.precio * udiv.cantidad) as importe
                ");
                $query->orderByDesc('importe');
                break;
        }

        $results = $query->limit($limit)->get();

        return $results->map(function ($item) {
            return [
                'id' => $item->id,
                'cod_producto' => $item->cod_producto,
                'producto' => $item->producto,
                'marca' => $item->marca,
                'importe' => round((float)$item->importe, 2),
            ];
        })->toArray();
    }

    /**
     * Resumen KPI de inventario
     */
    public function obtenerResumenInventario(array $filtros): array
    {
        $query = DB::table('productoalmacen as pa')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id');

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }

        $resumen = $query->selectRaw("
            COUNT(DISTINCT p.id) as total_productos,
            SUM(pa.stock_fraccion) as total_stock,
            SUM(pa.stock_fraccion * pa.costo) as valorizacion_total,
            SUM(CASE WHEN pa.stock_fraccion <= 0 THEN 1 ELSE 0 END) as productos_sin_stock,
            SUM(CASE WHEN pa.stock_fraccion > 0 AND pa.stock_fraccion < p.stock_min THEN 1 ELSE 0 END) as productos_stock_bajo
        ")->first();

        return [
            'total_productos' => (int)($resumen->total_productos ?? 0),
            'total_stock' => round((float)($resumen->total_stock ?? 0), 2),
            'valorizacion_total' => round((float)($resumen->valorizacion_total ?? 0), 2),
            'productos_sin_stock' => (int)($resumen->productos_sin_stock ?? 0),
            'productos_stock_bajo' => (int)($resumen->productos_stock_bajo ?? 0),
        ];
    }

    /**
     * Stock valorizado
     */
    public function obtenerStockValorizado(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = $this->buildStockQuery($filtros);

        $query->select([
            'p.id',
            'p.cod_producto',
            'p.name as producto',
            'm.name as marca',
            'c.name as categoria',
            'um.name as unidad_medida',
            'pa.stock_fraccion as stock',
            'pa.costo as costo_unitario',
            DB::raw('ROUND(pa.stock_fraccion * pa.costo, 2) as valor_total'),
        ]);

        $query->orderByDesc('valor_total');

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        $totalesQuery = $this->buildStockQuery($filtros);
        $totales = $totalesQuery->selectRaw("
            SUM(pa.stock_fraccion) as total_stock,
            SUM(pa.stock_fraccion * pa.costo) as total_valorizado
        ")->first();

        return [
            'data' => $data->toArray(),
            'resumen' => [
                'total_stock' => round((float)($totales->total_stock ?? 0), 2),
                'total_valorizado' => round((float)($totales->total_valorizado ?? 0), 2),
            ],
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Productos con stock bajo
     */
    public function obtenerProductosStockBajo(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = DB::table('productoalmacen as pa')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('categoria as c', 'p.categoria_id', '=', 'c.id')
            ->where('pa.stock_fraccion', '>', 0)
            ->where('pa.stock_fraccion', '<', DB::raw('p.stock_min'))
            ->where('p.stock_min', '>', 0);

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }

        $query->select([
            'p.id',
            'p.cod_producto',
            'p.name as producto',
            'm.name as marca',
            'c.name as categoria',
            'pa.stock_fraccion as stock',
            'p.stock_min',
            'p.stock_max',
            'pa.costo as costo_unitario',
        ]);

        $query->orderBy('pa.stock_fraccion');

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data->toArray(),
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Cantidades vendidas por producto.
     * Usa udiv para cantidades y precios reales.
     */
    public function obtenerCantidadesVendidas(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('unidadmedida as um', 'p.unidad_medida_id', '=', 'um.id')
            ->where('v.estado_de_venta', '!=', 'an');

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'v.fecha');

        $query->selectRaw("
            p.id,
            p.cod_producto,
            p.name as producto,
            m.name as marca,
            um.name as unidad_medida,
            SUM(udiv.cantidad) as cantidad_vendida,
            SUM(udiv.precio * udiv.cantidad) as importe_venta,
            COUNT(DISTINCT pav.venta_id) as num_ventas
        ");

        $query->groupBy('p.id', 'p.cod_producto', 'p.name', 'm.name', 'um.name');
        $query->orderByDesc('cantidad_vendida');

        $totalQuery = clone $query;
        $total = DB::table(DB::raw("({$totalQuery->toSql()}) as sub"))
            ->mergeBindings($totalQuery)
            ->count();

        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data->toArray(),
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    private function buildStockQuery(array $filtros)
    {
        $query = DB::table('productoalmacen as pa')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('categoria as c', 'p.categoria_id', '=', 'c.id')
            ->leftJoin('unidadmedida as um', 'p.unidad_medida_id', '=', 'um.id');

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }
        if (!empty($filtros['categoria_id'])) {
            $query->where('p.categoria_id', $filtros['categoria_id']);
        }
        if (!empty($filtros['marca_id'])) {
            $query->where('p.marca_id', $filtros['marca_id']);
        }
        if (!empty($filtros['con_stock'])) {
            $query->where('pa.stock_fraccion', '>', 0);
        }

        return $query;
    }

    private function aplicarFiltrosFecha($query, array $filtros, string $campo): void
    {
        if (!empty($filtros['desde'])) {
            $query->whereDate($campo, '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate($campo, '<=', $filtros['hasta']);
        }
    }
}
