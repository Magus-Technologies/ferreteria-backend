<?php

namespace App\Services\Implementations;

use App\Models\Venta;
use App\Models\ProductoAlmacenVenta;
use App\Services\Interfaces\GananciasServiceInterface;
use App\QueryFilters\GananciasQueryFilter;
use App\QueryBuilders\GananciasQueryBuilder;
use App\Helpers\ResumenHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class GananciasService implements GananciasServiceInterface
{
    /**
     * Obtener reporte detallado de ganancias
     */
    public function obtenerReporteGanancias(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = $this->construirQueryGanancias($filtros);

        // Obtener datos paginados
        $total = $query->count();
        $datos = $query->offset(($page - 1) * $perPage)
                      ->limit($perPage)
                      ->get();

        // Desglosar por lote: una venta que consumió varios costos (lotes PEPS)
        // se separa en una fila por costo, con su costo y ganancia reales.
        $datos = $this->expandirPorLotes($datos);

        // Calcular resumen de la página actual
        $resumen = $this->calcularResumenDatos($datos);

        return [
            'data' => $datos,
            'resumen' => $resumen,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Obtener resumen de ganancias para las cards
     */
    public function obtenerResumenGanancias(array $filtros): array
    {
        $filter = new GananciasQueryFilter($filtros);

        // Resumen de ventas
        $query = GananciasQueryBuilder::resumenGananciasQuery();
        $filter->apply($query);
        $resumen = $query->first();

        // Gastos de compras
        $gastosUQuery = GananciasQueryBuilder::gastosComprasQuery();
        $filter->applyCompras($gastosUQuery, 'comp');
        $gastosU = $gastosUQuery->get()->sum('subtotal') + $gastosUQuery->get()->sum('percepcion');

        // Pérdidas por salidas
        $queryPerdidaSalidas = GananciasQueryBuilder::perdidaSalidasSumQuery();
        $filter->applyBasic($queryPerdidaSalidas, 'isa');
        $totalPerdidaSalidas = $queryPerdidaSalidas->sum(DB::raw('udis.cantidad * pais.costo'));

        $totalPerdida = ($resumen->total_perdida ?? 0) + $totalPerdidaSalidas;

        return ResumenHelper::buildResumenGanancias(
            $resumen->total_ventas ?? 0,
            $resumen->total_costo ?? 0,
            $resumen->total_ganancia ?? 0,
            $gastosU,
            $totalPerdida,
            $resumen->total_transacciones ?? 0
        );
    }

    /**
     * Exportar reporte de ganancias
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array
    {
        // Por ahora retornamos un placeholder
        // Aquí se implementaría la lógica de exportación real
        $nombreArchivo = 'reporte_ganancias_' . date('Y-m-d_H-i-s') . '.' . ($formato === 'excel' ? 'xlsx' : 'pdf');
        
        return [
            'url' => '/storage/exports/' . $nombreArchivo,
            'nombre' => $nombreArchivo
        ];
    }

    /**
     * Enviar reporte por correo electrónico
     */
    public function enviarReportePorCorreo(string $email, array $filtros): void
    {
        // Por ahora solo log, se implementaría con Mail facade
        \Log::info("Enviando reporte de ganancias a: {$email}", $filtros);
    }

    /**
     * Construir query base para ganancias
     */
    private function construirQueryGanancias(array $filtros)
    {
        $filter = new GananciasQueryFilter($filtros);
        $query = GananciasQueryBuilder::reporteDetalladoQuery();

        // Aplicar filtros usando el QueryFilter
        $filter->apply($query);

        // Si no hay almacén específico, no mostrar datos por seguridad
        if (empty($filtros['almacen_id'])) {
            $query->whereRaw('1 = 0');
        }

        return $query->orderBy('v.fecha', 'desc')->orderBy('v.created_at', 'desc');
    }

    /**
     * Desglosa cada fila de venta en N filas (una por lote de costo PEPS) cuando
     * esa venta consumió stock de costos distintos. Así el reporte muestra el
     * costo y la ganancia REALES de cada lote, no un promedio.
     *
     * Solo desglosa cuando es seguro (no altera totales):
     *  - hay consumos registrados para (venta, producto),
     *  - hay 2+ lotes con costo distinto,
     *  - la suma de cantidades del consumo coincide con la cantidad de la fila
     *    (la fila representa toda la línea; evita doble conteo en multi-formato).
     */
    private function expandirPorLotes($datos)
    {
        if ($datos->isEmpty()) {
            return $datos;
        }

        // Pares (venta_id, producto_almacen_id) presentes en esta página
        $ventaIds = $datos->pluck('venta_id')->filter()->unique()->values()->all();
        $paIds = $datos->pluck('producto_almacen_id')->filter()->unique()->values()->all();

        if (empty($ventaIds) || empty($paIds)) {
            return $datos;
        }

        $consumosPorClave = DB::table('productoalmacen_lote_consumo')
            ->where('origen_tipo', 'venta')
            ->whereIn('origen_id', $ventaIds)
            ->whereIn('producto_almacen_id', $paIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn($c) => $c->origen_id . '|' . $c->producto_almacen_id);

        $resultado = collect();

        foreach ($datos as $row) {
            $clave = ($row->venta_id ?? '') . '|' . ($row->producto_almacen_id ?? '');
            $consumos = $consumosPorClave->get($clave);
            $cant = (float) $row->cant;

            $distintos = $consumos
                ? $consumos->pluck('costo')->map(fn($c) => round((float) $c, 4))->unique()->count()
                : 0;
            $sumCant = $consumos ? (float) $consumos->sum(fn($c) => (float) $c->cantidad) : 0;

            $puedeDesglosar = $consumos
                && $consumos->count() > 1
                && $distintos > 1
                && abs($sumCant - $cant) < 0.01; // fila = toda la línea (factor 1)

            if (! $puedeDesglosar) {
                $resultado->push($row);
                continue;
            }

            $n = $consumos->count();
            $i = 0;
            foreach ($consumos as $c) {
                $i++;
                $cl = (float) $c->cantidad;
                $costoUnit = (float) $c->costo;

                $fila = clone $row;
                $fila->cant = $cl;
                $fila->costo = $costoUnit;
                $fila->costo_total = $costoUnit * $cl;
                $fila->subtot = (float) $row->p_unit * $cl;
                $fila->ganancia = $fila->subtot - $fila->costo_total;
                $fila->desglose_lote = "Lote {$i}/{$n}";
                $resultado->push($fila);
            }
        }

        return $resultado->values();
    }

    /**
     * Calcular resumen de los datos actuales
     */
    private function calcularResumenDatos($datos): array
    {
        $totalPerdida = $datos->where('ganancia', '<', 0)->sum(fn($item) => abs($item->ganancia));

        return ResumenHelper::buildResumenDatos(
            $datos->sum('subtotal'),
            $datos->sum('costo_total'),
            $datos->sum('ganancia'),
            0, // gastos_u
            $totalPerdida,
            $datos->count()
        );
    }

    /**
     * Obtener pagos de compras detallados
     */
    public function obtenerPagosCompras(array $filtros): array
    {
        $filter = new GananciasQueryFilter($filtros);

        // Pagos
        $queryPagos = DB::table('pagodecompra as p')
            ->join('compra as c', 'p.compra_id', '=', 'c.id')
            ->join('proveedor as prov', 'c.proveedor_id', '=', 'prov.id')
            ->leftJoin('desplieguedepago as dp', 'p.despliegue_de_pago_id', '=', 'dp.id')
            ->select([
                'p.id', 'p.fecha', 'p.monto', 'p.numero_operacion', 'p.observacion',
                'prov.razon_social as proveedor', 'c.serie', 'c.numero',
                'dp.id as despliegue_id', 'dp.name as metodo_pago'
            ])
            ->where('p.estado', true)
            ->where('c.estado_de_compra', '!=', 'an')
            ->orderBy('p.fecha', 'desc');

        $filter->applyPagosCompras($queryPagos);
        $pagos = $queryPagos->get();

        // Gastos extras (excluir los que están asociados a compras)
        $queryGastosExtras = DB::table('gastos_extras as ge')
            ->leftJoin('compra as c', 'ge.id', '=', 'c.gasto_extra_id')
            ->select([
                'ge.id', 
                'ge.created_at as fecha',
                'ge.monto',
                'ge.concepto as descripcion',
                DB::raw("'GASTO OPERATIVO' as tipo_gasto"),
                DB::raw("'gasto_operativo' as tipo")
            ])
            ->whereNull('c.id'); // Solo gastos que NO están asociados a compras

        $filter->applyGastosExtras($queryGastosExtras);
        $gastosExtras = $queryGastosExtras->get();

        // Gastos de compras
        $queryGastosCompras = DB::table('compra as c')
            ->join('gastos_extras as ge', 'c.gasto_extra_id', '=', 'ge.id')
            ->join('proveedor as prov', 'c.proveedor_id', '=', 'prov.id')
            ->select([
                'ge.id', 
                DB::raw('DATE(ge.created_at) as fecha'), 
                'ge.monto', 
                'ge.concepto as descripcion', 
                DB::raw("'GASTO DE COMPRA' as tipo_gasto"),
                'prov.razon_social as proveedor', 
                'c.serie', 
                'c.numero', 
                DB::raw("'gasto_compra' as tipo")
            ])
            ->where('c.estado_de_compra', '!=', 'an')
            ->whereNotNull('c.gasto_extra_id');

        $filter->applyGastosCompras($queryGastosCompras);
        $gastosCompras = $queryGastosCompras->get();

        // Comisiones a vendedores (solo pagadas/confirmadas)
        $queryComisiones = DB::table('comision_pago as cp')
            ->join('user as u', 'cp.user_id', '=', 'u.id')
            ->select([
                'cp.id',
                'cp.fecha_pago as fecha',
                'cp.monto_pagado as monto',
                DB::raw("CONCAT('Comisión a ', u.name, ' (', DATE_FORMAT(cp.periodo_desde, '%d/%m/%Y'), ' - ', DATE_FORMAT(cp.periodo_hasta, '%d/%m/%Y'), ')') as descripcion"),
                DB::raw("'COMISIÓN VENDEDOR' as tipo_gasto"),
                DB::raw("'comision_vendedor' as tipo"),
                'u.name as vendedor',
                'cp.metodo_pago',
                'cp.observacion'
            ])
            ->orderBy('cp.fecha_pago', 'desc');

        $filter->applyComisiones($queryComisiones);
        $comisiones = $queryComisiones->get();

        $todosGastos = $gastosExtras->concat($gastosCompras)->concat($comisiones)->sortByDesc('fecha')->values();

        // Calcular resumen
        $queryComprasPeriodo = DB::table('compra as comp')
            ->where('comp.estado_de_compra', '!=', 'an')
            ->select('comp.id', 'comp.percepcion');

        $filter->applyCompras($queryComprasPeriodo, 'comp');
        $comprasPeriodo = $queryComprasPeriodo->get();
        $compraIds = $comprasPeriodo->pluck('id');

        $totalComprasBruto = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->whereIn('pac.compra_id', $compraIds)
            ->sum(DB::raw('udic.cantidad * pac.costo'));
        
        $totalCompras = $totalComprasBruto + $comprasPeriodo->sum('percepcion');
        $totalPagadoDeEstasCompras = DB::table('pagodecompra')
            ->whereIn('compra_id', $compraIds)
            ->where('estado', true)
            ->sum('monto');

        return [
            'pagos' => $pagos->toArray(),
            'gastos' => $todosGastos->toArray(),
            'resumen' => ResumenHelper::buildResumenPagosCompras(
                $totalCompras,
                $pagos->sum('monto'),
                $todosGastos->sum('monto'),
                $totalCompras - $totalPagadoDeEstasCompras
            )
        ];
    }

    /**
     * Obtener detalle de pérdidas (Ventas bajo costo y Salidas de Almacén)
     */
    public function obtenerPerdidasDetalle(array $filtros): array
    {
        $filter = new GananciasQueryFilter($filtros);

        // Pérdidas por ventas bajo costo
        $queryPerdidasVentas = GananciasQueryBuilder::perdidasVentasQuery();
        $filter->applyPerdidas($queryPerdidasVentas, 'venta');
        $perdidasVentas = $queryPerdidasVentas->get();

        // Pérdidas por salidas de almacén
        $queryPerdidasSalidas = GananciasQueryBuilder::perdidasSalidasQuery();
        $filter->applyPerdidas($queryPerdidasSalidas, 'salida');
        $perdidasSalidas = $queryPerdidasSalidas->get();

        return [
            'detalles' => $perdidasVentas->concat($perdidasSalidas)->sortByDesc('fecha')->values()->toArray(),
            'resumen' => ResumenHelper::buildResumenPerdidas(
                $perdidasVentas->sum('monto'),
                $perdidasSalidas->sum('monto')
            )
        ];
    }
}