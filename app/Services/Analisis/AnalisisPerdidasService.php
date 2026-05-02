<?php

namespace App\Services\Analisis;

use Illuminate\Support\Facades\DB;
use App\QueryFilters\GananciasQueryFilter;

class AnalisisPerdidasService
{
    /**
     * Obtener análisis completo de pérdidas
     */
    public function obtenerAnalisisPerdidas(array $filtros): array
    {
        $filter = new GananciasQueryFilter($filtros);

        // 1. Pérdidas por Ventas Bajo Costo
        $perdidasBajoCosto = $this->calcularPerdidasBajoCosto($filter);

        // 2. Pérdidas por Descuentos
        $perdidasDescuentos = $this->calcularPerdidasDescuentos($filter);

        // 3. Pérdidas por Comisiones
        $perdidasComisiones = $this->calcularPerdidasComisiones($filter);

        // 4. Pérdidas por Salidas de Almacén
        $perdidasSalidas = $this->calcularPerdidasSalidas($filter);

        // 5. Pérdidas por Notas de Crédito
        $perdidasNotasCredito = $this->calcularPerdidasNotasCredito($filter);

        // Combinar todos los detalles
        $todosDetalles = collect()
            ->concat($perdidasBajoCosto['detalles'])
            ->concat($perdidasDescuentos['detalles'])
            ->concat($perdidasComisiones['detalles'])
            ->concat($perdidasSalidas['detalles'])
            ->concat($perdidasNotasCredito['detalles'])
            ->sortByDesc('fecha')
            ->values();

        return [
            'detalles' => $todosDetalles->toArray(),
            'resumen' => [
                'ventas_bajo_costo' => round($perdidasBajoCosto['total'], 2),
                'descuentos_aplicados' => round($perdidasDescuentos['total'], 2),
                'comisiones_vendedor' => round($perdidasComisiones['total'], 2),
                'salidas_almacen' => round($perdidasSalidas['total'], 2),
                'notas_credito' => round($perdidasNotasCredito['total'], 2),
                'total_perdidas' => round(
                    $perdidasBajoCosto['total'] +
                    $perdidasDescuentos['total'] +
                    $perdidasComisiones['total'] +
                    $perdidasSalidas['total'] +
                    $perdidasNotasCredito['total'],
                    2
                ),
            ],
            'por_categoria' => [
                [
                    'categoria' => 'Ventas Bajo Costo',
                    'monto' => round($perdidasBajoCosto['total'], 2),
                    'cantidad' => count($perdidasBajoCosto['detalles']),
                ],
                [
                    'categoria' => 'Descuentos Aplicados',
                    'monto' => round($perdidasDescuentos['total'], 2),
                    'cantidad' => count($perdidasDescuentos['detalles']),
                ],
                [
                    'categoria' => 'Comisiones de Vendedor',
                    'monto' => round($perdidasComisiones['total'], 2),
                    'cantidad' => count($perdidasComisiones['detalles']),
                ],
                [
                    'categoria' => 'Salidas de Almacén',
                    'monto' => round($perdidasSalidas['total'], 2),
                    'cantidad' => count($perdidasSalidas['detalles']),
                ],
                [
                    'categoria' => 'Notas de Crédito',
                    'monto' => round($perdidasNotasCredito['total'], 2),
                    'cantidad' => count($perdidasNotasCredito['detalles']),
                ],
            ],
        ];
    }

    /**
     * Calcular pérdidas por ventas bajo costo
     */
    private function calcularPerdidasBajoCosto(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->select([
                'v.id as venta_id',
                'v.fecha',
                'v.tipo_documento',
                'v.numero',
                'p.name as producto',
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("'VENTA BAJO COSTO' as categoria"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_producto"),
                DB::raw("((CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) - udiv.precio) * udiv.cantidad as monto"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->whereRaw('udiv.precio < (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END)');

        $filter->applyPerdidas($query, 'venta');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por descuentos aplicados
     */
    private function calcularPerdidasDescuentos(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->select([
                'v.id as venta_id',
                'v.fecha',
                'v.tipo_documento',
                'v.numero',
                'p.name as producto',
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("CASE 
                    WHEN udiv.descuento_tipo = 'porcentaje' THEN CONCAT('DESCUENTO ', udiv.descuento, '%')
                    ELSE 'DESCUENTO FIJO'
                END as categoria"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_producto"),
                'udiv.descuento',
                'udiv.descuento_tipo',
                DB::raw("CASE 
                    WHEN udiv.descuento_tipo = 'porcentaje' THEN (udiv.precio * udiv.cantidad * udiv.descuento / 100)
                    ELSE udiv.descuento
                END as monto"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->where(function($q) {
                $q->where('udiv.descuento', '>', 0)
                  ->orWhereNotNull('udiv.descuento');
            });

        $filter->applyPerdidas($query, 'venta');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por comisiones de vendedor
     */
    private function calcularPerdidasComisiones(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->select([
                'v.id as venta_id',
                'v.fecha',
                'v.tipo_documento',
                'v.numero',
                'p.name as producto',
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("'COMISIÓN VENDEDOR' as categoria"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_producto"),
                'udiv.comision',
                DB::raw("udiv.comision as monto"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->where(function($q) {
                $q->where('udiv.comision', '>', 0)
                  ->orWhereNotNull('udiv.comision');
            });

        $filter->applyPerdidas($query, 'venta');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por salidas de almacén
     */
    private function calcularPerdidasSalidas(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as is', 'pais.ingreso_id', '=', 'is.id')
            ->leftJoin('productoalmacen as pa', 'pais.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('tipoingresosalida as tis', 'is.tipo_ingreso_id', '=', 'tis.id')
            ->leftJoin('user as u', 'is.user_id', '=', 'u.id')
            ->select([
                'is.id as salida_id',
                'is.fecha',
                DB::raw("'SALIDA' as tipo_documento"),
                'is.numero',
                'p.name as producto',
                DB::raw("UPPER(tis.name) as categoria"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as cliente"),
                'udis.cantidad',
                'pais.costo as costo_producto',
                DB::raw("pais.costo * udis.cantidad as monto"),
                DB::raw("CONCAT('SALIDA #', is.numero) as referencia"),
            ])
            ->where('is.tipo_documento', 'sa')
            ->where('is.estado', true);

        $filter->applyPerdidas($query, 'salida');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por notas de crédito
     */
    private function calcularPerdidasNotasCredito(GananciasQueryFilter $filter): array
    {
        $query = DB::table('notacredito as nc')
            ->join('venta as v', 'nc.venta_id', '=', 'v.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'nc.user_id', '=', 'u.id')
            ->leftJoin('motivonota as mn', 'nc.motivo_nota_id', '=', 'mn.id')
            ->select([
                'nc.id as nota_credito_id',
                'nc.fecha',
                DB::raw("'NC' as tipo_documento"),
                DB::raw("CONCAT(nc.serie, '-', LPAD(nc.correlativo, 8, '0')) as numero"),
                DB::raw("COALESCE(mn.descripcion, 'NOTA DE CRÉDITO') as categoria"),
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("'N/A' as producto"),
                DB::raw("1 as cantidad"),
                DB::raw("nc.total as monto"),
                DB::raw("CONCAT('VENTA ', v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('nc.estado', true);

        $filter->applyPerdidas($query, 'nota_credito');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }
}
