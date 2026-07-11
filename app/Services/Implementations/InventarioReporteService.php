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
        if (!empty($filtros['categoria_id'])) {
            $query->where('p.categoria_id', $filtros['categoria_id']);
        }
        if (!empty($filtros['marca_id'])) {
            $query->where('p.marca_id', $filtros['marca_id']);
        }
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.cod_producto', 'like', "%{$search}%");
            });
        }
        // Filtro de stock: misma semántica que la lista (ProductoRepository::applyStockFilter).
        // con_stock => stock_fraccion > 0; sin_stock => stock_fraccion <= 0; all/omitido => sin filtro.
        if (!empty($filtros['cs_stock']) && $filtros['cs_stock'] !== 'all') {
            if ($filtros['cs_stock'] === 'con_stock') {
                $query->where('pa.stock_fraccion', '>', 0);
            } elseif ($filtros['cs_stock'] === 'sin_stock') {
                $query->where('pa.stock_fraccion', '<=', 0);
            }
        }

        $resumen = $query->selectRaw("
            COUNT(DISTINCT p.id) as total_productos,
            SUM(pa.stock_fraccion) as total_stock,
            SUM(pa.stock_fraccion * pa.costo) as valorizacion_total,
            SUM(pa.stock_fraccion * COALESCE((
                SELECT ud.precio_publico
                FROM productoalmacenunidadderivada ud
                WHERE ud.producto_almacen_id = pa.id
                ORDER BY ABS(ud.factor - 1), ud.id
                LIMIT 1
            ), 0) / NULLIF(COALESCE((
                SELECT ud.factor
                FROM productoalmacenunidadderivada ud
                WHERE ud.producto_almacen_id = pa.id
                ORDER BY ABS(ud.factor - 1), ud.id
                LIMIT 1
            ), 1), 0)) as valorizacion_venta,
            SUM(CASE WHEN pa.stock_fraccion <= 0 THEN 1 ELSE 0 END) as productos_sin_stock,
            SUM(CASE WHEN pa.stock_fraccion > 0 AND pa.stock_fraccion < p.stock_min THEN 1 ELSE 0 END) as productos_stock_bajo
        ")->first();

        return [
            'total_productos' => (int)($resumen->total_productos ?? 0),
            'total_stock' => round((float)($resumen->total_stock ?? 0), 2),
            'valorizacion_total' => round((float)($resumen->valorizacion_total ?? 0), 2),
            'valorizacion_venta' => round((float)($resumen->valorizacion_venta ?? 0), 2),
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

    /**
     * Demanda (unidades vendidas) por categoría. Usa udiv para las cantidades
     * reales y agrupa por la categoría del producto.
     */
    public function obtenerDemandaPorCategoria(array $filtros, int $limit = 10): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('categoria as c', 'p.categoria_id', '=', 'c.id')
            ->where('v.estado_de_venta', '!=', 'an');

        if (!empty($filtros['almacen_id'])) {
            $query->where('pa.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'v.fecha');

        $rows = $query->selectRaw("COALESCE(c.name, 'Sin categoría') as label, SUM(udiv.cantidad) as value")
            ->groupBy('c.name')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'label' => $r->label,
            'value' => round((float) $r->value, 2),
        ])->toArray();
    }

    /**
     * Costo de ajuste de inventario: suma del costo de los ingresos/salidas
     * manuales (ajustes) en el periodo. costo vive en productoalmaceningresosalida.
     */
    public function obtenerCostoAjuste(array $filtros): float
    {
        $query = DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as iss', 'pais.ingreso_id', '=', 'iss.id')
            ->where('iss.estado', 1);

        if (!empty($filtros['almacen_id'])) {
            $query->where('iss.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'iss.fecha');

        $total = $query->selectRaw('SUM(udis.cantidad * pais.costo) as total')->value('total');

        return round((float) $total, 2);
    }

    /**
     * Productos rotados: cantidad de productos distintos con al menos una venta
     * en el periodo, y el total de productos del almacén.
     */
    public function obtenerProductosRotados(array $filtros): array
    {
        $rotadosQuery = DB::table('productoalmacenventa as pav')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->where('v.estado_de_venta', '!=', 'an');

        if (!empty($filtros['almacen_id'])) {
            $rotadosQuery->where('pa.almacen_id', $filtros['almacen_id']);
        }
        $this->aplicarFiltrosFecha($rotadosQuery, $filtros, 'v.fecha');

        $rotados = $rotadosQuery->distinct()->count('pa.producto_id');

        $totalQuery = DB::table('productoalmacen as pa');
        if (!empty($filtros['almacen_id'])) {
            $totalQuery->where('pa.almacen_id', $filtros['almacen_id']);
        }
        $total = $totalQuery->distinct()->count('pa.producto_id');

        return ['rotados' => (int) $rotados, 'total' => (int) $total];
    }

    /**
     * Valorización del inventario al inicio (1/ene) y fin (31/dic) del año,
     * reconstruida desde el kardex (último saldo por producto a cada fecha).
     */
    public function obtenerInventarioPorAnio(array $filtros): array
    {
        $anio = (int) ($filtros['anio'] ?? date('Y'));
        $almacenId = !empty($filtros['almacen_id']) ? (int) $filtros['almacen_id'] : null;

        return [
            'inicial' => $this->valorizacionAFecha(sprintf('%04d-01-01 00:00:00', $anio), $almacenId, '<'),
            'final'   => $this->valorizacionAFecha(sprintf('%04d-12-31 23:59:59', $anio), $almacenId, '<='),
        ];
    }

    /**
     * Suma de stock_actual * costo_actual usando el ÚLTIMO movimiento de kardex
     * por producto_almacen a la fecha dada.
     */
    private function valorizacionAFecha(string $fechaLimite, ?int $almacenId, string $operador): float
    {
        $sub = DB::table('kardex_inventarios')
            ->selectRaw('stock_actual, costo_actual, ROW_NUMBER() OVER (PARTITION BY producto_almacen_id ORDER BY fecha DESC, orden DESC, id DESC) as rn')
            ->where('fecha', $operador, $fechaLimite);

        if ($almacenId) {
            $sub->where('almacen_id', $almacenId);
        }

        // Solo cuenta stock positivo: un stock negativo (sobreventa / kardex
        // incompleto) es un error de data, no un valor negativo de inventario.
        $total = DB::query()->fromSub($sub, 't')
            ->where('t.rn', 1)
            ->selectRaw('SUM(CASE WHEN t.stock_actual > 0 THEN t.stock_actual * t.costo_actual ELSE 0 END) as total')
            ->value('total');

        return round((float) $total, 2);
    }

    /**
     * Productos sin rotar: con stock > 0 pero SIN ninguna venta en el periodo.
     */
    public function obtenerProductosSinRotar(array $filtros, int $perPage = 100, int $page = 1): array
    {
        // Subconsulta: producto_id que SÍ tuvieron venta en el periodo.
        $vendidos = DB::table('productoalmacenventa as pav')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('productoalmacen as pa2', 'pav.producto_almacen_id', '=', 'pa2.id')
            ->where('v.estado_de_venta', '!=', 'an')
            ->select('pa2.producto_id');
        if (!empty($filtros['almacen_id'])) {
            $vendidos->where('pa2.almacen_id', $filtros['almacen_id']);
        }
        $this->aplicarFiltrosFecha($vendidos, $filtros, 'v.fecha');

        $query = DB::table('productoalmacen as pa')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('categoria as c', 'p.categoria_id', '=', 'c.id')
            ->where('pa.stock_fraccion', '>', 0)
            ->whereNotIn('pa.producto_id', $vendidos);
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
            'pa.costo as costo_unitario',
        ])->orderByDesc('pa.stock_fraccion');

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data->toArray(),
            'current_page' => $page,
            'last_page' => (int) ceil($total / max($perPage, 1)),
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
