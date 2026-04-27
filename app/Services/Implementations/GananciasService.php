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
            ->where('v.estado_de_venta', '!=', 'Anulado')
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
                        return $q->whereRaw('(udiv.precio - pav.costo) * udiv.cantidad > 0');
                    case 'con_perdida':
                        return $q->whereRaw('(udiv.precio - pav.costo) * udiv.cantidad < 0');
                    case 'sin_costo':
                        return $q->where('pav.costo', 0);
                }
                return $q;
            })
            ->selectRaw('
                SUM(udiv.precio * udiv.cantidad) as total_ventas,
                SUM(pav.costo * udiv.cantidad) as total_costo,
                SUM((udiv.precio - pav.costo) * udiv.cantidad) as total_ganancia,
                COUNT(DISTINCT v.id) as total_transacciones,
                SUM(CASE WHEN udiv.precio < pav.costo THEN (pav.costo - udiv.precio) * udiv.cantidad ELSE 0 END) as total_perdida
            ')->first();

        $gastosU = DB::table('unidadderivadainmutablecompra as udic')
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
            ->sum(DB::raw('udic.cantidad * pac.costo'));

        $totalVentas = $resumen->total_ventas ?? 0;
        $totalCosto = $resumen->total_costo ?? 0;
        $totalGanancia = $resumen->total_ganancia ?? 0;
        $totalPerdida = $resumen->total_perdida ?? 0;
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
            ->select([
                DB::raw("DATE_FORMAT(v.fecha, '%d/%m/%Y') as fecha"),
                DB::raw("TIME_FORMAT(v.fecha, '%H:%i:%s') as hora_emision"),
                DB::raw("DATE_FORMAT(v.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento"),
                DB::raw("v.tipo_documento as tipo_doc"),
                DB::raw("CONCAT(COALESCE(ce.serie, 'S/N'), '-', LPAD(COALESCE(ce.correlativo, v.numero), 8, '0')) as numero"),
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
                DB::raw("pav.costo as costo_unit"),
                DB::raw("pav.costo * udiv.cantidad as costo_total"),
                DB::raw("(udiv.precio - pav.costo) * udiv.cantidad as ganancia"),
                DB::raw("'E' as cc"), // Por defecto 'E' (Efectivo)
                'v.created_at',
                'v.updated_at'
            ])
            ->where('v.estado_de_venta', '!=', 'Anulado');

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
}