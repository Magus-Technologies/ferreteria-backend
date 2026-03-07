<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexController extends Controller
{
    /**
     * Obtener el kardex de movimientos de un producto.
     * Solo movimientos del modulo facturacion-electronica:
     * - Ventas (SALIDA)
     * - Cotizaciones (REFERENCIA - no afecta stock)
     * - Prestamos (SALIDA)
     * - Guias de Remision (SALIDA si afecta stock)
     */
    public function index(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|integer',
            'almacen_id' => 'nullable|integer',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'tipo' => 'nullable|string|in:venta,cotizacion,prestamo,guia',
            'per_page' => 'nullable|integer|min:-1|max:200',
        ]);

        $productoId = $request->producto_id;
        $almacenId = $request->almacen_id;
        $desde = $request->desde;
        $hasta = $request->hasta;
        $tipo = $request->tipo;
        $perPage = $request->per_page ?? 50;

        $movimientos = collect();

        // 1. VENTAS (SALIDA)
        if (!$tipo || $tipo === 'venta') {
            $ventas = DB::table('productoalmacenventa as pav')
                ->join('venta as v', 'v.id', '=', 'pav.venta_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pav.producto_almacen_id')
                ->join('unidadderivadainmutableventa as udv', 'udv.producto_almacen_venta_id', '=', 'pav.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udv.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('v.estado_de_venta', '!=', 'an')
                ->when($almacenId, fn($q) => $q->where('v.almacen_id', $almacenId))
                ->when($desde, fn($q) => $q->whereDate('v.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('v.fecha', '<=', $hasta))
                ->select([
                    DB::raw("'venta' as tipo"),
                    'v.fecha',
                    DB::raw("CONCAT(COALESCE(v.serie, ''), '-', LPAD(v.numero, 4, '0')) as documento"),
                    DB::raw("CASE v.tipo_documento
                        WHEN '01' THEN 'Factura'
                        WHEN '03' THEN 'Boleta'
                        WHEN 'nv' THEN 'Nota de Venta'
                        ELSE v.tipo_documento
                    END as tipo_doc"),
                    'udi.name as unidad',
                    'udv.cantidad',
                    'udv.factor',
                    'udv.precio',
                    'pav.costo',
                    'v.id as referencia_id',
                    DB::raw("'SALIDA' as movimiento"),
                ])
                ->get();

            foreach ($ventas as $venta) {
                $cantidadFraccion = $venta->cantidad * $venta->factor;
                $movimientos->push([
                    'tipo' => 'venta',
                    'movimiento' => 'SALIDA',
                    'fecha' => $venta->fecha,
                    'documento' => $venta->tipo_doc . ' ' . $venta->documento,
                    'unidad' => $venta->unidad,
                    'cantidad' => (float) $venta->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => (float) $venta->precio,
                    'costo' => (float) $venta->costo,
                    'entrada' => 0,
                    'salida' => (float) $cantidadFraccion,
                    'referencia_id' => $venta->referencia_id,
                ]);
            }
        }

        // 2. COTIZACIONES (REFERENCIA - no afecta stock)
        if (!$tipo || $tipo === 'cotizacion') {
            $cotizaciones = DB::table('productoalmacencotizacion as pac')
                ->join('cotizacion as c', 'c.id', '=', 'pac.cotizacion_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pac.producto_almacen_id')
                ->join('unidadderivadainmutablecotizacion as udc', 'udc.producto_almacen_cotizacion_id', '=', 'pac.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udc.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('c.estado_cotizacion', '!=', 'ca')
                ->when($almacenId, fn($q) => $q->whereExists(function ($sub) use ($almacenId) {
                    $sub->select(DB::raw(1))
                        ->from('productoalmacen as pa2')
                        ->whereColumn('pa2.id', 'pac.producto_almacen_id')
                        ->where('pa2.almacen_id', $almacenId);
                }))
                ->when($desde, fn($q) => $q->whereDate('c.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('c.fecha', '<=', $hasta))
                ->select([
                    'c.fecha',
                    DB::raw("c.numero as documento"),
                    'udi.name as unidad',
                    'udc.cantidad',
                    'udc.factor',
                    'udc.precio',
                    'pac.costo',
                    'c.id as referencia_id',
                    'c.estado_cotizacion',
                ])
                ->get();

            foreach ($cotizaciones as $cot) {
                $cantidadFraccion = $cot->cantidad * $cot->factor;
                $estado = match ($cot->estado_cotizacion) {
                    'pe' => 'Pendiente',
                    'co' => 'Convertida',
                    've' => 'Vencida',
                    default => $cot->estado_cotizacion,
                };
                $movimientos->push([
                    'tipo' => 'cotizacion',
                    'movimiento' => 'REFERENCIA',
                    'fecha' => $cot->fecha,
                    'documento' => 'Cotizacion ' . $cot->documento . ' (' . $estado . ')',
                    'unidad' => $cot->unidad,
                    'cantidad' => (float) $cot->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => (float) $cot->precio,
                    'costo' => (float) $cot->costo,
                    'entrada' => 0,
                    'salida' => 0,
                    'referencia_id' => $cot->referencia_id,
                ]);
            }
        }

        // 3. PRESTAMOS (SALIDA)
        if (!$tipo || $tipo === 'prestamo') {
            $prestamos = DB::table('productoalmacenprestamo as pap')
                ->join('prestamos as p', 'p.id', '=', 'pap.prestamo_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pap.producto_almacen_id')
                ->join('unidadderivadainmutableprestamo as udp', 'udp.producto_almacen_prestamo_id', '=', 'pap.id')
                ->where('pa.producto_id', $productoId)
                ->where('p.tipo_operacion', 'PRESTAR')
                ->when($almacenId, fn($q) => $q->whereExists(function ($sub) use ($almacenId) {
                    $sub->select(DB::raw(1))
                        ->from('productoalmacen as pa2')
                        ->whereColumn('pa2.id', 'pap.producto_almacen_id')
                        ->where('pa2.almacen_id', $almacenId);
                }))
                ->when($desde, fn($q) => $q->whereDate('p.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('p.fecha', '<=', $hasta))
                ->select([
                    'p.fecha',
                    DB::raw("CONCAT('PREST-', p.numero) as documento"),
                    'udp.name as unidad',
                    'udp.cantidad',
                    'udp.factor',
                    'pap.costo',
                    'p.id as referencia_id',
                ])
                ->get();

            foreach ($prestamos as $prest) {
                $cantidadFraccion = $prest->cantidad * $prest->factor;
                $movimientos->push([
                    'tipo' => 'prestamo',
                    'movimiento' => 'SALIDA',
                    'fecha' => $prest->fecha,
                    'documento' => 'Prestamo ' . $prest->documento,
                    'unidad' => $prest->unidad,
                    'cantidad' => (float) $prest->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => (float) $prest->costo,
                    'entrada' => 0,
                    'salida' => (float) $cantidadFraccion,
                    'referencia_id' => $prest->referencia_id,
                ]);
            }
        }

        // 4. GUIAS DE REMISION (SALIDA si afecta stock)
        if (!$tipo || $tipo === 'guia') {
            $guias = DB::table('detalle_guia_remision as dgr')
                ->join('guia_remision as gr', 'gr.id', '=', 'dgr.guia_remision_id')
                ->where('dgr.producto_id', $productoId)
                ->where('gr.afecta_stock', true)
                ->when($almacenId, fn($q) => $q->where('gr.almacen_origen_id', $almacenId))
                ->when($desde, fn($q) => $q->whereDate('gr.fecha_emision', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('gr.fecha_emision', '<=', $hasta))
                ->select([
                    'gr.fecha_emision as fecha',
                    DB::raw("CONCAT(COALESCE(gr.serie, ''), '-', LPAD(gr.numero, 4, '0')) as documento"),
                    'dgr.unidad_derivada_inmutable_name as unidad',
                    'dgr.cantidad',
                    'dgr.factor',
                    'gr.id as referencia_id',
                ])
                ->get();

            foreach ($guias as $guia) {
                $cantidadFraccion = $guia->cantidad * $guia->factor;
                $movimientos->push([
                    'tipo' => 'guia',
                    'movimiento' => 'SALIDA',
                    'fecha' => $guia->fecha,
                    'documento' => 'Guia ' . $guia->documento,
                    'unidad' => $guia->unidad,
                    'cantidad' => (float) $guia->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => 0,
                    'entrada' => 0,
                    'salida' => (float) $cantidadFraccion,
                    'referencia_id' => $guia->referencia_id,
                ]);
            }
        }

        // Ordenar por fecha ascendente y calcular saldo
        $movimientos = $movimientos->sortBy('fecha')->values();

        // Obtener stock actual para calcular saldo retroactivo
        $stockActual = DB::table('productoalmacen')
            ->where('producto_id', $productoId)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->sum('stock_fraccion');

        // Calcular saldo acumulado
        // Saldo final = stock actual
        // Recorremos de atras hacia adelante para calcular
        $saldo = (float) $stockActual;
        $movimientosArray = $movimientos->toArray();

        // Primero calculamos el saldo total de los movimientos mostrados
        $totalEntradas = 0;
        $totalSalidas = 0;
        foreach ($movimientosArray as $m) {
            $totalEntradas += $m['entrada'];
            $totalSalidas += $m['salida'];
        }

        // El saldo antes de todos estos movimientos
        $saldoInicial = $saldo + $totalSalidas - $totalEntradas;
        $saldoActual = $saldoInicial;

        for ($i = 0; $i < count($movimientosArray); $i++) {
            $saldoActual += $movimientosArray[$i]['entrada'] - $movimientosArray[$i]['salida'];
            $movimientosArray[$i]['saldo'] = round($saldoActual, 4);
        }

        // Paginar manualmente
        $total = count($movimientosArray);
        $page = $request->page ?? 1;

        if ($perPage == -1) {
            $data = $movimientosArray;
        } else {
            $offset = ($page - 1) * $perPage;
            $data = array_slice($movimientosArray, $offset, $perPage);
        }

        return response()->json([
            'data' => array_values($data),
            'total' => $total,
            'current_page' => (int) $page,
            'per_page' => $perPage,
            'last_page' => $perPage == -1 ? 1 : (int) ceil($total / $perPage),
            'stock_actual' => (float) $stockActual,
            'saldo_inicial' => round($saldoInicial, 4),
        ]);
    }

    /**
     * Kardex de inventario (gestion-comercial-e-inventario):
     * - Compras (ENTRADA)
     * - Recepciones (ENTRADA)
     * - Ingresos (ENTRADA)
     * - Salidas (SALIDA)
     */
    public function inventario(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|integer',
            'almacen_id' => 'nullable|integer',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'tipo' => 'nullable|string|in:compra,recepcion,ingreso,salida',
            'per_page' => 'nullable|integer|min:-1|max:200',
        ]);

        $productoId = $request->producto_id;
        $almacenId = $request->almacen_id;
        $desde = $request->desde;
        $hasta = $request->hasta;
        $tipo = $request->tipo;
        $perPage = $request->per_page ?? 50;

        $movimientos = collect();

        // 1. COMPRAS (ENTRADA)
        if (!$tipo || $tipo === 'compra') {
            $compras = DB::table('productoalmacencompra as pac')
                ->join('compra as c', 'c.id', '=', 'pac.compra_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pac.producto_almacen_id')
                ->join('unidadderivadainmutablecompra as udc', 'udc.producto_almacen_compra_id', '=', 'pac.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udc.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('c.estado_de_compra', '!=', 'an')
                ->when($almacenId, fn($q) => $q->where('c.almacen_id', $almacenId))
                ->when($desde, fn($q) => $q->whereDate('c.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('c.fecha', '<=', $hasta))
                ->select([
                    'c.fecha',
                    DB::raw("CONCAT(COALESCE(c.serie, ''), '-', COALESCE(c.numero, 0)) as documento"),
                    DB::raw("CASE c.tipo_documento
                        WHEN '01' THEN 'Factura'
                        WHEN '03' THEN 'Boleta'
                        WHEN 'nv' THEN 'Nota de Venta'
                        ELSE c.tipo_documento
                    END as tipo_doc"),
                    'udi.name as unidad',
                    'udc.cantidad',
                    'udc.factor',
                    'pac.costo',
                    'c.id as referencia_id',
                ])
                ->get();

            foreach ($compras as $compra) {
                $cantidadFraccion = $compra->cantidad * $compra->factor;
                $movimientos->push([
                    'tipo' => 'compra',
                    'movimiento' => 'ENTRADA',
                    'fecha' => $compra->fecha,
                    'documento' => 'Compra ' . $compra->tipo_doc . ' ' . $compra->documento,
                    'unidad' => $compra->unidad,
                    'cantidad' => (float) $compra->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => (float) $compra->costo,
                    'entrada' => (float) $cantidadFraccion,
                    'salida' => 0,
                    'referencia_id' => $compra->referencia_id,
                ]);
            }
        }

        // 2. RECEPCIONES (ENTRADA)
        if (!$tipo || $tipo === 'recepcion') {
            $recepciones = DB::table('productoalmacenrecepcion as par')
                ->join('recepcionalmacen as r', 'r.id', '=', 'par.recepcion_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'par.producto_almacen_id')
                ->join('unidadderivadainmutablerecepcion as udr', 'udr.producto_almacen_recepcion_id', '=', 'par.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udr.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('r.estado', true)
                ->when($almacenId, fn($q) => $q->whereExists(function ($sub) use ($almacenId) {
                    $sub->select(DB::raw(1))
                        ->from('productoalmacen as pa2')
                        ->whereColumn('pa2.id', 'par.producto_almacen_id')
                        ->where('pa2.almacen_id', $almacenId);
                }))
                ->when($desde, fn($q) => $q->whereDate('r.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('r.fecha', '<=', $hasta))
                ->select([
                    'r.fecha',
                    DB::raw("CONCAT('REC-', r.numero) as documento"),
                    'udi.name as unidad',
                    'udr.cantidad',
                    'udr.factor',
                    'par.costo',
                    'r.id as referencia_id',
                ])
                ->get();

            foreach ($recepciones as $rec) {
                $cantidadFraccion = $rec->cantidad * $rec->factor;
                $movimientos->push([
                    'tipo' => 'recepcion',
                    'movimiento' => 'ENTRADA',
                    'fecha' => $rec->fecha,
                    'documento' => 'Recepcion ' . $rec->documento,
                    'unidad' => $rec->unidad,
                    'cantidad' => (float) $rec->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => (float) $rec->costo,
                    'entrada' => (float) $cantidadFraccion,
                    'salida' => 0,
                    'referencia_id' => $rec->referencia_id,
                ]);
            }
        }

        // 3. INGRESOS (ENTRADA)
        if (!$tipo || $tipo === 'ingreso') {
            $ingresos = DB::table('productoalmaceningresosalida as pais')
                ->join('ingresosalida as is_t', 'is_t.id', '=', 'pais.ingreso_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pais.producto_almacen_id')
                ->join('unidadderivadainmutableingresosalida as udis', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udis.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('is_t.tipo_documento', 'in')
                ->where('is_t.estado', true)
                ->when($almacenId, fn($q) => $q->where('is_t.almacen_id', $almacenId))
                ->when($desde, fn($q) => $q->whereDate('is_t.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('is_t.fecha', '<=', $hasta))
                ->select([
                    'is_t.fecha',
                    DB::raw("CONCAT('ING-', is_t.serie, '-', is_t.numero) as documento"),
                    'udi.name as unidad',
                    'udis.cantidad',
                    'udis.factor',
                    'pais.costo',
                    'is_t.id as referencia_id',
                ])
                ->get();

            foreach ($ingresos as $ing) {
                $cantidadFraccion = $ing->cantidad * $ing->factor;
                $movimientos->push([
                    'tipo' => 'ingreso',
                    'movimiento' => 'ENTRADA',
                    'fecha' => $ing->fecha,
                    'documento' => 'Ingreso ' . $ing->documento,
                    'unidad' => $ing->unidad,
                    'cantidad' => (float) $ing->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => (float) $ing->costo,
                    'entrada' => (float) $cantidadFraccion,
                    'salida' => 0,
                    'referencia_id' => $ing->referencia_id,
                ]);
            }
        }

        // 4. SALIDAS (SALIDA)
        if (!$tipo || $tipo === 'salida') {
            $salidas = DB::table('productoalmaceningresosalida as pais')
                ->join('ingresosalida as is_t', 'is_t.id', '=', 'pais.ingreso_id')
                ->join('productoalmacen as pa', 'pa.id', '=', 'pais.producto_almacen_id')
                ->join('unidadderivadainmutableingresosalida as udis', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udis.unidad_derivada_inmutable_id')
                ->where('pa.producto_id', $productoId)
                ->where('is_t.tipo_documento', 'sa')
                ->where('is_t.estado', true)
                ->when($almacenId, fn($q) => $q->where('is_t.almacen_id', $almacenId))
                ->when($desde, fn($q) => $q->whereDate('is_t.fecha', '>=', $desde))
                ->when($hasta, fn($q) => $q->whereDate('is_t.fecha', '<=', $hasta))
                ->select([
                    'is_t.fecha',
                    DB::raw("CONCAT('SAL-', is_t.serie, '-', is_t.numero) as documento"),
                    'udi.name as unidad',
                    'udis.cantidad',
                    'udis.factor',
                    'pais.costo',
                    'is_t.id as referencia_id',
                ])
                ->get();

            foreach ($salidas as $sal) {
                $cantidadFraccion = $sal->cantidad * $sal->factor;
                $movimientos->push([
                    'tipo' => 'salida',
                    'movimiento' => 'SALIDA',
                    'fecha' => $sal->fecha,
                    'documento' => 'Salida ' . $sal->documento,
                    'unidad' => $sal->unidad,
                    'cantidad' => (float) $sal->cantidad,
                    'cantidad_fraccion' => (float) $cantidadFraccion,
                    'precio' => 0,
                    'costo' => (float) $sal->costo,
                    'entrada' => 0,
                    'salida' => (float) $cantidadFraccion,
                    'referencia_id' => $sal->referencia_id,
                ]);
            }
        }

        // Ordenar por fecha ascendente y calcular saldo
        $movimientos = $movimientos->sortBy('fecha')->values();

        // Obtener stock actual
        $stockActual = DB::table('productoalmacen')
            ->where('producto_id', $productoId)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->sum('stock_fraccion');

        $saldo = (float) $stockActual;
        $movimientosArray = $movimientos->toArray();

        $totalEntradas = 0;
        $totalSalidas = 0;
        foreach ($movimientosArray as $m) {
            $totalEntradas += $m['entrada'];
            $totalSalidas += $m['salida'];
        }

        $saldoInicial = $saldo + $totalSalidas - $totalEntradas;
        $saldoActual = $saldoInicial;

        for ($i = 0; $i < count($movimientosArray); $i++) {
            $saldoActual += $movimientosArray[$i]['entrada'] - $movimientosArray[$i]['salida'];
            $movimientosArray[$i]['saldo'] = round($saldoActual, 4);
        }

        // Paginar manualmente
        $total = count($movimientosArray);
        $page = $request->page ?? 1;

        if ($perPage == -1) {
            $data = $movimientosArray;
        } else {
            $offset = ($page - 1) * $perPage;
            $data = array_slice($movimientosArray, $offset, $perPage);
        }

        return response()->json([
            'data' => array_values($data),
            'total' => $total,
            'current_page' => (int) $page,
            'per_page' => $perPage,
            'last_page' => $perPage == -1 ? 1 : (int) ceil($total / $perPage),
            'stock_actual' => (float) $stockActual,
            'saldo_inicial' => round($saldoInicial, 4),
        ]);
    }
}
