<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexController extends Controller
{
    /**
     * Kardex de facturación (ventas, cotizaciones, préstamos, guías).
     * Optimizado: UNION ALL en SQL + paginación real en DB.
     */
    public function index(Request $request)
    {
        $request->validate([
            'producto_id' => 'nullable|integer',
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
        $page = $request->page ?? 1;

        $union = $this->buildFacturacionUnion($productoId, $almacenId, $desde, $hasta, $tipo);

        if (!$union) {
            return $this->emptyKardexResponse($productoId, $almacenId, $perPage);
        }

        return $this->paginateKardex($union['sql'], $union['bindings'], $productoId, $almacenId, $perPage, $page);
    }

    /**
     * Kardex de inventario (compras, recepciones, ingresos, salidas).
     * Optimizado: UNION ALL en SQL + paginación real en DB.
     */
    public function inventario(Request $request)
    {
        $request->validate([
            'producto_id' => 'nullable|integer',
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
        $page = $request->page ?? 1;

        $union = $this->buildInventarioUnion($productoId, $almacenId, $desde, $hasta, $tipo);

        if (!$union) {
            return $this->emptyKardexResponse($productoId, $almacenId, $perPage);
        }

        return $this->paginateKardex($union['sql'], $union['bindings'], $productoId, $almacenId, $perPage, $page);
    }

    /**
     * Ejecuta el UNION ALL con paginación real en DB y calcula saldos.
     * En vez de cargar todos los registros en memoria, usa:
     * 1. COUNT + SUM en la DB para totales
     * 2. SUM parcial para el saldo acumulado hasta el offset
     * 3. LIMIT/OFFSET para la página actual
     */
    private function paginateKardex(string $unionSql, array $bindings, ?int $productoId, ?int $almacenId, int $perPage, int $page)
    {
        $wrappedSql = "SELECT * FROM ({$unionSql}) as movimientos";

        // 1. Totales: COUNT + SUM entradas/salidas (1 query)
        $totals = DB::selectOne(
            "SELECT COUNT(*) as total, COALESCE(SUM(entrada), 0) as total_entradas, COALESCE(SUM(salida), 0) as total_salidas FROM ({$unionSql}) as t",
            $bindings
        );

        $total = (int) $totals->total;
        $totalEntradas = (float) $totals->total_entradas;
        $totalSalidas = (float) $totals->total_salidas;

        // Stock actual y Saldo Inicial solo si hay producto específico filtrado
        $stockActual = 0;
        $saldoInicial = 0;

        if ($productoId) {
            $stockActual = (float) DB::table('productoalmacen')
                ->where('producto_id', $productoId)
                ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
                ->sum('stock_fraccion');
            
            $saldoInicial = $stockActual + $totalSalidas - $totalEntradas;
        }

        if ($total === 0) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'last_page' => 1,
                'stock_actual' => $stockActual,
                'saldo_inicial' => round($saldoInicial, 4),
            ]);
        }

        if ($perPage == -1) {
            // Sin paginación: traer todo ordenado
            $rows = DB::select("{$wrappedSql} ORDER BY fecha ASC, referencia_id ASC", $bindings);
            $saldoActual = $saldoInicial;
            $data = [];
            foreach ($rows as $row) {
                if ($productoId) {
                    $saldoActual += $row->entrada - $row->salida;
                    $row->saldo = round($saldoActual, 4);
                } else {
                    $row->saldo = null; // No calcular saldo global para múltiples productos
                }
                $data[] = $row;
            }

            return response()->json([
                'data' => $data,
                'total' => $total,
                'current_page' => 1,
                'per_page' => -1,
                'last_page' => 1,
                'stock_actual' => $stockActual,
                'saldo_inicial' => round($saldoInicial, 4),
            ]);
        }

        $offset = ($page - 1) * $perPage;

        // 2. Saldo acumulado hasta el offset (solo si hay producto específico)
        $saldoAtOffset = $saldoInicial;
        if ($productoId && $offset > 0) {
            $prePage = DB::selectOne(
                "SELECT COALESCE(SUM(entrada), 0) as sum_entradas, COALESCE(SUM(salida), 0) as sum_salidas FROM (SELECT entrada, salida FROM ({$unionSql}) as t ORDER BY fecha ASC, referencia_id ASC LIMIT ?) as pre",
                array_merge($bindings, [$offset])
            );
            $saldoAtOffset = $saldoInicial + (float) $prePage->sum_entradas - (float) $prePage->sum_salidas;
        }

        // 3. Página actual
        $rows = DB::select(
            "{$wrappedSql} ORDER BY fecha ASC, referencia_id ASC LIMIT ? OFFSET ?",
            array_merge($bindings, [$perPage, $offset])
        );

        // 4. Calcular saldo running solo para la página si aplica
        $saldoActual = $saldoAtOffset;
        $data = [];
        foreach ($rows as $row) {
            if ($productoId) {
                $saldoActual += $row->entrada - $row->salida;
                $row->saldo = round($saldoActual, 4);
            } else {
                $row->saldo = null;
            }
            $data[] = $row;
        }

        return response()->json([
            'data' => $data,
            'total' => $total,
            'current_page' => (int) $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
            'stock_actual' => $stockActual,
            'saldo_inicial' => round($saldoInicial, 4),
        ]);
    }

    /**
     * Respuesta vacía para cuando no hay subqueries que ejecutar.
     */
    private function emptyKardexResponse(?int $productoId, ?int $almacenId, int $perPage)
    {
        $stockActual = 0;
        if ($productoId) {
            $stockActual = (float) DB::table('productoalmacen')
                ->where('producto_id', $productoId)
                ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
                ->sum('stock_fraccion');
        }

        return response()->json([
            'data' => [],
            'total' => 0,
            'current_page' => 1,
            'per_page' => $perPage,
            'last_page' => 1,
            'stock_actual' => $stockActual,
            'saldo_inicial' => round($stockActual, 4),
        ]);
    }

    /**
     * Construye UNION ALL para kardex de facturación.
     * Retorna ['sql' => string, 'bindings' => array] o null si no hay queries.
     */
    private function buildFacturacionUnion(?int $productoId, ?int $almacenId, ?string $desde, ?string $hasta, ?string $tipo): ?array
    {
        $queries = [];
        $bindings = [];

        // VENTAS (SALIDA) - incluye todas, anuladas aparecen tachadas
        if (!$tipo || $tipo === 'venta') {
            $whereBase = "1=1";
            $params = [];

            if ($productoId) {
                $whereBase .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $whereBase .= " AND v.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $whereBase .= " AND DATE(v.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $whereBase .= " AND DATE(v.fecha) <= ?";
                $params[] = $hasta;
            }

            // Fila de SALIDA para todas las ventas (activas y anuladas)
            $queries[] = "SELECT
                'venta' as tipo,
                CASE WHEN v.estado_de_venta = 'an' THEN 'ANULADO' ELSE 'SALIDA' END as movimiento,
                v.fecha,
                CONCAT(
                    CASE v.tipo_documento WHEN '01' THEN 'Factura' WHEN '03' THEN 'Boleta' WHEN 'nv' THEN 'Nota de Venta' ELSE v.tipo_documento END,
                    ' ', COALESCE(v.serie, ''), '-', LPAD(v.numero, 4, '0')
                ) as documento,
                udi.name as unidad,
                CAST(udv.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udv.cantidad * udv.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(udv.precio AS DECIMAL(16,4)) as precio,
                CAST(pav.costo AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(udv.cantidad * udv.factor AS DECIMAL(16,4)) as salida,
                v.id as referencia_id,
                pa.producto_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo
            FROM productoalmacenventa pav
            JOIN venta v ON v.id = pav.venta_id
            JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutableventa udv ON udv.producto_almacen_venta_id = pav.id
            JOIN unidadderivadainmutable udi ON udi.id = udv.unidad_derivada_inmutable_id
            WHERE {$whereBase}";
            $bindings = array_merge($bindings, $params);

            // Fila de ENTRADA (devolución) solo para ventas anuladas
            $whereAnulada = "v.estado_de_venta = 'an'";
            $paramsAnulada = [];

            if ($productoId) {
                $whereAnulada .= " AND pa.producto_id = ?";
                $paramsAnulada[] = $productoId;
            }
            if ($almacenId) {
                $whereAnulada .= " AND v.almacen_id = ?";
                $paramsAnulada[] = $almacenId;
            }
            if ($desde) {
                $whereAnulada .= " AND DATE(v.fecha) >= ?";
                $paramsAnulada[] = $desde;
            }
            if ($hasta) {
                $whereAnulada .= " AND DATE(v.fecha) <= ?";
                $paramsAnulada[] = $hasta;
            }

            $queries[] = "SELECT
                'venta' as tipo,
                'ANULADO' as movimiento,
                v.fecha,
                CONCAT(
                    'Anulación ',
                    CASE v.tipo_documento WHEN '01' THEN 'Factura' WHEN '03' THEN 'Boleta' WHEN 'nv' THEN 'Nota de Venta' ELSE v.tipo_documento END,
                    ' ', COALESCE(v.serie, ''), '-', LPAD(v.numero, 4, '0')
                ) as documento,
                udi.name as unidad,
                CAST(udv.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udv.cantidad * udv.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(udv.precio AS DECIMAL(16,4)) as precio,
                CAST(pav.costo AS DECIMAL(16,4)) as costo,
                CAST(udv.cantidad * udv.factor AS DECIMAL(16,4)) as entrada,
                CAST(0 AS DECIMAL(16,4)) as salida,
                v.id as referencia_id,
                pa.producto_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo
            FROM productoalmacenventa pav
            JOIN venta v ON v.id = pav.venta_id
            JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutableventa udv ON udv.producto_almacen_venta_id = pav.id
            JOIN unidadderivadainmutable udi ON udi.id = udv.unidad_derivada_inmutable_id
            WHERE {$whereAnulada}";
            $bindings = array_merge($bindings, $paramsAnulada);
        }

        // COTIZACIONES (REFERENCIA - no afecta stock)
        if (!$tipo || $tipo === 'cotizacion') {
            $where = "c.estado_cotizacion != 'ca'";
            $params = [];

            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND pa.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(c.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(c.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'cotizacion' as tipo,
                'REFERENCIA' as movimiento,
                c.fecha,
                CONCAT('Cotizacion ', c.numero, ' (',
                    CASE c.estado_cotizacion WHEN 'pe' THEN 'Pendiente' WHEN 'co' THEN 'Convertida' WHEN 've' THEN 'Vencida' ELSE c.estado_cotizacion END,
                ')') as documento,
                udi.name as unidad,
                CAST(udc.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udc.cantidad * udc.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(udc.precio AS DECIMAL(16,4)) as precio,
                CAST(pac.costo AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(0 AS DECIMAL(16,4)) as salida,
                c.id as referencia_id,
                pa.producto_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo
            FROM productoalmacencotizacion pac
            JOIN cotizacion c ON c.id = pac.cotizacion_id
            JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutablecotizacion udc ON udc.producto_almacen_cotizacion_id = pac.id
            JOIN unidadderivadainmutable udi ON udi.id = udc.unidad_derivada_inmutable_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        // PRESTAMOS (SALIDA)
        if (!$tipo || $tipo === 'prestamo') {
            $where = "p.tipo_operacion = 'PRESTAR'";
            $params = [];

            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND pa.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(p.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(p.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'prestamo' as tipo,
                'SALIDA' as movimiento,
                p.fecha,
                CONCAT('Prestamo PREST-', p.numero) as documento,
                udp.name as unidad,
                CAST(udp.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udp.cantidad * udp.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(pap.costo AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(udp.cantidad * udp.factor AS DECIMAL(16,4)) as salida,
                p.id as referencia_id,
                pa.producto_id,
                prod.name as producto_nombre,
                prod.cod_producto as producto_codigo
            FROM productoalmacenprestamo pap
            JOIN prestamos p ON p.id = pap.prestamo_id
            JOIN productoalmacen pa ON pa.id = pap.producto_almacen_id
            JOIN producto prod ON prod.id = pa.producto_id
            JOIN unidadderivadainmutableprestamo udp ON udp.producto_almacen_prestamo_id = pap.id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        // GUIAS DE REMISION (SALIDA)
        if (!$tipo || $tipo === 'guia') {
            $where = "gr.afecta_stock = 1 AND gr.estado != 'ANULADA'";
            $params = [];

            if ($productoId) {
                $where .= " AND dgr.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND gr.almacen_origen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(gr.fecha_emision) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(gr.fecha_emision) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'guia' as tipo,
                'SALIDA' as movimiento,
                gr.fecha_emision as fecha,
                CONCAT('Guia ', COALESCE(gr.serie, ''), '-', LPAD(gr.numero, 4, '0')) as documento,
                dgr.unidad_derivada_inmutable_name as unidad,
                CAST(dgr.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(dgr.cantidad * dgr.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(0 AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(dgr.cantidad * dgr.factor AS DECIMAL(16,4)) as salida,
                gr.id as referencia_id,
                dgr.producto_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo
            FROM detalle_guia_remision dgr
            JOIN guia_remision gr ON gr.id = dgr.guia_remision_id
            JOIN producto p ON p.id = dgr.producto_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        if (empty($queries)) {
            return null;
        }

        return [
            'sql' => implode(' UNION ALL ', $queries),
            'bindings' => $bindings,
        ];
    }

    /**
     * Construye UNION ALL para kardex de inventario.
     * Retorna ['sql' => string, 'bindings' => array] o null si no hay queries.
     */
    private function buildInventarioUnion(?int $productoId, ?int $almacenId, ?string $desde, ?string $hasta, ?string $tipo): ?array
    {
        $queries = [];
        $bindings = [];

        // COMPRAS (ENTRADA)
        if (!$tipo || $tipo === 'compra') {
            $where = "c.estado_de_compra != 'an'";
            $params = [];
            
            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND c.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(c.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(c.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'compra' as tipo,
                'COMPRA' as movimiento,
                c.fecha,
                CONCAT('Compra ',
                    CASE c.tipo_documento WHEN '01' THEN 'Factura' WHEN '03' THEN 'Boleta' WHEN 'nv' THEN 'Nota de Venta' ELSE c.tipo_documento END,
                    ' ', COALESCE(c.serie, ''), '-', COALESCE(c.numero, 0)
                ) as documento,
                udi.name as unidad,
                CAST(udc.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udc.cantidad * udc.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(pac.costo AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(0 AS DECIMAL(16,4)) as salida,
                c.id as referencia_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo,
                pa.producto_id
            FROM productoalmacencompra pac
            JOIN compra c ON c.id = pac.compra_id
            JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutablecompra udc ON udc.producto_almacen_compra_id = pac.id
            JOIN unidadderivadainmutable udi ON udi.id = udc.unidad_derivada_inmutable_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        // RECEPCIONES (ENTRADA)
        if (!$tipo || $tipo === 'recepcion') {
            $where = "r.estado = 1";
            $params = [];
            
            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND pa.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(r.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(r.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'recepcion' as tipo,
                'ENTRADA' as movimiento,
                r.fecha,
                CONCAT('Recepcion REC-', r.numero) as documento,
                udi.name as unidad,
                CAST(udr.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udr.cantidad * udr.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(par.costo AS DECIMAL(16,4)) as costo,
                CAST(CASE WHEN r.es_finalizacion = 1 THEN 0 ELSE udr.cantidad * udr.factor END AS DECIMAL(16,4)) as entrada,
                CAST(0 AS DECIMAL(16,4)) as salida,
                r.id as referencia_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo,
                pa.producto_id
            FROM productoalmacenrecepcion par
            JOIN recepcionalmacen r ON r.id = par.recepcion_id
            JOIN productoalmacen pa ON pa.id = par.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutablerecepcion udr ON udr.producto_almacen_recepcion_id = par.id
            JOIN unidadderivadainmutable udi ON udi.id = udr.unidad_derivada_inmutable_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        // INGRESOS (ENTRADA)
        if (!$tipo || $tipo === 'ingreso') {
            $where = "is_t.tipo_documento = 'in' AND is_t.estado = 1";
            $params = [];
            
            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND is_t.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(is_t.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(is_t.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'ingreso' as tipo,
                'ENTRADA' as movimiento,
                is_t.fecha,
                CONCAT('Ingreso ING-', is_t.serie, '-', is_t.numero) as documento,
                udi.name as unidad,
                CAST(udis.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udis.cantidad * udis.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(pais.costo AS DECIMAL(16,4)) as costo,
                CAST(udis.cantidad * udis.factor AS DECIMAL(16,4)) as entrada,
                CAST(0 AS DECIMAL(16,4)) as salida,
                is_t.id as referencia_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo,
                pa.producto_id
            FROM productoalmaceningresosalida pais
            JOIN ingresosalida is_t ON is_t.id = pais.ingreso_id
            JOIN productoalmacen pa ON pa.id = pais.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutableingresosalida udis ON udis.producto_almacen_ingreso_salida_id = pais.id
            JOIN unidadderivadainmutable udi ON udi.id = udis.unidad_derivada_inmutable_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        // SALIDAS (SALIDA)
        if (!$tipo || $tipo === 'salida') {
            $where = "is_t.tipo_documento = 'sa' AND is_t.estado = 1";
            $params = [];
            
            if ($productoId) {
                $where .= " AND pa.producto_id = ?";
                $params[] = $productoId;
            }

            if ($almacenId) {
                $where .= " AND is_t.almacen_id = ?";
                $params[] = $almacenId;
            }
            if ($desde) {
                $where .= " AND DATE(is_t.fecha) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $where .= " AND DATE(is_t.fecha) <= ?";
                $params[] = $hasta;
            }

            $queries[] = "SELECT
                'salida' as tipo,
                'SALIDA' as movimiento,
                is_t.fecha,
                CONCAT('Salida SAL-', is_t.serie, '-', is_t.numero) as documento,
                udi.name as unidad,
                CAST(udis.cantidad AS DECIMAL(16,4)) as cantidad,
                CAST(udis.cantidad * udis.factor AS DECIMAL(16,4)) as cantidad_fraccion,
                CAST(0 AS DECIMAL(16,4)) as precio,
                CAST(pais.costo AS DECIMAL(16,4)) as costo,
                CAST(0 AS DECIMAL(16,4)) as entrada,
                CAST(udis.cantidad * udis.factor AS DECIMAL(16,4)) as salida,
                is_t.id as referencia_id,
                p.name as producto_nombre,
                p.cod_producto as producto_codigo,
                pa.producto_id
            FROM productoalmaceningresosalida pais
            JOIN ingresosalida is_t ON is_t.id = pais.ingreso_id
            JOIN productoalmacen pa ON pa.id = pais.producto_almacen_id
            JOIN producto p ON p.id = pa.producto_id
            JOIN unidadderivadainmutableingresosalida udis ON udis.producto_almacen_ingreso_salida_id = pais.id
            JOIN unidadderivadainmutable udi ON udi.id = udis.unidad_derivada_inmutable_id
            WHERE {$where}";
            $bindings = array_merge($bindings, $params);
        }

        if (empty($queries)) {
            return null;
        }

        return [
            'sql' => implode(' UNION ALL ', $queries),
            'bindings' => $bindings,
        ];
    }
}
