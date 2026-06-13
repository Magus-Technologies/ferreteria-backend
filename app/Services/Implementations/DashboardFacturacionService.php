<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\DashboardFacturacionServiceInterface;
use Illuminate\Support\Facades\DB;

class DashboardFacturacionService implements DashboardFacturacionServiceInterface
{
    /**
     * Importe real de venta. Los precios/cantidades viven en
     * unidadderivadainmutableventa (udiv), no en productoalmacenventa.
     */
    private const IMPORTE = 'udiv.precio * udiv.cantidad';

    public function obtenerResumen(array $filtros): array
    {
        // Importe total y n° de ventas (sobre productos, excluyendo anuladas).
        $base = $this->baseProductosQuery($filtros);
        $totales = $base->selectRaw(
            'SUM(' . self::IMPORTE . ') as importe, COUNT(DISTINCT v.id) as num_ventas'
        )->first();

        // Importe y n° de comprobantes por tipo de documento en una sola pasada.
        $porDoc = $this->baseProductosQuery($filtros)
            ->selectRaw('v.tipo_documento as tipo, SUM(' . self::IMPORTE . ') as importe, COUNT(DISTINCT v.id) as num')
            ->groupBy('v.tipo_documento')
            ->get()
            ->keyBy('tipo');

        return [
            'total_ventas'   => round((float) ($totales->importe ?? 0), 2),
            'num_ventas'     => (int) ($totales->num_ventas ?? 0),
            'total_facturas' => round((float) ($porDoc->get('01')->importe ?? 0), 2),
            'num_facturas'   => (int) ($porDoc->get('01')->num ?? 0),
            'total_boletas'  => round((float) ($porDoc->get('03')->importe ?? 0), 2),
            'num_boletas'    => (int) ($porDoc->get('03')->num ?? 0),
            'total_notas'    => round((float) ($porDoc->get('nv')->importe ?? 0), 2),
            'num_notas'      => (int) ($porDoc->get('nv')->num ?? 0),
        ];
    }

    public function ventasPorCategoria(array $filtros, int $limit = 10): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->leftJoin('categoria as c', 'p.categoria_id', '=', 'c.id')
            ->selectRaw("COALESCE(c.name, 'Sin categoría') as label, SUM(" . self::IMPORTE . ") as value")
            ->groupBy('c.name')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $this->mapLabelValue($rows);
    }

    public function ventasPorMarca(array $filtros, int $limit = 12): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->selectRaw("COALESCE(m.name, 'Sin marca') as label, SUM(" . self::IMPORTE . ") as value")
            ->groupBy('m.name')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $this->mapLabelValue($rows);
    }

    public function ventasPorMetodoPago(array $filtros): array
    {
        $query = DB::table('desplieguedepagoventa as ddpv')
            ->join('venta as v', 'ddpv.venta_id', '=', 'v.id')
            ->leftJoin('desplieguedepago as ddp', 'ddpv.despliegue_de_pago_id', '=', 'ddp.id')
            ->where('v.estado_de_venta', '!=', 'an');

        $this->aplicarFiltros($query, $filtros);

        $rows = $query
            ->selectRaw("COALESCE(ddp.name, 'Otro') as label, SUM(ddpv.monto) as value")
            ->groupBy('ddp.name')
            ->orderByDesc('value')
            ->get();

        return $this->mapLabelValue($rows);
    }

    public function productosMasVendidos(array $filtros, int $limit = 10): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->selectRaw("p.name as label, SUM(" . self::IMPORTE . ") as value")
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $this->mapLabelValue($rows);
    }

    public function ventasPorTipoDocumento(array $filtros): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->selectRaw('v.tipo_documento as tipo, SUM(' . self::IMPORTE . ') as value, COUNT(DISTINCT v.id) as num_ventas')
            ->groupBy('v.tipo_documento')
            ->get();

        $nombres = ['01' => 'Facturas', '03' => 'Boletas', 'nv' => 'Notas de Venta'];

        return $rows->map(fn($r) => [
            'label'      => $nombres[$r->tipo] ?? $r->tipo,
            'value'      => round((float) $r->value, 2),
            'num_ventas' => (int) $r->num_ventas,
        ])->toArray();
    }

    public function ingresosPorCanal(array $filtros): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->selectRaw('v.canal as canal, SUM(' . self::IMPORTE . ') as value, COUNT(DISTINCT v.id) as pedidos')
            ->groupBy('v.canal')
            ->get();

        $nombres = ['presencial' => 'Presencial', 'web' => 'Web'];

        return $rows->map(fn($r) => [
            'label'   => $nombres[$r->canal] ?? ($r->canal ?? 'Presencial'),
            'value'   => round((float) $r->value, 2),
            'pedidos' => (int) $r->pedidos,
        ])->toArray();
    }

    public function ingresosPorTipoDespacho(array $filtros): array
    {
        $rows = $this->baseProductosQuery($filtros)
            ->selectRaw('v.tipo_despacho as tipo, SUM(' . self::IMPORTE . ') as value, COUNT(DISTINCT v.id) as pedidos')
            ->groupBy('v.tipo_despacho')
            ->get();

        $nombres = ['et' => 'En Tienda', 'do' => 'Domicilio', 'pa' => 'Parcial'];

        return $rows->map(fn($r) => [
            'label'   => $nombres[$r->tipo] ?? ($r->tipo ?? 'Sin despacho'),
            'value'   => round((float) $r->value, 2),
            'pedidos' => (int) $r->pedidos,
        ])->toArray();
    }

    /**
     * Query base sobre los productos vendidos: venta → productoalmacenventa →
     * unidadderivadainmutableventa → producto. Excluye ventas anuladas y aplica
     * los filtros de fecha/almacén.
     */
    private function baseProductosQuery(array $filtros)
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->where('v.estado_de_venta', '!=', 'an');

        $this->aplicarFiltros($query, $filtros);

        return $query;
    }

    /** Filtros comunes: fecha (desde/hasta) y almacén de la venta. */
    private function aplicarFiltros($query, array $filtros): void
    {
        if (!empty($filtros['desde'])) {
            $query->whereDate('v.fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('v.fecha', '<=', $filtros['hasta']);
        }
        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }
    }

    private function mapLabelValue($rows): array
    {
        return $rows->map(fn($r) => [
            'label' => $r->label,
            'value' => round((float) $r->value, 2),
        ])->toArray();
    }
}
