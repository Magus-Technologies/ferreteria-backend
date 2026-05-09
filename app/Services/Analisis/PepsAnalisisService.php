<?php

namespace App\Services\Analisis;

use Illuminate\Support\Facades\DB;

class PepsAnalisisService
{
    /**
     * Obtiene el análisis PEPS real desde la base de datos.
     *
     * Para cada producto con compras en USD en el período:
     *  - Toma los lotes de compra ordenados por fecha (PEPS = más antiguo primero)
     *  - Usa el TC de compra (registrado al momento de la compra)
     *  - Usa el TC de pago (promedio ponderado de pagos con tipo_de_cambio; si no existe, usa TC compra)
     *  - Aplica PEPS contra las ventas del período para ese producto
     *  - Calcula ganancia estimada (TC compra) vs ganancia real (TC pago)
     */
    public function obtenerAnalisisPeps(array $filtros): array
    {
        $almacenId = $filtros['almacen_id'] ?? null;
        $desde     = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta     = $filtros['hasta'] ?? now()->toDateString();
        $productoId = $filtros['producto_id'] ?? null;

        // ── 1. Lotes de compra en USD del período ────────────────────────────
        $lotesQuery = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->join('compra as c', 'pac.compra_id', '=', 'c.id')
            ->join('productoalmacen as pa', 'pac.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->whereNotIn('c.estado_de_compra', ['an', 'ee'])
            ->whereNotNull('c.tipo_de_cambio')
            ->where('c.tipo_de_cambio', '>', 0)
            ->where('udic.bonificacion', false)
            ->whereDate('c.fecha', '>=', $desde)
            ->whereDate('c.fecha', '<=', $hasta)
            ->select(
                'udic.id as udic_id',
                'c.id as compra_id',
                'c.fecha as compra_fecha',
                'c.serie',
                'c.numero',
                DB::raw('CAST(c.tipo_de_cambio AS DECIMAL(10,4)) as tc_compra'),
                DB::raw('CAST(pac.costo AS DECIMAL(10,4)) as costo_soles_unit'),
                DB::raw('CAST(udic.factor AS DECIMAL(10,4)) as factor'),
                DB::raw('CAST(udic.cantidad AS DECIMAL(10,4)) as cantidad'),
                DB::raw('CAST(udic.flete AS DECIMAL(10,4)) as flete'),
                'udic.lote',
                'pa.producto_id',
                'p.name as producto_nombre',
                'm.name as marca_nombre'
            )
            ->orderBy('pa.producto_id')
            ->orderBy('c.fecha', 'asc');

        if ($almacenId) {
            $lotesQuery->where('c.almacen_id', $almacenId);
        }
        if ($productoId) {
            $lotesQuery->where('pa.producto_id', $productoId);
        }

        $lotes = $lotesQuery->get();

        if ($lotes->isEmpty()) {
            return $this->respuestaVacia();
        }

        // ── 2. TC de pago por compra (promedio ponderado) ─────────────────────
        $compraIds = $lotes->pluck('compra_id')->unique()->values()->toArray();

        $tcPagoPorCompra = DB::table('pagodecompra')
            ->whereIn('compra_id', $compraIds)
            ->where('estado', true)
            ->whereNotNull('tipo_de_cambio')
            ->where('tipo_de_cambio', '>', 0)
            ->select(
                'compra_id',
                DB::raw('SUM(CAST(monto AS DECIMAL(10,4)) * CAST(tipo_de_cambio AS DECIMAL(10,4))) / SUM(CAST(monto AS DECIMAL(10,4))) as tc_pago_promedio')
            )
            ->groupBy('compra_id')
            ->pluck('tc_pago_promedio', 'compra_id');

        // ── 3. Ventas del período para esos productos ─────────────────────────
        $productoIds = $lotes->pluck('producto_id')->unique()->values()->toArray();

        $ventasQuery = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->whereNotIn('v.estado_de_venta', ['an'])
            ->whereDate('v.fecha', '>=', $desde)
            ->whereDate('v.fecha', '<=', $hasta)
            ->whereIn('pa.producto_id', $productoIds)
            ->select(
                'v.id as venta_id',
                'v.fecha as venta_fecha',
                DB::raw('CAST(udiv.cantidad AS DECIMAL(10,4)) as cantidad'),
                DB::raw('CAST(udiv.precio AS DECIMAL(10,4)) as precio'),
                'pa.producto_id'
            )
            ->orderBy('pa.producto_id')
            ->orderBy('v.fecha', 'asc');

        if ($almacenId) {
            $ventasQuery->where('v.almacen_id', $almacenId);
        }

        $ventas = $ventasQuery->get();

        // ── 4. Aplicar PEPS por producto ──────────────────────────────────────
        $lotesPorProducto  = $lotes->groupBy('producto_id');
        $ventasPorProducto = $ventas->groupBy('producto_id');

        $resultadosPorProducto = [];
        $totalIngresoGlobal    = 0;
        $totalGananciaCGlobal  = 0;
        $totalGananciaPGlobal  = 0;

        foreach ($lotesPorProducto as $productoId => $lotesProducto) {
            $ventasProducto = $ventasPorProducto->get($productoId, collect());

            // Stock mutable por lote (en unidades base = cantidad × factor)
            $stockLotes = $lotesProducto->map(function ($l) use ($tcPagoPorCompra) {
                $costoUsd   = (float) $l->costo_soles_unit / (float) $l->tc_compra;
                $tcPago     = isset($tcPagoPorCompra[$l->compra_id])
                    ? (float) $tcPagoPorCompra[$l->compra_id]
                    : (float) $l->tc_compra; // Sin TC pago registrado → igual al de compra

                return [
                    'compra_id'    => $l->compra_id,
                    'fecha'        => $l->compra_fecha,
                    'serie'        => $l->serie,
                    'numero'       => $l->numero,
                    'lote'         => $l->lote,
                    'costo_usd'    => round($costoUsd, 6),
                    'tc_compra'    => (float) $l->tc_compra,
                    'tc_pago'      => round($tcPago, 4),
                    'tc_pago_real' => isset($tcPagoPorCompra[$l->compra_id]),
                    'stock'        => (float) $l->cantidad * (float) $l->factor,
                    'stock_orig'   => (float) $l->cantidad * (float) $l->factor,
                ];
            })->values()->toArray();

            $resultadosVenta = [];

            foreach ($ventasProducto as $venta) {
                $necesario         = (float) $venta->cantidad;
                $fracciones        = [];
                $totalCostoC       = 0;
                $totalCostoP       = 0;

                foreach ($stockLotes as &$lote) {
                    if ($necesario <= 0) break;
                    if ($lote['stock'] <= 0) continue;

                    $tomar  = min($lote['stock'], $necesario);
                    $costoC = $tomar * $lote['costo_usd'] * $lote['tc_compra'];
                    $costoP = $tomar * $lote['costo_usd'] * $lote['tc_pago'];

                    $fracciones[] = [
                        'compra_id'       => $lote['compra_id'],
                        'serie_numero'    => ($lote['serie'] ?? '') . '-' . ($lote['numero'] ?? ''),
                        'lote'            => $lote['lote'],
                        'cantidad'        => round($tomar, 4),
                        'costo_usd'       => round($lote['costo_usd'], 4),
                        'tc_compra'       => $lote['tc_compra'],
                        'tc_pago'         => $lote['tc_pago'],
                        'tc_pago_real'    => $lote['tc_pago_real'],
                        'costo_tc_compra' => round($costoC, 4),
                        'costo_tc_pago'   => round($costoP, 4),
                    ];

                    $totalCostoC      += $costoC;
                    $totalCostoP      += $costoP;
                    $lote['stock']    -= $tomar;
                    $necesario        -= $tomar;
                }

                $ingreso       = (float) $venta->cantidad * (float) $venta->precio;
                $gananciaC     = $ingreso - $totalCostoC;
                $gananciaP     = $ingreso - $totalCostoP;
                $diffCambio    = $gananciaC - $gananciaP;

                $totalIngresoGlobal   += $ingreso;
                $totalGananciaCGlobal += $gananciaC;
                $totalGananciaPGlobal += $gananciaP;

                $resultadosVenta[] = [
                    'venta_id'               => $venta->venta_id,
                    'fecha'                  => $venta->venta_fecha,
                    'cantidad'               => (float) $venta->cantidad,
                    'precio'                 => (float) $venta->precio,
                    'ingreso'                => round($ingreso, 4),
                    'fracciones'             => $fracciones,
                    'total_costo_tc_compra'  => round($totalCostoC, 4),
                    'total_costo_tc_pago'    => round($totalCostoP, 4),
                    'ganancia_tc_compra'     => round($gananciaC, 4),
                    'ganancia_tc_pago'       => round($gananciaP, 4),
                    'diferencia_cambio'      => round($diffCambio, 4),
                    'sin_stock'              => $necesario > 0,
                    'faltante'               => max(0, $necesario),
                ];
            }

            $totC = collect($resultadosVenta)->sum('ganancia_tc_compra');
            $totP = collect($resultadosVenta)->sum('ganancia_tc_pago');

            $resultadosPorProducto[] = [
                'producto_id'      => $productoId,
                'producto_nombre'  => $lotesProducto->first()->producto_nombre,
                'marca_nombre'     => $lotesProducto->first()->marca_nombre,
                'total_lotes'      => count($stockLotes),
                'total_ventas'     => count($resultadosVenta),
                'ventas'           => $resultadosVenta,
                'resumen_producto' => [
                    'ganancia_tc_compra' => round($totC, 4),
                    'ganancia_tc_pago'   => round($totP, 4),
                    'diferencia_cambio'  => round($totC - $totP, 4),
                ],
            ];
        }

        $totalDiferencia = $totalGananciaCGlobal - $totalGananciaPGlobal;

        return [
            'productos'   => $resultadosPorProducto,
            'resumen'     => [
                'ingreso_total'       => round($totalIngresoGlobal, 4),
                'ganancia_tc_compra'  => round($totalGananciaCGlobal, 4),
                'ganancia_tc_pago'    => round($totalGananciaPGlobal, 4),
                'diferencia_total'    => round($totalDiferencia, 4),
                'perdida_por_cambio'  => $totalDiferencia < 0,
                'total_productos'     => count($resultadosPorProducto),
                'aviso_sin_tc_pago'   => $tcPagoPorCompra->isEmpty(),
            ],
        ];
    }

    private function respuestaVacia(): array
    {
        return [
            'productos' => [],
            'resumen'   => [
                'ingreso_total'      => 0,
                'ganancia_tc_compra' => 0,
                'ganancia_tc_pago'   => 0,
                'diferencia_total'   => 0,
                'perdida_por_cambio' => false,
                'total_productos'    => 0,
                'aviso_sin_tc_pago'  => true,
            ],
        ];
    }

    /**
     * Cálculo puro PEPS (usado internamente o para testing).
     */
    public function calcularPeps(array $lotes, array $ventas): array
    {
        $stockRestante = [];
        foreach ($lotes as $lote) {
            $stockRestante[$lote['id']] = (float) $lote['stock'];
        }

        $resultadosPorVenta = [];
        $totIngreso = $totGananciaC = $totGananciaP = 0;

        foreach ($ventas as $idx => $venta) {
            $necesario = (float) $venta['cantidad'];
            $fracciones = [];
            $totalCostoTcCompra = $totalCostoTcPago = 0;

            foreach ($lotes as $lote) {
                if ($necesario <= 0) break;
                $disponible = $stockRestante[$lote['id']] ?? 0;
                if ($disponible <= 0) continue;

                $tomar    = min($disponible, $necesario);
                $costoUsd = (float) $lote['costo_usd'];
                $tcCompra = (float) $lote['tc_compra'];
                $tcPago   = (float) $lote['tc_pago'];
                $costoC   = $tomar * $costoUsd * $tcCompra;
                $costoP   = $tomar * $costoUsd * $tcPago;

                $fracciones[] = [
                    'lote_id'         => $lote['id'],
                    'lote_label'      => $lote['label'],
                    'cantidad'        => $tomar,
                    'costo_usd'       => $costoUsd,
                    'tc_compra'       => $tcCompra,
                    'tc_pago'         => $tcPago,
                    'costo_tc_compra' => round($costoC, 4),
                    'costo_tc_pago'   => round($costoP, 4),
                ];

                $totalCostoTcCompra += $costoC;
                $totalCostoTcPago   += $costoP;
                $stockRestante[$lote['id']] -= $tomar;
                $necesario -= $tomar;
            }

            $ingreso      = (float) $venta['cantidad'] * (float) $venta['precio'];
            $gananciaC    = $ingreso - $totalCostoTcCompra;
            $gananciaP    = $ingreso - $totalCostoTcPago;
            $diffCambio   = $gananciaC - $gananciaP;

            $totIngreso   += $ingreso;
            $totGananciaC += $gananciaC;
            $totGananciaP += $gananciaP;

            $resultadosPorVenta[] = [
                'idx'                   => $idx,
                'cantidad'              => (float) $venta['cantidad'],
                'precio'                => (float) $venta['precio'],
                'ingreso'               => round($ingreso, 4),
                'fracciones'            => $fracciones,
                'total_costo_tc_compra' => round($totalCostoTcCompra, 4),
                'total_costo_tc_pago'   => round($totalCostoTcPago, 4),
                'ganancia_tc_compra'    => round($gananciaC, 4),
                'ganancia_tc_pago'      => round($gananciaP, 4),
                'diferencia_cambio'     => round($diffCambio, 4),
                'sin_stock'             => $necesario > 0,
                'faltante'              => max(0, $necesario),
            ];
        }

        $totalDiferencia = $totGananciaC - $totGananciaP;

        return [
            'ventas'         => $resultadosPorVenta,
            'stock_restante' => $stockRestante,
            'resumen'        => [
                'ingreso_total'       => round($totIngreso, 4),
                'ganancia_tc_compra'  => round($totGananciaC, 4),
                'ganancia_tc_pago'    => round($totGananciaP, 4),
                'diferencia_total'    => round($totalDiferencia, 4),
                'perdida_por_cambio'  => $totalDiferencia < 0,
            ],
        ];
    }
}
