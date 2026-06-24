<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\DashboardContableServiceInterface;
use Illuminate\Support\Facades\DB;

class DashboardContableService implements DashboardContableServiceInterface
{
    private const IMPORTE = 'udiv.precio * udiv.cantidad';
    // pav.costo/pa.costo están por unidad base (fracción); udiv.cantidad está en
    // unidades derivadas, por eso se multiplica también por udiv.factor. Sin el
    // ×factor el costo queda subvaluado y la ganancia inflada con unidades derivadas.
    private const COSTO   = '(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) * udiv.cantidad * udiv.factor';

    public function porcentajeGananciasPorMes(array $filtros): array
    {
        $query = $this->baseVentasQuery($filtros);

        $rows = $query->selectRaw(
            "DATE_FORMAT(v.fecha, '%Y-%m') as mes,
             SUM(" . self::IMPORTE . ") as ventas,
             SUM(" . self::COSTO . ") as costo"
        )->groupBy('mes')->orderBy('mes')->get();

        return $rows->map(function ($r) {
            $ventas = (float) $r->ventas;
            $costo = (float) $r->costo;
            $pct = $ventas > 0 ? round((($ventas - $costo) / $ventas) * 100, 2) : 0.0;
            return ['label' => $this->labelMes($r->mes), 'value' => $pct];
        })->toArray();
    }

    public function cierresCajaConPerdidaPorMes(array $filtros): array
    {
        $query = DB::table('apertura_cierre_caja')
            ->whereNotNull('fecha_cierre')
            ->where('diferencia_total', '<', 0);

        if (!empty($filtros['desde'])) {
            $query->whereDate('fecha_cierre', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('fecha_cierre', '<=', $filtros['hasta']);
        }

        $rows = $query->selectRaw("DATE_FORMAT(fecha_cierre, '%Y-%m') as mes, SUM(ABS(diferencia_total)) as value")
            ->groupBy('mes')->orderBy('mes')->get();

        return $rows->map(fn($r) => [
            'label' => $this->labelMes($r->mes),
            'value' => round((float) $r->value, 2),
        ])->toArray();
    }

    public function clientesMorosos(array $filtros, int $limit = 10): array
    {
        // Deuda VENCIDA: ventas a crédito, vencidas (fecha_vencimiento < hoy) y con
        // saldo > 0 (importe vendido - pagos). Es un estado actual: no se filtra por
        // el rango de fechas del dashboard.
        $query = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.forma_de_pago', 'cr')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999')
            ->whereNotNull('v.fecha_vencimiento')
            ->whereDate('v.fecha_vencimiento', '<', now()->toDateString());

        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }

        $saldoExpr = "SUM(
            (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
             JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
             WHERE pav2.venta_id = v.id)
        ) - SUM(
            (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id)
        )";

        $rows = $query->selectRaw(
            "CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as label,
             ($saldoExpr) as value"
        )
            ->groupBy('c.id', 'c.tipo_cliente', 'c.razon_social', 'c.nombres', 'c.apellidos')
            ->havingRaw("($saldoExpr) > 0")
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'label' => $r->label,
            'value' => round((float) $r->value, 2),
        ])->toArray();
    }

    public function gananciasPorRecomendador(array $filtros, int $limit = 10): array
    {
        $query = $this->baseVentasQuery($filtros)
            ->join('cliente as rec', 'v.recomendado_por_id', '=', 'rec.id')
            ->whereNotNull('v.recomendado_por_id');

        $rows = $query->selectRaw(
            "CASE WHEN rec.tipo_cliente = 'e' THEN rec.razon_social ELSE CONCAT(rec.nombres, ' ', rec.apellidos) END as label,
             SUM(" . self::IMPORTE . ") - SUM(" . self::COSTO . ") as value"
        )
            ->groupBy('rec.id', 'rec.tipo_cliente', 'rec.razon_social', 'rec.nombres', 'rec.apellidos')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'label' => $r->label,
            'value' => round((float) $r->value, 2),
        ])->toArray();
    }

    public function resumenCards(array $filtros): array
    {
        // Ganancias del periodo (ventas - costo).
        $g = $this->baseVentasQuery($filtros)
            ->selectRaw('SUM(' . self::IMPORTE . ') - SUM(' . self::COSTO . ') as ganancia')
            ->value('ganancia');

        // Capital = valorización del inventario (stock * costo) del almacén. Punto en el tiempo.
        $capQ = DB::table('productoalmacen as pa');
        if (!empty($filtros['almacen_id'])) {
            $capQ->where('pa.almacen_id', $filtros['almacen_id']);
        }
        $capital = (float) $capQ->selectRaw('SUM(pa.stock_fraccion * pa.costo) as t')->value('t');

        // Caja = saldo total de las sub-cajas.
        $caja = (float) DB::table('sub_cajas')->sum('saldo_actual');

        return [
            'ganancias'         => round((float) $g, 2),
            'capital'           => round($capital, 2),
            'ventas_por_cobrar' => $this->totalVentasPorCobrar($filtros),
            'compras_por_pagar' => $this->totalComprasPorPagar($filtros),
            'caja'              => round($caja, 2),
        ];
    }

    /** Saldo vigente de ventas a crédito (importe vendido - pagos). Estado actual. */
    private function totalVentasPorCobrar(array $filtros): float
    {
        $q = DB::table('venta as v')
            ->where('v.forma_de_pago', 'cr')
            ->where('v.estado_de_venta', '!=', 'an');
        if (!empty($filtros['almacen_id'])) {
            $q->where('v.almacen_id', $filtros['almacen_id']);
        }

        $total = $q->selectRaw("
            SUM((SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
                 JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
                 WHERE pav2.venta_id = v.id))
            - SUM((SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id)) as t
        ")->value('t');

        return round((float) $total, 2);
    }

    /** Saldo vigente de compras a crédito (total compra - pagos). Estado actual. */
    private function totalComprasPorPagar(array $filtros): float
    {
        $totalQ = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->join('compra as co', 'pac.compra_id', '=', 'co.id')
            ->where('co.forma_de_pago', 'cr')
            ->where('co.estado_de_compra', '!=', 'an');
        if (!empty($filtros['almacen_id'])) {
            $totalQ->where('co.almacen_id', $filtros['almacen_id']);
        }
        $totalCompras = (float) $totalQ->selectRaw(
            'SUM((CASE WHEN udic.bonificacion THEN 0 ELSE pac.costo * udic.cantidad * udic.factor END) + COALESCE(udic.flete, 0)) as t'
        )->value('t');

        $pagadoQ = DB::table('pagodecompra as p')
            ->join('compra as co', 'p.compra_id', '=', 'co.id')
            ->where('co.forma_de_pago', 'cr')
            ->where('co.estado_de_compra', '!=', 'an')
            ->where('p.estado', 1);
        if (!empty($filtros['almacen_id'])) {
            $pagadoQ->where('co.almacen_id', $filtros['almacen_id']);
        }
        $pagado = (float) $pagadoQ->sum('p.monto');

        return round($totalCompras - $pagado, 2);
    }

    /**
     * Base: venta → productoalmacenventa → unidadderivadainmutableventa, excluye
     * anuladas y aplica filtros de fecha (v.fecha) y almacén.
     */
    private function baseVentasQuery(array $filtros)
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->where('v.estado_de_venta', '!=', 'an');

        if (!empty($filtros['desde'])) {
            $query->whereDate('v.fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('v.fecha', '<=', $filtros['hasta']);
        }
        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }

        return $query;
    }

    private function labelMes(string $mes): string
    {
        [$anio, $m] = array_pad(explode('-', $mes), 2, '01');
        $nombres = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return ($nombres[(int) $m] ?? $m) . ' ' . $anio;
    }
}
