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

        // Si se filtró por Despliegue de Pago, TODAS las filas devueltas tienen ese
        // despliegue (el filtro usa whereExists). Se muestra el filtrado en la columna CC
        // para que coincida con el filtro; si no, en pagos mixtos la CC mostraba el
        // despliegue de mayor monto (que puede ser otro) y parecía inconsistente.
        if (!empty($filtros['confirmar_caja'])) {
            $ccFiltrado = $filtros['confirmar_caja'];
            $datos->each(function ($row) use ($ccFiltrado) {
                $row->cc = $ccFiltrado;
            });
        }

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
        // pais.costo está por unidad base; udis.cantidad en unidades derivadas → ×udis.factor.
        $totalPerdidaSalidas = $queryPerdidaSalidas->sum(DB::raw('udis.cantidad * udis.factor * pais.costo'));

        $totalPerdida = ($resumen->total_perdida ?? 0) + $totalPerdidaSalidas;

        // Impacto TC a favor/en contra (costo a TC compra vs. TC realmente pagado)
        // de las compras en dólares del período; se suma a la ganancia porque es
        // dinero real ganado (o perdido) por la diferencia de cambio al pagar.
        $totalImpactoTc = $this->expandirPorLotes(
            $this->construirQueryGanancias($filtros)->get()
        )->sum('impacto_tc');

        return ResumenHelper::buildResumenGanancias(
            $resumen->total_ventas ?? 0,
            $resumen->total_costo ?? 0,
            ($resumen->total_ganancia ?? 0) + $totalImpactoTc,
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

        // Origen de cada lote (para la columna "Documento pagado"). Un lote NO siempre
        // viene de una compra: puede venir de una recepción, una transferencia entre
        // almacenes o un ingreso/salida manual. Antes solo se resolvía compra_id, así que
        // el stock de transferencias/ingresos mostraba "Sin registro" aunque sí había
        // trazabilidad — solo que no era una compra.
        $loteIds = $consumosPorClave->flatten(1)->pluck('lote_id')->filter()->unique()->values()->all();
        $documentoPorLote = collect();
        // Impacto TC (costo a TC compra − costo al TC realmente pagado) y fecha del pago,
        // por lote — solo aplica a lotes de compras en dólares con al menos un pago activo.
        $impactoUnitPorLote = collect();
        $fechaPagoPorLote = collect();
        // compra_id resuelta por lote (null si el origen no es una compra) — para poder
        // exponer F.Vence/Tipo/Forma de pago/Proveedor/Registrador en la fila de subtotal.
        $compraIdPorLote = collect();
        // Maps de compras/proveedores/usuarios: se inicializan aquí (no solo dentro del if)
        // porque los usan los closures de abajo con `use(...)`. Si no hay lotes ($loteIds
        // vacío) el if no corre y quedarían indefinidos → "Undefined variable $comprasMap".
        $comprasMap = collect();
        $proveedoresMap = collect();
        $usuariosCompraMap = collect();

        if (!empty($loteIds)) {
            $lotesInfo = DB::table('productoalmacen_lote')
                ->whereIn('id', $loteIds)
                ->select('id', 'compra_id', 'transferencia_stock_id', 'ingreso_salida_id', 'recepcion_id', 'costo')
                ->get();

            $recepcionIds = $lotesInfo->pluck('recepcion_id')->filter()->unique()->values();
            $recepcionesMap = $recepcionIds->isEmpty() ? collect() : DB::table('recepcionalmacen')
                ->whereIn('id', $recepcionIds)->select('id', 'numero', 'compra_id')->get()->keyBy('id');

            $compraIds = $lotesInfo->pluck('compra_id')
                ->merge($recepcionesMap->pluck('compra_id'))
                ->filter()->unique()->values();
            $comprasMap = $compraIds->isEmpty() ? collect() : DB::table('compra')
                ->whereIn('id', $compraIds)
                ->select('id', 'serie', 'numero', 'tipo_moneda', 'tipo_de_cambio', 'tipo_documento', 'forma_de_pago', 'fecha_vencimiento', 'proveedor_id', 'user_id')
                ->get()->keyBy('id');

            // Proveedor y registrador de cada compra involucrada (para las columnas
            // F.VENCE / TIPO / FORMA PAGO / PROVEEDOR / VENDEDOR de la fila de subtotal).
            $proveedorIdsCompras = $comprasMap->pluck('proveedor_id')->filter()->unique()->values();
            $proveedoresMap = $proveedorIdsCompras->isEmpty() ? collect() : DB::table('proveedor')
                ->whereIn('id', $proveedorIdsCompras)->select('id', 'razon_social')->get()->keyBy('id');

            $userIdsCompras = $comprasMap->pluck('user_id')->filter()->unique()->values();
            $usuariosCompraMap = $userIdsCompras->isEmpty() ? collect() : DB::table('user')
                ->whereIn('id', $userIdsCompras)->select('id', 'name')->get()->keyBy('id');

            $transferenciaIds = $lotesInfo->pluck('transferencia_stock_id')->filter()->unique()->values();
            $transferenciasMap = $transferenciaIds->isEmpty() ? collect() : DB::table('transferencia_stock')
                ->whereIn('id', $transferenciaIds)->select('id', 'serie', 'numero')->get()->keyBy('id');

            $ingresoIds = $lotesInfo->pluck('ingreso_salida_id')->filter()->unique()->values();
            $ingresosMap = $ingresoIds->isEmpty() ? collect() : DB::table('ingresosalida')
                ->whereIn('id', $ingresoIds)->select('id', 'serie', 'numero')->get()->keyBy('id');

            // Pagos activos de las compras en dólares involucradas: TC promedio ponderado
            // (total soles pagados / total dólares pagados) y fecha del último pago.
            $comprasDolaresIds = $comprasMap->filter(fn($c) => $c->tipo_moneda === 'd')->pluck('id');
            $pagosPorCompra = $comprasDolaresIds->isEmpty() ? collect() : DB::table('pagodecompra')
                ->whereIn('compra_id', $comprasDolaresIds)
                ->where('estado', true)
                ->select('compra_id', 'monto', 'tipo_de_cambio', 'fecha')
                ->get()
                ->groupBy('compra_id');

            $tcPagoPromedioPorCompra = collect();
            $fechaPagoPorCompra = collect();
            foreach ($pagosPorCompra as $compraId => $pagos) {
                $totalSoles = $pagos->sum(fn($p) => (float) $p->monto);
                $totalDolares = $pagos->sum(fn($p) => ((float) $p->tipo_de_cambio) > 0 ? ((float) $p->monto) / ((float) $p->tipo_de_cambio) : 0);
                if ($totalDolares > 0) {
                    $tcPagoPromedioPorCompra->put($compraId, $totalSoles / $totalDolares);
                }
                $fechaPagoPorCompra->put($compraId, $pagos->max('fecha'));
            }

            foreach ($lotesInfo as $lote) {
                $etiqueta = null;
                $compraIdResuelta = null;

                if ($lote->compra_id && $comprasMap->has($lote->compra_id)) {
                    $c = $comprasMap->get($lote->compra_id);
                    $etiqueta = ($c->serie ?? '') . '-' . ($c->numero ?? '');
                    $compraIdResuelta = $lote->compra_id;
                } elseif ($lote->recepcion_id && $recepcionesMap->has($lote->recepcion_id)) {
                    $r = $recepcionesMap->get($lote->recepcion_id);
                    if ($r->compra_id && $comprasMap->has($r->compra_id)) {
                        $c = $comprasMap->get($r->compra_id);
                        $etiqueta = ($c->serie ?? '') . '-' . ($c->numero ?? '');
                        $compraIdResuelta = $r->compra_id;
                    } else {
                        $etiqueta = 'Recepción #' . $r->numero;
                    }
                } elseif ($lote->transferencia_stock_id && $transferenciasMap->has($lote->transferencia_stock_id)) {
                    $t = $transferenciasMap->get($lote->transferencia_stock_id);
                    $etiqueta = 'Transferencia ' . (($t->serie ?? null) ? $t->serie . '-' . $t->numero : '#' . $t->id);
                } elseif ($lote->ingreso_salida_id && $ingresosMap->has($lote->ingreso_salida_id)) {
                    $i = $ingresosMap->get($lote->ingreso_salida_id);
                    $etiqueta = 'Ingreso/Salida ' . (($i->serie ?? null) ? $i->serie . '-' . $i->numero : '#' . $i->id);
                }
                $documentoPorLote->put($lote->id, $etiqueta);
                $compraIdPorLote->put($lote->id, $compraIdResuelta);

                if ($compraIdResuelta && $comprasMap->has($compraIdResuelta)) {
                    $c = $comprasMap->get($compraIdResuelta);
                    $tcCompra = (float) ($c->tipo_de_cambio ?? 0);
                    if ($c->tipo_moneda === 'd' && $tcCompra > 0 && $tcPagoPromedioPorCompra->has($compraIdResuelta)) {
                        $tcPago = $tcPagoPromedioPorCompra->get($compraIdResuelta);
                        // Impacto por unidad: costo_actual × (1 − tcPago/tcCompra).
                        // Positivo = ganaste (TC pago más barato); negativo = perdiste.
                        $impactoUnitPorLote->put($lote->id, (float) $lote->costo * (1 - $tcPago / $tcCompra));
                        $fechaPagoPorLote->put($lote->id, $fechaPagoPorCompra->get($compraIdResuelta));
                    }
                }
            }
        }

        // Impacto TC total y fecha de pago de un grupo de consumos (misma lógica que el
        // costo: se suma cantidad × impacto-unitario de cada lote consumido).
        $calcularImpactoYFecha = function ($consumos) use ($impactoUnitPorLote, $fechaPagoPorLote) {
            if (!$consumos || $consumos->isEmpty()) {
                return [null, null];
            }
            $impacto = null;
            $fecha = null;
            foreach ($consumos as $c) {
                if ($impactoUnitPorLote->has($c->lote_id)) {
                    $impacto = ($impacto ?? 0) + ((float) $c->cantidad) * $impactoUnitPorLote->get($c->lote_id);
                    $fecha = $fecha ?? $fechaPagoPorLote->get($c->lote_id);
                }
            }
            return [$impacto, $fecha];
        };

        // Datos de la compra de origen (F.Vence, Tipo, Forma de pago, Proveedor,
        // Registrador) — solo se exponen cuando TODO el grupo de consumos viene de la
        // MISMA compra (si son varias compras distintas, no hay un solo proveedor/fecha
        // que mostrar, así que se deja null).
        $resolverInfoCompra = function ($consumos) use ($compraIdPorLote, $comprasMap, $proveedoresMap, $usuariosCompraMap) {
            if (!$consumos || $consumos->isEmpty()) {
                return [null, null, null, null, null];
            }
            $compraIds = $consumos->pluck('lote_id')
                ->unique()
                ->map(fn($loteId) => $compraIdPorLote->get($loteId))
                ->filter()
                ->unique()
                ->values();
            if ($compraIds->count() !== 1) {
                return [null, null, null, null, null];
            }
            $compra = $comprasMap->get($compraIds->first());
            if (!$compra) {
                return [null, null, null, null, null];
            }
            $proveedor = $compra->proveedor_id ? $proveedoresMap->get($compra->proveedor_id) : null;
            $registrador = $compra->user_id ? $usuariosCompraMap->get($compra->user_id) : null;
            return [
                $compra->fecha_vencimiento,
                $compra->tipo_documento,
                $compra->forma_de_pago,
                $proveedor->razon_social ?? null,
                $registrador->name ?? null,
            ];
        };

        // Dado un grupo de consumos, resuelve el origen del stock: uno solo → su
        // referencia; varios distintos → "N orígenes".
        $resolverDocumentoPagado = function ($consumos) use ($documentoPorLote) {
            if (!$consumos || $consumos->isEmpty()) {
                return null;
            }
            $etiquetasUnicas = $consumos->pluck('lote_id')
                ->unique()
                ->map(fn($loteId) => $documentoPorLote->get($loteId))
                ->filter()
                ->unique()
                ->values();
            if ($etiquetasUnicas->isEmpty()) {
                return null;
            }
            return $etiquetasUnicas->count() === 1 ? $etiquetasUnicas->first() : $etiquetasUnicas->count() . ' orígenes';
        };

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
                $row->documento_pagado = $resolverDocumentoPagado($consumos);
                [$row->impacto_tc, $row->fecha_pago_compra] = $calcularImpactoYFecha($consumos);
                [
                    $row->compra_fecha_vencimiento,
                    $row->compra_tipo_documento,
                    $row->compra_forma_pago,
                    $row->compra_proveedor,
                    $row->compra_registrado_por,
                ] = $resolverInfoCompra($consumos);
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
                $fila->documento_pagado = $documentoPorLote->get($c->lote_id);
                $fila->impacto_tc = $impactoUnitPorLote->has($c->lote_id) ? $cl * $impactoUnitPorLote->get($c->lote_id) : null;
                $fila->fecha_pago_compra = $fechaPagoPorLote->get($c->lote_id);
                $consumoUnico = collect([$c]);
                [
                    $fila->compra_fecha_vencimiento,
                    $fila->compra_tipo_documento,
                    $fila->compra_forma_pago,
                    $fila->compra_proveedor,
                    $fila->compra_registrado_por,
                ] = $resolverInfoCompra($consumoUnico);
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
            $datos->sum('subtot'),
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
            ->where('ge.estado', '!=', 'anulado')
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
            ->where('ge.estado', '!=', 'anulado')
            ->whereNotNull('c.gasto_extra_id');

        $filter->applyGastosCompras($queryGastosCompras);
        $gastosCompras = $queryGastosCompras->get();

        // Comisiones: SOLO las PAGADAS (comision_pago). Una comisión generada pero NO
        // pagada todavía no es un gasto real, así que no debe aparecer ni sumar en Gastos.
        // Si no hay ninguna pagada en el período, no sale fila de comisión.
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