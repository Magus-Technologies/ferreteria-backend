<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\ClienteReporteServiceInterface;
use Illuminate\Support\Facades\DB;

class ClienteReporteService implements ClienteReporteServiceInterface
{
    /**
     * Top clientes por monto de compras.
     * Usa udiv.precio * udiv.cantidad para totales reales.
     */
    public function obtenerTopClientes(array $filtros, int $limit = 10): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'v.fecha');

        $query->selectRaw("
            c.id,
            c.numero_documento,
            c.tipo_cliente,
            CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as nombre,
            SUM(udiv.precio * udiv.cantidad) as total_compras,
            COUNT(DISTINCT v.id) as num_ventas
        ")
        ->groupBy('c.id', 'c.numero_documento', 'c.tipo_cliente', 'c.razon_social', 'c.nombres', 'c.apellidos')
        ->orderByDesc('total_compras')
        ->limit($limit);

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'numero_documento' => $item->numero_documento,
                'tipo_cliente' => $item->tipo_cliente,
                'nombre' => $item->nombre,
                'total_compras' => round((float)$item->total_compras, 2),
                'num_ventas' => (int)$item->num_ventas,
            ];
        })->toArray();
    }

    /**
     * Resumen KPI de clientes
     */
    public function obtenerResumenClientes(array $filtros): array
    {
        $baseQuery = DB::table('cliente')
            ->where('numero_documento', '!=', '99999999');

        $totalClientes = (clone $baseQuery)->count();
        $clientesActivos = (clone $baseQuery)->where('estado', true)->count();
        $clientesInactivos = (clone $baseQuery)->where('estado', false)->count();
        $clientesPersona = (clone $baseQuery)->where('tipo_cliente', 'p')->count();
        $clientesEmpresa = (clone $baseQuery)->where('tipo_cliente', 'e')->count();

        // Clientes con deuda pendiente
        $clientesConDeuda = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.forma_de_pago', 'cr')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['almacen_id'])) {
            $clientesConDeuda->where('v.almacen_id', $filtros['almacen_id']);
        }

        $deudaInfo = $clientesConDeuda->selectRaw("
            COUNT(DISTINCT c.id) as clientes_con_deuda,
            SUM(
                (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
                 JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
                 WHERE pav2.venta_id = v.id)
            ) as total_credito,
            SUM(
                (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id AND dpv.estado = 1)
            ) as total_pagado
        ")->first();

        $totalCredito = round((float)($deudaInfo->total_credito ?? 0), 2);
        $totalPagado = round((float)($deudaInfo->total_pagado ?? 0), 2);

        // Clientes con primera venta en últimos 30 días (clientes "nuevos")
        $nuevos30 = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('c.numero_documento', '!=', '99999999')
            ->where('v.estado_de_venta', '!=', 'an')
            ->groupBy('c.id')
            ->havingRaw('MIN(v.fecha) >= ?', [now()->subDays(30)->format('Y-m-d')])
            ->select('c.id')
            ->get()
            ->count();

        return [
            'total_clientes' => $totalClientes,
            'clientes_activos' => $clientesActivos,
            'clientes_inactivos' => $clientesInactivos,
            'clientes_persona' => $clientesPersona,
            'clientes_empresa' => $clientesEmpresa,
            'clientes_con_deuda' => (int)($deudaInfo->clientes_con_deuda ?? 0),
            'total_por_cobrar' => round($totalCredito - $totalPagado, 2),
            'nuevos_30_dias' => $nuevos30,
        ];
    }

    /**
     * Clientes con deuda pendiente
     */
    public function obtenerClientesPorCobrar(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.forma_de_pago', 'cr')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }

        $query->selectRaw("
            c.id,
            c.numero_documento,
            c.tipo_cliente,
            CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as nombre,
            c.telefono,
            COUNT(DISTINCT v.id) as num_ventas_credito,
            SUM(
                (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
                 JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
                 WHERE pav2.venta_id = v.id)
            ) as total_credito,
            SUM(
                (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id AND dpv.estado = 1)
            ) as total_pagado
        ")
        ->groupBy('c.id', 'c.numero_documento', 'c.tipo_cliente', 'c.razon_social', 'c.nombres', 'c.apellidos', 'c.telefono')
        ->havingRaw("SUM(
            (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
             JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
             WHERE pav2.venta_id = v.id)
        ) - SUM(
            (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id AND dpv.estado = 1)
        ) > 0")
        ->orderByDesc(DB::raw("SUM(
            (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
             JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
             WHERE pav2.venta_id = v.id)
        ) - SUM(
            (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id AND dpv.estado = 1)
        )"));

        // Count total (subquery approach)
        $countQuery = clone $query;
        $total = DB::table(DB::raw("({$countQuery->toSql()}) as sub"))
            ->mergeBindings($countQuery)
            ->count();

        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        // Totales generales
        $resumenQuery = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.forma_de_pago', 'cr')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['almacen_id'])) {
            $resumenQuery->where('v.almacen_id', $filtros['almacen_id']);
        }

        $resumen = $resumenQuery->selectRaw("
            SUM(
                (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
                 JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
                 WHERE pav2.venta_id = v.id)
            ) as total_credito,
            SUM(
                (SELECT COALESCE(SUM(dpv.monto), 0) FROM desplieguedepagoventa dpv WHERE dpv.venta_id = v.id AND dpv.estado = 1)
            ) as total_pagado
        ")->first();

        return [
            'data' => $data->map(function ($item) {
                $credito = round((float)$item->total_credito, 2);
                $pagado = round((float)$item->total_pagado, 2);
                return [
                    'id' => $item->id,
                    'numero_documento' => $item->numero_documento,
                    'tipo_cliente' => $item->tipo_cliente,
                    'nombre' => $item->nombre,
                    'telefono' => $item->telefono,
                    'num_ventas_credito' => (int)$item->num_ventas_credito,
                    'total_credito' => $credito,
                    'total_pagado' => $pagado,
                    'saldo_pendiente' => round($credito - $pagado, 2),
                ];
            })->toArray(),
            'resumen' => [
                'total_credito' => round((float)($resumen->total_credito ?? 0), 2),
                'total_pagado' => round((float)($resumen->total_pagado ?? 0), 2),
                'total_por_cobrar' => round((float)($resumen->total_credito ?? 0) - (float)($resumen->total_pagado ?? 0), 2),
            ],
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Listado de clientes con historial de compras
     */
    public function obtenerListadoClientes(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = DB::table('cliente as c')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['tipo_cliente'])) {
            $query->where('c.tipo_cliente', $filtros['tipo_cliente']);
        }
        if (!empty($filtros['estado'])) {
            $query->where('c.estado', $filtros['estado'] === 'activo');
        }

        $query->selectRaw("
            c.id,
            c.numero_documento,
            c.tipo_cliente,
            CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as nombre,
            c.direccion,
            c.telefono,
            c.email,
            c.estado,
            (SELECT COUNT(DISTINCT v.id) FROM venta v WHERE v.cliente_id = c.id AND v.estado_de_venta != 'an') as total_ventas,
            (SELECT COALESCE(SUM(udiv3.precio * udiv3.cantidad), 0) 
             FROM unidadderivadainmutableventa udiv3
             JOIN productoalmacenventa pav3 ON udiv3.producto_almacen_venta_id = pav3.id
             JOIN venta v3 ON pav3.venta_id = v3.id
             WHERE v3.cliente_id = c.id AND v3.estado_de_venta != 'an') as total_compras
        ");

        $query->orderByDesc(DB::raw("(SELECT COALESCE(SUM(udiv3.precio * udiv3.cantidad), 0) 
             FROM unidadderivadainmutableventa udiv3
             JOIN productoalmacenventa pav3 ON udiv3.producto_almacen_venta_id = pav3.id
             JOIN venta v3 ON pav3.venta_id = v3.id
             WHERE v3.cliente_id = c.id AND v3.estado_de_venta != 'an')"));

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'numero_documento' => $item->numero_documento,
                    'tipo_cliente' => $item->tipo_cliente,
                    'nombre' => $item->nombre,
                    'direccion' => $item->direccion,
                    'telefono' => $item->telefono,
                    'email' => $item->email,
                    'estado' => (bool)$item->estado,
                    'total_ventas' => (int)$item->total_ventas,
                    'total_compras' => round((float)$item->total_compras, 2),
                ];
            })->toArray(),
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Clientes frecuentes (más transacciones)
     */
    public function obtenerClientesFrecuentes(array $filtros, int $limit = 10): array
    {
        $query = DB::table('venta as v')
            ->join('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('c.numero_documento', '!=', '99999999');

        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        }

        $this->aplicarFiltrosFecha($query, $filtros, 'v.fecha');

        $query->selectRaw("
            c.id,
            c.numero_documento,
            c.tipo_cliente,
            CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as nombre,
            COUNT(DISTINCT v.id) as num_ventas,
            SUM(
                (SELECT COALESCE(SUM(udiv2.precio * udiv2.cantidad), 0) FROM unidadderivadainmutableventa udiv2
                 JOIN productoalmacenventa pav2 ON udiv2.producto_almacen_venta_id = pav2.id
                 WHERE pav2.venta_id = v.id)
            ) as total_compras
        ")
        ->groupBy('c.id', 'c.numero_documento', 'c.tipo_cliente', 'c.razon_social', 'c.nombres', 'c.apellidos')
        ->orderByDesc('num_ventas')
        ->limit($limit);

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'numero_documento' => $item->numero_documento,
                'tipo_cliente' => $item->tipo_cliente,
                'nombre' => $item->nombre,
                'num_ventas' => (int)$item->num_ventas,
                'total_compras' => round((float)$item->total_compras, 2),
            ];
        })->toArray();
    }

    /**
     * Clientes con primera venta reciente (nuevos compradores).
     * Como la tabla cliente no tiene timestamps, usamos la fecha de primera venta.
     */
    public function obtenerClientesRecientes(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $dias = !empty($filtros['dias']) ? (int)$filtros['dias'] : 30;
        $fechaLimite = now()->subDays($dias)->format('Y-m-d');

        $query = DB::table('cliente as c')
            ->join('venta as v', 'v.cliente_id', '=', 'c.id')
            ->where('c.numero_documento', '!=', '99999999')
            ->where('v.estado_de_venta', '!=', 'an')
            ->groupBy('c.id', 'c.numero_documento', 'c.tipo_cliente', 'c.razon_social', 'c.nombres', 'c.apellidos', 'c.direccion', 'c.telefono', 'c.email', 'c.estado')
            ->havingRaw('MIN(v.fecha) >= ?', [$fechaLimite]);

        $query->selectRaw("
            c.id,
            c.numero_documento,
            c.tipo_cliente,
            CASE WHEN c.tipo_cliente = 'e' THEN c.razon_social ELSE CONCAT(c.nombres, ' ', c.apellidos) END as nombre,
            c.direccion,
            c.telefono,
            c.email,
            c.estado,
            MIN(v.fecha) as primera_venta,
            COUNT(DISTINCT v.id) as total_ventas
        ");

        $query->orderByDesc(DB::raw('MIN(v.fecha)'));

        $countQuery = clone $query;
        $total = DB::table(DB::raw("({$countQuery->toSql()}) as sub"))
            ->mergeBindings($countQuery)
            ->count();

        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'numero_documento' => $item->numero_documento,
                    'tipo_cliente' => $item->tipo_cliente,
                    'nombre' => $item->nombre,
                    'direccion' => $item->direccion,
                    'telefono' => $item->telefono,
                    'email' => $item->email,
                    'estado' => (bool)$item->estado,
                    'primera_venta' => $item->primera_venta,
                    'total_ventas' => (int)$item->total_ventas,
                ];
            })->toArray(),
            'current_page' => $page,
            'last_page' => (int)ceil($total / max($perPage, 1)),
            'per_page' => $perPage,
            'total' => $total,
        ];
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
