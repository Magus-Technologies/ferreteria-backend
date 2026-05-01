<?php

namespace App\Services\Implementations;

use App\Models\Venta;
use App\Models\ProductoAlmacenVenta;
use App\Services\Interfaces\GananciasServiceInterface;
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
        // Crear una consulta específica para el resumen que use la tabla correcta unidadderivadainmutableventa
        $resumen = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('almacen as a', 'v.almacen_id', '=', 'a.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            ->where('v.estado_de_venta', '!=', 'an')
            ->when(!empty($filtros['almacen_id']), function($q) use ($filtros) {
                return $q->where('v.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function($q) use ($filtros) {
                return $q->whereDate('v.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function($q) use ($filtros) {
                return $q->whereDate('v.fecha', '<=', $filtros['hasta']);
            })
            ->when(!empty($filtros['cliente_id']), function($q) use ($filtros) {
                return $q->where('v.cliente_id', $filtros['cliente_id']);
            })
            ->when(!empty($filtros['user_id']), function($q) use ($filtros) {
                return $q->where('v.user_id', $filtros['user_id']);
            })
            ->when(!empty($filtros['search']), function($q) use ($filtros) {
                $search = '%' . $filtros['search'] . '%';
                return $q->where(function($subQ) use ($search) {
                    $subQ->where('c.numero_documento', 'like', $search)
                         ->orWhere('c.razon_social', 'like', $search)
                         ->orWhere(DB::raw("CONCAT(c.nombres, ' ', c.apellidos)"), 'like', $search);
                });
            })
            ->when(!empty($filtros['producto_servicio']), function($q) use ($filtros) {
                return $q->where('p.name', 'like', '%' . $filtros['producto_servicio'] . '%');
            })
            ->when(!empty($filtros['marca']), function($q) use ($filtros) {
                return $q->where('m.name', 'like', '%' . $filtros['marca'] . '%');
            })
            ->when(!empty($filtros['forma_pago']), function($q) use ($filtros) {
                return $q->where('v.forma_de_pago', $filtros['forma_pago']);
            })
            ->when(!empty($filtros['tipo_doc']), function($q) use ($filtros) {
                return $q->where('v.tipo_documento', $filtros['tipo_doc']);
            })
            ->when(!empty($filtros['serie']) && !empty($filtros['numero']), function($q) use ($filtros) {
                return $q->where('ce.serie', $filtros['serie'])
                         ->where('ce.correlativo', $filtros['numero']);
            })
            ->when(!empty($filtros['incluir']), function($q) use ($filtros) {
                switch ($filtros['incluir']) {
                    case 'con_ganancia':
                        return $q->whereRaw('(udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)) * udiv.cantidad > 0');
                    case 'con_perdida':
                        return $q->whereRaw('(udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)) * udiv.cantidad < 0');
                    case 'sin_costo':
                        return $q->whereRaw('(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) = 0');
                }
                return $q;
            })
            ->selectRaw('
                SUM(udiv.precio * udiv.cantidad) as total_ventas,
                SUM(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END * udiv.cantidad) as total_costo,
                SUM((udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)) * udiv.cantidad) as total_ganancia,
                COUNT(DISTINCT v.id) as total_transacciones,
                SUM(CASE WHEN udiv.precio < (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) THEN ((CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) - udiv.precio) * udiv.cantidad ELSE 0 END) as total_perdida
            ')->first();

        $gastosUQuery = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->join('compra as comp', 'pac.compra_id', '=', 'comp.id')
            ->where('comp.estado_de_compra', '!=', 'an')
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('comp.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('comp.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('comp.fecha', '<=', $filtros['hasta']);
            })
            ->selectRaw('SUM(udic.cantidad * pac.costo) as subtotal, ANY_VALUE(comp.percepcion) as percepcion')
            ->groupBy('comp.id')
            ->get();

        $gastosU = $gastosUQuery->sum('subtotal') + $gastosUQuery->sum('percepcion');

        $totalVentas = $resumen->total_ventas ?? 0;
        $totalCosto = $resumen->total_costo ?? 0;
        $totalGanancia = $resumen->total_ganancia ?? 0;
        $totalPerdidaVentas = $resumen->total_perdida ?? 0;

        // Calcular pérdidas por salidas de almacén
        $totalPerdidaSalidas = DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as is', 'pais.ingreso_id', '=', 'is.id')
            ->where('is.tipo_documento', 'sa') // Salida
            ->where('is.estado', true)
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('is.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('is.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('is.fecha', '<=', $filtros['hasta']);
            })
            ->sum(DB::raw('udis.cantidad * pais.costo'));

        $totalPerdida = $totalPerdidaVentas + $totalPerdidaSalidas;
        $neto = $totalGanancia - $gastosU - $totalPerdida;

        return [
            'ventas' => round($totalVentas, 2),
            'costo' => round($totalCosto, 2),
            'ganancia' => round($totalGanancia, 2),
            'gastos_u' => round($gastosU, 2),
            'neto' => round($neto, 2),
            'perdida' => round($totalPerdida, 2),
            'total_transacciones' => $resumen->total_transacciones ?? 0
        ];
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
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->join('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->join('producto as p', 'pa.producto_id', '=', 'p.id')
            ->join('marca as m', 'p.marca_id', '=', 'm.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('almacen as a', 'v.almacen_id', '=', 'a.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            // Join para obtener el despliegue de pago
            ->leftJoin('desplieguedepagoventa as dpv', 'v.id', '=', 'dpv.venta_id')
            ->leftJoin('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->select([
                DB::raw("DATE_FORMAT(v.fecha, '%d/%m/%Y') as fecha"),
                DB::raw("TIME_FORMAT(v.fecha, '%H:%i:%s') as hora_emision"),
                DB::raw("DATE_FORMAT(v.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento"),
                DB::raw("v.tipo_documento as tipo_doc"),
                DB::raw("CASE 
                    WHEN ce.serie IS NOT NULL AND ce.correlativo IS NOT NULL 
                    THEN CONCAT(ce.serie, '-', LPAD(ce.correlativo, 8, '0'))
                    ELSE CONCAT('NV', LPAD(v.numero, 3, '0'), '-', LPAD(v.numero, 8, '0'))
                END as numero"),
                DB::raw("v.forma_de_pago as f_pago"),
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, '-', COALESCE(c.razon_social, CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellidos, ''))))
                    ELSE '99999999-CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("COALESCE(p.name, 'PRODUCTO SIN NOMBRE') as producto"),
                DB::raw("COALESCE(m.name, 'SIN MARCA') as marca"),
                DB::raw("udiv.cantidad as cant"),
                DB::raw("udiv.precio as p_unit"),
                DB::raw("udiv.precio * udiv.cantidad as subtot"),
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_unit"),
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END * udiv.cantidad as costo_total"),
                DB::raw("(udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)) * udiv.cantidad as ganancia"),
                DB::raw("COALESCE(dp.id, 'SIN_METODO') as cc"), // ID del despliegue de pago
                'v.created_at',
                'v.updated_at'
            ])
            ->where('v.estado_de_venta', '!=', 'an');

        // Aplicar filtros
        if (!empty($filtros['almacen_id'])) {
            $query->where('v.almacen_id', $filtros['almacen_id']);
        } else {
            // Si no hay almacén específico, no mostrar datos por seguridad
            $query->whereRaw('1 = 0'); // Forzar resultado vacío
        }

        if (!empty($filtros['desde'])) {
            $query->whereDate('v.fecha', '>=', $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $query->whereDate('v.fecha', '<=', $filtros['hasta']);
        }

        if (!empty($filtros['cliente_id'])) {
            $query->where('v.cliente_id', $filtros['cliente_id']);
        }

        if (!empty($filtros['search'])) {
            $search = '%' . $filtros['search'] . '%';
            $query->where(function($q) use ($search) {
                $q->where('c.numero_documento', 'like', $search)
                  ->orWhere('c.razon_social', 'like', $search)
                  ->orWhere(DB::raw("CONCAT(c.nombres, ' ', c.apellidos)"), 'like', $search);
            });
        }

        if (!empty($filtros['user_id'])) {
            $query->where('v.user_id', $filtros['user_id']);
        }

        if (!empty($filtros['producto_servicio'])) {
            $query->where('p.name', 'like', '%' . $filtros['producto_servicio'] . '%');
        }

        if (!empty($filtros['marca'])) {
            $query->where('m.name', 'like', '%' . $filtros['marca'] . '%');
        }

        if (!empty($filtros['forma_pago'])) {
            $query->where('v.forma_de_pago', $filtros['forma_pago']);
        }

        if (!empty($filtros['tipo_doc'])) {
            $query->where('v.tipo_documento', $filtros['tipo_doc']);
        }

        if (!empty($filtros['serie']) && !empty($filtros['numero'])) {
            $query->where('ce.serie', $filtros['serie'])
                  ->where('ce.correlativo', $filtros['numero']);
        }

        // Filtro por despliegue de pago (C.Caja)
        if (!empty($filtros['confirmar_caja'])) {
            $query->where('dp.id', $filtros['confirmar_caja']);
        }

        // Filtro "incluir" para ganancias/pérdidas
        if (!empty($filtros['incluir'])) {
            switch ($filtros['incluir']) {
                case 'con_ganancia':
                    $query->whereRaw('(udiv.precio - pav.costo) * udiv.cantidad > 0');
                    break;
                case 'con_perdida':
                    $query->whereRaw('(udiv.precio - pav.costo) * udiv.cantidad < 0');
                    break;
                case 'sin_costo':
                    $query->where('pav.costo', 0);
                    break;
                // 'todos' no requiere filtro adicional
            }
        }

        // Ordenar por fecha descendente
        $query->orderBy('v.fecha', 'desc')
              ->orderBy('v.created_at', 'desc');

        return $query;
    }

    /**
     * Calcular resumen de los datos actuales
     */
    private function calcularResumenDatos($datos): array
    {
        $totalVentas = $datos->sum('subtotal');
        $totalCosto = $datos->sum('costo_total');
        $totalGanancia = $datos->sum('ganancia');
        $gastosU = 0;
        $totalPerdida = $datos->where('ganancia', '<', 0)->sum(function($item) {
            return abs($item->ganancia);
        });
        $neto = $totalGanancia - $gastosU - $totalPerdida;

        return [
            'ventas' => round($totalVentas, 2),
            'costo' => round($totalCosto, 2),
            'ganancia' => round($totalGanancia, 2),
            'gastos_u' => round($gastosU, 2),
            'neto' => round($neto, 2),
            'perdida' => round($totalPerdida, 2),
            'total_registros' => $datos->count()
        ];
    }

    /**
     * Obtener pagos de compras detallados
     */
    public function obtenerPagosCompras(array $filtros): array
    {
        $pagos = DB::table('pagodecompra as p')
            ->join('compra as c', 'p.compra_id', '=', 'c.id')
            ->join('proveedor as prov', 'c.proveedor_id', '=', 'prov.id')
            ->leftJoin('desplieguedepago as dp', 'p.despliegue_de_pago_id', '=', 'dp.id')
            ->select([
                'p.id',
                'p.fecha',
                'p.monto',
                'p.numero_operacion',
                'p.observacion',
                'prov.razon_social as proveedor',
                'c.serie',
                'c.numero',
                'dp.id as despliegue_id',
                'dp.name as metodo_pago'
            ])
            ->where('p.estado', true)
            ->where('c.estado_de_compra', '!=', 'an')
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('c.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('p.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('p.fecha', '<=', $filtros['hasta']);
            })
            ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                $search = $filtros['search'];
                return $q->where(function ($query) use ($search) {
                    $query->where('prov.razon_social', 'like', "%{$search}%")
                        ->orWhere('c.serie', 'like', "%{$search}%")
                        ->orWhere('c.numero', 'like', "%{$search}%")
                        ->orWhere('p.numero_operacion', 'like', "%{$search}%");
                });
            })
            ->orderBy('p.fecha', 'desc')
            ->get();

        // Calcular resumen para el modal
        // Obtenemos las compras que se realizaron en este periodo
        $comprasPeriodo = DB::table('compra as comp')
            ->where('comp.estado_de_compra', '!=', 'an')
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('comp.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('comp.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('comp.fecha', '<=', $filtros['hasta']);
            })
            ->select('comp.id', 'comp.percepcion')
            ->get();

        $compraIds = $comprasPeriodo->pluck('id');

        // Total bruto de las compras (items + percepcion)
        $totalComprasBruto = DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->whereIn('pac.compra_id', $compraIds)
            ->sum(DB::raw('udic.cantidad * pac.costo'));
        
        $totalPercepciones = $comprasPeriodo->sum('percepcion');
        $totalCompras = $totalComprasBruto + $totalPercepciones;

        // Total Pagado EN EL PERIODO (lo que se muestra en la tabla)
        $totalPagadoPeriodo = $pagos->sum('monto');

        // Para el saldo pendiente, necesitamos saber cuánto se debe de las compras realizadas en este periodo
        // Independientemente de cuándo se hicieron los pagos
        $totalPagadoDeEstasCompras = DB::table('pagodecompra')
            ->whereIn('compra_id', $compraIds)
            ->where('estado', true)
            ->sum('monto');

        $pendiente = $totalCompras - $totalPagadoDeEstasCompras;

        return [
            'pagos' => $pagos->toArray(),
            'resumen' => [
                'total_compras' => round($totalCompras, 2),
                'total_pagado' => round($totalPagadoPeriodo, 2),
                'pendiente' => round(max(0, $pendiente), 2),
            ]
        ];
    }

    /**
     * Obtener detalle de pérdidas (Ventas bajo costo y Salidas de Almacén)
     */
    public function obtenerPerdidasDetalle(array $filtros): array
    {
        // 1. Pérdidas por Ventas bajo costo
        $perdidasVentas = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->select([
                'v.fecha',
                'p.name as producto',
                DB::raw("'VENTA BAJO COSTO' as motivo"),
                DB::raw("((CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) - udiv.precio) * udiv.cantidad as monto"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
                'udiv.cantidad'
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->whereRaw('udiv.precio < (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)')
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('v.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('v.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('v.fecha', '<=', $filtros['hasta']);
            })
            ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                $search = $filtros['search'];
                return $q->where(function ($query) use ($search) {
                    $query->where('p.name', 'like', "%{$search}%")
                        ->orWhere('v.numero', 'like', "%{$search}%");
                });
            })
            ->get();

        // 2. Pérdidas por Salidas de Almacén (Tipo Salida)
        $perdidasSalidas = DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as is', 'pais.ingreso_id', '=', 'is.id')
            ->leftJoin('productoalmacen as pa', 'pais.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('tipoingresosalida as tis', 'is.tipo_ingreso_id', '=', 'tis.id')
            ->select([
                'is.fecha',
                'p.name as producto',
                DB::raw("UPPER(tis.name) as motivo"),
                DB::raw("pais.costo * udis.cantidad as monto"),
                DB::raw("CONCAT('SALIDA #', is.numero) as referencia"),
                'udis.cantidad'
            ])
            ->where('is.tipo_documento', 'sa') // Salida
            ->where('is.estado', true)
            ->when(!empty($filtros['almacen_id']), function ($q) use ($filtros) {
                return $q->where('is.almacen_id', $filtros['almacen_id']);
            })
            ->when(!empty($filtros['desde']), function ($q) use ($filtros) {
                return $q->whereDate('is.fecha', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($q) use ($filtros) {
                return $q->whereDate('is.fecha', '<=', $filtros['hasta']);
            })
            ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                $search = $filtros['search'];
                return $q->where(function ($query) use ($search) {
                    $query->where('p.name', 'like', "%{$search}%")
                        ->orWhere('is.numero', 'like', "%{$search}%")
                        ->orWhere('tis.name', 'like', "%{$search}%");
                });
            })
            ->get();

        $merged = $perdidasVentas->concat($perdidasSalidas)->sortByDesc('fecha');

        return [
            'detalles' => $merged->values()->toArray(),
            'resumen' => [
                'total_ventas_bajo_costo' => round($perdidasVentas->sum('monto'), 2),
                'total_salidas_almacen' => round($perdidasSalidas->sum('monto'), 2),
                'total_perdida' => round($perdidasVentas->sum('monto') + $perdidasSalidas->sum('monto'), 2),
            ]
        ];
    }
}