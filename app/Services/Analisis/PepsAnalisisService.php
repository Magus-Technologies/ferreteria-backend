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
     *
     * TAMBIÉN retorna:
     *  - pending_payments: Todas las compras en USD a crédito sin pagos registrados (para análisis de TC)
     */
    public function obtenerAnalisisPeps(array $filtros): array
    {
        $almacenId = $filtros['almacen_id'] ?? null;
        $desde     = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta     = $filtros['hasta'] ?? now()->toDateString();
        $productoId = $filtros['producto_id'] ?? null;

        // ── 1. Primero obtener productos con ventas en el período ────────────
        $productosConVentas = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->whereNotIn('v.estado_de_venta', ['an'])
            ->whereDate('v.fecha', '>=', $desde)
            ->whereDate('v.fecha', '<=', $hasta)
            ->distinct()
            ->pluck('pa.producto_id')
            ->toArray();

        // Obtener TC actual (último TC de compra en USD del sistema)
        $tcActual = DB::table('compra')
            ->where('tipo_moneda', 'd')
            ->whereNotNull('tipo_de_cambio')
            ->where('tipo_de_cambio', '>', 0)
            ->orderBy('fecha', 'desc')
            ->limit(1)
            ->value('tipo_de_cambio') ?? 3.5000;

        // Si no hay ventas en el período, retornar vacío pero incluir pending payments
        if (empty($productosConVentas)) {
            $pendingPayments = $this->obtenerPendingPayments($almacenId, $tcActual);
            return [
                'productos'   => [],
                'resumen'     => [
                    'ingreso_total'      => 0,
                    'ganancia_tc_compra' => 0,
                    'ganancia_tc_pago'   => 0,
                    'diferencia_total'   => 0,
                    'perdida_por_cambio' => false,
                    'total_productos'    => 0,
                    'aviso_sin_tc_pago'  => true,
                    'tc_actual'          => round($tcActual, 4),
                    'compras_con_riesgo' => [],
                    'impacto_si_pagas_hoy' => 0,
                ],
                'pending_payments' => $pendingPayments,
            ];
        }

        // ── 2. Lotes de compra en USD (moneda = 'd') - TODAS las compras históricas ────────────
        // Nota: Obtenemos TODAS las compras en USD para los productos con ventas en el período,
        // no solo las del período. Esto permite PEPS correcto contra ventas actuales.
        $lotesQuery = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->join('compra as c', 'pac.compra_id', '=', 'c.id')
            ->join('productoalmacen as pa', 'pac.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('marca as m', 'p.marca_id', '=', 'm.id')
            ->whereNotIn('c.estado_de_compra', ['an', 'ee'])
            ->where('c.tipo_moneda', 'd')  // Solo compras en USD
            ->whereNotNull('c.tipo_de_cambio')
            ->where('c.tipo_de_cambio', '>', 0)
            ->where('udic.bonificacion', false)
            ->whereIn('pa.producto_id', $productosConVentas)  // Solo productos con ventas en el período
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

        // TC de pago con tipo_de_cambio explícito (promedio ponderado por monto)
        $tcPagoPorCompra = DB::table('pagodecompra')
            ->whereIn('compra_id', $compraIds)
            ->where('estado', true)
            ->whereNotNull('tipo_de_cambio')
            ->where('tipo_de_cambio', '>', 0)
            ->select(
                'compra_id',
                DB::raw('SUM(CAST(monto AS DECIMAL(10,4)) * CAST(tipo_de_cambio AS DECIMAL(10,4))) / SUM(CAST(monto AS DECIMAL(10,4))) as tc_pago_promedio'),
                DB::raw('MIN(CAST(tipo_de_cambio AS DECIMAL(10,4))) as tc_pago_minimo'),
                DB::raw('MAX(CAST(tipo_de_cambio AS DECIMAL(10,4))) as tc_pago_maximo'),
                DB::raw('COUNT(*) as num_pagos'),
                DB::raw('MAX(fecha) as fecha_ultimo_pago')
            )
            ->groupBy('compra_id')
            ->get();

        // Compras que tienen al menos un pago activo (aunque no tengan TC registrado)
        $comprasConCualquierPago = DB::table('pagodecompra')
            ->whereIn('compra_id', $compraIds)
            ->where('estado', true)
            ->distinct()
            ->pluck('compra_id')
            ->flip(); // para lookup O(1)

        // Convertir a array indexado por compra_id
        $tcPagoMap = [];
        foreach ($tcPagoPorCompra as $row) {
            $tcPagoMap[$row->compra_id] = [
                'tc_pago_promedio'  => (float) $row->tc_pago_promedio,
                'tc_pago_minimo'    => (float) $row->tc_pago_minimo,
                'tc_pago_maximo'    => (float) $row->tc_pago_maximo,
                'num_pagos'         => (int)   $row->num_pagos,
                'fecha_ultimo_pago' => $row->fecha_ultimo_pago,
                'tc_es_fallback'    => false,
            ];
        }

        // Para compras con pago pero sin TC: usar el TC de la compra como fallback
        // Así el análisis las reconoce como "pagadas" con diferencia = 0
        $tcCompraMap = $lotes->keyBy('compra_id')->map(fn($l) => (float) $l->tc_compra);
        foreach ($comprasConCualquierPago as $compraId => $_) {
            if (!isset($tcPagoMap[$compraId]) && isset($tcCompraMap[$compraId])) {
                $tcPagoMap[$compraId] = [
                    'tc_pago_promedio'  => $tcCompraMap[$compraId],
                    'tc_pago_minimo'    => $tcCompraMap[$compraId],
                    'tc_pago_maximo'    => $tcCompraMap[$compraId],
                    'num_pagos'         => 1,
                    'fecha_ultimo_pago' => null,
                    'tc_es_fallback'    => true, // pago real pero sin TC registrado
                ];
            }
        }

        // Obtener TC actual (último TC de compra en USD del sistema)
        $tcActual = DB::table('compra')
            ->where('tipo_moneda', 'd')
            ->whereNotNull('tipo_de_cambio')
            ->where('tipo_de_cambio', '>', 0)
            ->orderBy('fecha', 'desc')
            ->limit(1)
            ->value('tipo_de_cambio') ?? 3.5000;

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
        // Solo procesar productos que tienen AMBAS: compras en USD Y ventas en el período
        $lotesPorProducto  = $lotes->groupBy('producto_id');
        $ventasPorProducto = $ventas->groupBy('producto_id');

        $resultadosPorProducto = [];
        $totalIngresoGlobal      = 0;
        $totalGananciaCGlobal    = 0;
        $totalGananciaPGlobal    = 0;
        $totalGananciaCConPago   = 0; // solo ventas donde hay al menos un lote con pago real

        // Solo iterar sobre productos que tienen ventas
        foreach ($ventasPorProducto as $productoId => $ventasProducto) {
            $lotesProducto = $lotesPorProducto->get($productoId);
            
            // Si no hay lotes para este producto, saltar
            if (!$lotesProducto) {
                continue;
            }

            // Stock mutable por lote (en unidades base = cantidad × factor)
            $stockLotes = $lotesProducto->map(function ($l) use ($tcPagoMap, $tcActual) {
                $tcCompra = (float) $l->tc_compra;
                $tcPago = isset($tcPagoMap[$l->compra_id])
                    ? (float) $tcPagoMap[$l->compra_id]['tc_pago_promedio']
                    : $tcCompra;
                $tcPagoReal    = isset($tcPagoMap[$l->compra_id]);
                $tcEsFallback  = $tcPagoReal && ($tcPagoMap[$l->compra_id]['tc_es_fallback'] ?? false);

                // Calcular variación de TC
                $variacionTc = $tcActual - $tcCompra;
                $variacionPorcentaje = ($variacionTc / $tcCompra) * 100;
                $riesgoAlto = abs($variacionPorcentaje) > 2; // Alerta si varía más de 2%

                // Proyección: impacto si pagas hoy vs si ya pagaste
                $costoUsd = (float) $l->costo_soles_unit / $tcCompra;
                $costoEstimado = $costoUsd * $tcCompra;
                $costoSiPagasHoy = $costoUsd * $tcActual;
                $impactoSiPagasHoy = $costoSiPagasHoy - $costoEstimado;

                return [
                    'compra_id' => $l->compra_id,
                    'fecha' => $l->compra_fecha,
                    'serie' => $l->serie,
                    'numero' => $l->numero,
                    'lote' => $l->lote,
                    'costo_usd'      => round($costoUsd, 6),
                    'tc_compra'      => $tcCompra,
                    'tc_pago'        => round($tcPago, 4),
                    'tc_pago_real'   => $tcPagoReal,
                    'tc_es_fallback' => $tcEsFallback,
                    'tc_actual'      => $tcActual,
                    'variacion_tc' => round($variacionTc, 4),
                    'variacion_porcentaje' => round($variacionPorcentaje, 2),
                    'riesgo_alto' => $riesgoAlto,
                    'impacto_si_pagas_hoy' => round($impactoSiPagasHoy, 4),
                    'stock' => (float) $l->cantidad * (float) $l->factor,
                    'stock_orig' => (float) $l->cantidad * (float) $l->factor,
                ];
            })->values()->toArray();

            $resultadosVenta = [];

            foreach ($ventasProducto as $venta) {
                $necesario         = (float) $venta->cantidad;
                $fracciones        = [];
                $totalCostoC       = 0;
                $totalCostoP       = 0;
                $hayPagoReal       = false;

                foreach ($stockLotes as &$lote) {
                    if ($necesario <= 0) break;
                    if ($lote['stock'] <= 0) continue;

                    $tomar  = min($lote['stock'], $necesario);
                    $costoC = $tomar * $lote['costo_usd'] * $lote['tc_compra'];
                    $costoP = $tomar * $lote['costo_usd'] * $lote['tc_pago'];

                    $fraccion = [
                        'compra_id'       => $lote['compra_id'],
                        'serie_numero'    => ($lote['serie'] ?? '') . '-' . ($lote['numero'] ?? ''),
                        'lote'            => $lote['lote'],
                        'cantidad'        => round($tomar, 4),
                        'costo_usd'       => round($lote['costo_usd'], 4),
                        'tc_compra'       => $lote['tc_compra'],
                        'tc_pago_real'    => $lote['tc_pago_real'],
                    'tc_es_fallback'  => $lote['tc_es_fallback'],
                        'costo_tc_compra' => round($costoC, 4),
                    ];

                    // Solo incluir TC de pago si hay pago real registrado
                    if ($lote['tc_pago_real']) {
                        $fraccion['tc_pago']       = $lote['tc_pago'];
                        $fraccion['costo_tc_pago'] = round($costoP, 4);
                        $hayPagoReal = true;
                    }

                    $fracciones[] = $fraccion;

                    $totalCostoC += $costoC;
                    // Lote sin pago → aporta su costo TC compra (diferencia = 0 para ese tramo)
                    $totalCostoP += $lote['tc_pago_real'] ? $costoP : $costoC;
                    $lote['stock'] -= $tomar;
                    $necesario        -= $tomar;
                }

                $ingreso       = (float) $venta->cantidad * (float) $venta->precio;
                $gananciaC     = $ingreso - $totalCostoC;
                $gananciaP     = $hayPagoReal ? ($ingreso - $totalCostoP) : null;
                $diffCambio    = $hayPagoReal ? ($gananciaC - $gananciaP) : null;

                $totalIngresoGlobal   += $ingreso;
                $totalGananciaCGlobal += $gananciaC;
                if ($hayPagoReal) {
                    $totalGananciaPGlobal  += $gananciaP;
                    $totalGananciaCConPago += $gananciaC; // solo las ventas comparables
                }

                $resultadoVenta = [
                    'venta_id'               => $venta->venta_id,
                    'fecha'                  => $venta->venta_fecha,
                    'cantidad'               => (float) $venta->cantidad,
                    'precio'                 => (float) $venta->precio,
                    'ingreso'                => round($ingreso, 4),
                    'fracciones'             => $fracciones,
                    'total_costo_tc_compra'  => round($totalCostoC, 4),
                    'ganancia_tc_compra'     => round($gananciaC, 4),
                    'sin_stock'              => $necesario > 0,
                    'faltante'               => max(0, $necesario),
                ];

                // Solo incluir campos de pago si hay pagos reales
                if ($hayPagoReal) {
                    $resultadoVenta['total_costo_tc_pago']  = round($totalCostoP, 4);
                    $resultadoVenta['ganancia_tc_pago']     = round($gananciaP, 4);
                    $resultadoVenta['diferencia_cambio']    = round($diffCambio, 4);
                }

                $resultadosVenta[] = $resultadoVenta;
            }

            $totC = collect($resultadosVenta)->sum('ganancia_tc_compra');
            $totP = collect($resultadosVenta)->sum('ganancia_tc_pago') ?? 0;
            $hayPagoRealEnProducto = collect($resultadosVenta)->some(function ($v) {
                return isset($v['ganancia_tc_pago']);
            });

            $resumenProducto = [
                'ganancia_tc_compra' => round($totC, 4),
            ];

            if ($hayPagoRealEnProducto) {
                $resumenProducto['ganancia_tc_pago']   = round($totP, 4);
                $resumenProducto['diferencia_cambio']  = round($totC - $totP, 4);
            }

            $resultadosPorProducto[] = [
                'producto_id'      => $productoId,
                'producto_nombre'  => $lotesProducto->first()->producto_nombre,
                'marca_nombre'     => $lotesProducto->first()->marca_nombre,
                'total_lotes'      => count($stockLotes),
                'total_ventas'     => count($resultadosVenta),
                'ventas'           => $resultadosVenta,
                'resumen_producto' => $resumenProducto,
            ];
        }

        $totalDiferencia = $totalGananciaCConPago - $totalGananciaPGlobal;
        $hayPagoRealGlobal = !empty($tcPagoMap);

        // Calcular recomendaciones globales
        $comprasConRiesgo       = [];
        $comprasConRiesgoIds    = [];
        $impactoTotalSiPagasHoy = 0;
        $comprasImpactoIds      = [];
        foreach ($lotes as $lote) {
            $variacionTc = $tcActual - (float) $lote->tc_compra;
            $variacionPorcentaje = ($variacionTc / (float) $lote->tc_compra) * 100;

            // Riesgo: una entrada por compra (no por lote de producto)
            if (abs($variacionPorcentaje) > 2 && !in_array($lote->compra_id, $comprasConRiesgoIds)) {
                $comprasConRiesgoIds[] = $lote->compra_id;
                $comprasConRiesgo[] = [
                    'compra_id'            => $lote->compra_id,
                    'serie_numero'         => ($lote->serie ?? '') . '-' . ($lote->numero ?? ''),
                    'variacion_porcentaje' => round($variacionPorcentaje, 2),
                    'recomendacion'        => $variacionPorcentaje > 0 ? 'Pagar pronto (TC subió)' : 'Esperar (TC bajó)',
                ];
            }

            // Impacto: acumular una sola vez por compra
            if (!in_array($lote->compra_id, $comprasImpactoIds)) {
                $comprasImpactoIds[] = $lote->compra_id;
                $costoUsd = (float) $lote->costo_soles_unit / (float) $lote->tc_compra;
                $impactoTotalSiPagasHoy += $costoUsd * $variacionTc;
            }
        }

        $resumenGlobal = [
            'ingreso_total'       => round($totalIngresoGlobal, 4),
            'ganancia_tc_compra'  => round($totalGananciaCGlobal, 4),
            'total_productos'     => count($resultadosPorProducto),
            'aviso_sin_tc_pago'   => !$hayPagoRealGlobal,
            'tc_actual'           => round($tcActual, 4),
            'compras_con_riesgo'  => $comprasConRiesgo,
            'impacto_si_pagas_hoy' => round($impactoTotalSiPagasHoy, 4),
        ];

        if ($hayPagoRealGlobal) {
            $resumenGlobal['ganancia_tc_pago']    = round($totalGananciaPGlobal, 4);
            $resumenGlobal['diferencia_total']    = round($totalDiferencia, 4);
            $resumenGlobal['perdida_por_cambio']  = $totalDiferencia > 0;
        }

        // Obtener compras pendientes de pago (a crédito sin pagos)
        $pendingPayments = $this->obtenerPendingPayments($almacenId, $tcActual);

        return [
            'productos'   => $resultadosPorProducto,
            'resumen'     => $resumenGlobal,
            'pending_payments' => $pendingPayments,
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
     * Obtiene todas las compras en USD a crédito sin pagos registrados.
     * Útil para análisis de TC y decisión de cuándo pagar.
     */
    private function obtenerPendingPayments(?int $almacenId, float $tcActual): array
    {
        // Obtener todas las compras en USD a crédito (forma_de_pago = 'cr') sin pagos
        $comprasQuery = DB::table('compra as c')
            ->join('proveedor as pr', 'c.proveedor_id', '=', 'pr.id')
            ->leftJoin('pagodecompra as pc', function ($join) {
                $join->on('c.id', '=', 'pc.compra_id')
                    ->where('pc.estado', true);
            })
            ->where('c.tipo_moneda', 'd')  // Solo USD
            ->where('c.forma_de_pago', 'cr')  // Solo a crédito
            ->whereNotIn('c.estado_de_compra', ['an', 'ee'])
            ->whereNull('pc.id')  // Sin pagos registrados
            ->whereNotNull('c.tipo_de_cambio')
            ->where('c.tipo_de_cambio', '>', 0)
            ->select(
                'c.id as compra_id',
                'c.fecha as compra_fecha',
                'c.serie',
                'c.numero',
                DB::raw('CAST(c.tipo_de_cambio AS DECIMAL(10,4)) as tc_compra'),
                'pr.razon_social as proveedor_nombre',
                DB::raw('DATEDIFF(CURDATE(), c.fecha) as dias_desde_compra')
            )
            ->distinct();

        if ($almacenId) {
            $comprasQuery->where('c.almacen_id', $almacenId);
        }

        $compras = $comprasQuery
            ->orderBy('c.fecha', 'asc')
            ->get();

        // Obtener montos totales por compra
        $compraIds = $compras->pluck('compra_id')->toArray();
        
        $montosQuery = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->whereIn('pac.compra_id', $compraIds)
            ->where('udic.bonificacion', false)
            ->select(
                'pac.compra_id',
                DB::raw('SUM(CAST(udic.cantidad AS DECIMAL(10,4)) * CAST(udic.factor AS DECIMAL(10,4))) as cantidad_total'),
                DB::raw('SUM(CAST(pac.costo AS DECIMAL(10,4)) * CAST(udic.cantidad AS DECIMAL(10,4)) * CAST(udic.factor AS DECIMAL(10,4))) as costo_total_soles')
            )
            ->groupBy('pac.compra_id')
            ->get();

        $montosMap = $montosQuery->keyBy('compra_id')->toArray();

        // Construir respuesta
        $pendingPayments = [];
        foreach ($compras as $compra) {
            $montos = $montosMap[$compra->compra_id] ?? null;
            if (!$montos) continue;

            $tcCompra = (float) $compra->tc_compra;
            $variacionTc = $tcActual - $tcCompra;
            $variacionPorcentaje = ($variacionTc / $tcCompra) * 100;
            $riesgoAlto = abs($variacionPorcentaje) > 2;

            // Costo en USD
            $costoTotalSoles = (float) $montos->costo_total_soles;
            $costoUsd = $costoTotalSoles / $tcCompra;
            
            // Impacto si pagas hoy
            $impactoSiPagasHoy = $costoUsd * $variacionTc;

            $pendingPayments[] = [
                'compra_id' => $compra->compra_id,
                'serie_numero' => ($compra->serie ?? '') . '-' . ($compra->numero ?? ''),
                'fecha' => $compra->compra_fecha,
                'proveedor' => $compra->proveedor_nombre,
                'dias_desde_compra' => (int) $compra->dias_desde_compra,
                'cantidad_total' => (float) $montos->cantidad_total,
                'costo_usd' => round($costoUsd, 4),
                'costo_soles' => round($costoTotalSoles, 4),
                'tc_compra' => $tcCompra,
                'tc_actual' => $tcActual,
                'variacion_tc' => round($variacionTc, 4),
                'variacion_porcentaje' => round($variacionPorcentaje, 2),
                'riesgo_alto' => $riesgoAlto,
                'impacto_si_pagas_hoy' => round($impactoSiPagasHoy, 4),
                'recomendacion' => $variacionPorcentaje > 0 ? 'Pagar pronto (TC subió)' : 'Esperar (TC bajó)',
            ];
        }

        return $pendingPayments;
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
