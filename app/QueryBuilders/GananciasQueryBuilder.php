<?php

namespace App\QueryBuilders;

use Illuminate\Support\Facades\DB;

class GananciasQueryBuilder
{
    /**
     * Expresión SQL para obtener el costo correcto (pav.costo o pa.costo)
     */
    private const COSTO_EXPR = 'CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END';

    /**
     * Query base para ventas con joins comunes
     */
    public static function baseVentasQuery()
    {
        return DB::table('unidadderivadainmutableventa as udiv')
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
            // Excluir ventas administrativas ("no descontar stock"): no movieron inventario,
            // no cuentan como ganancia. NULL (ventas previas a esta columna) sí cuentan.
            ->whereRaw('(v.descuenta_stock IS NULL OR v.descuenta_stock = 1)');
    }

    /**
     * Query para reporte detallado de ganancias
     */
    public static function reporteDetalladoQuery()
    {
        return self::baseVentasQuery()
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
                DB::raw(self::COSTO_EXPR . " as costo"),
                DB::raw(self::COSTO_EXPR . " * udiv.cantidad as costo_total"),
                DB::raw("(udiv.precio - " . self::COSTO_EXPR . ") * udiv.cantidad as ganancia"),
                DB::raw("COALESCE(dp.id, 'SIN_METODO') as cc"),
                'v.created_at',
                'v.updated_at'
            ]);
    }

    /**
     * Query para resumen de ganancias (agregado)
     */
    public static function resumenGananciasQuery()
    {
        return self::baseVentasQuery()
            ->selectRaw('
                SUM(udiv.precio * udiv.cantidad) as total_ventas,
                SUM(' . self::COSTO_EXPR . ' * udiv.cantidad) as total_costo,
                SUM((udiv.precio - ' . self::COSTO_EXPR . ') * udiv.cantidad) as total_ganancia,
                COUNT(DISTINCT v.id) as total_transacciones,
                SUM(CASE WHEN udiv.precio < ' . self::COSTO_EXPR . ' THEN (' . self::COSTO_EXPR . ' - udiv.precio) * udiv.cantidad ELSE 0 END) as total_perdida
            ');
    }

    /**
     * Query para pérdidas por ventas bajo costo
     */
    public static function perdidasVentasQuery()
    {
        return DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            ->select([
                'v.fecha',
                'p.name as producto',
                DB::raw("'VENTA BAJO COSTO' as motivo"),
                DB::raw("((" . self::COSTO_EXPR . ") - udiv.precio) * udiv.cantidad as monto"),
                DB::raw("CASE 
                    WHEN ce.serie IS NOT NULL AND ce.correlativo IS NOT NULL 
                    THEN CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            ELSE '[DOC] ' 
                        END,
                        ce.serie, '-', LPAD(ce.correlativo, 8, '0')
                    )
                    ELSE CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            WHEN 'nv' THEN '[N.VENTA] ' 
                            WHEN '00' THEN '[N.VENTA] '
                            ELSE '[DOC] ' 
                        END,
                        LPAD(v.numero, 8, '0')
                    )
                END as comprobante"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
                'udiv.cantidad'
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            // Excluir ventas administrativas ("no descontar stock") también de las pérdidas.
            ->whereRaw('(v.descuenta_stock IS NULL OR v.descuenta_stock = 1)')
            ->whereRaw('udiv.precio < (' . self::COSTO_EXPR . ')');
    }

    /**
     * Query para pérdidas por salidas de almacén
     */
    public static function perdidasSalidasQuery()
    {
        return DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as isa', 'pais.ingreso_id', '=', 'isa.id')
            ->leftJoin('productoalmacen as pa', 'pais.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('tipoingresosalida as tis', 'isa.tipo_ingreso_id', '=', 'tis.id')
            ->select([
                'isa.fecha',
                'p.name as producto',
                DB::raw("UPPER(tis.name) as motivo"),
                DB::raw("pais.costo * udis.cantidad as monto"),
                DB::raw("CASE 
                    WHEN isa.serie IS NOT NULL THEN CONCAT(isa.serie, '-', LPAD(isa.numero, 8, '0'))
                    ELSE CONCAT('NS-', LPAD(isa.numero, 8, '0'))
                END as comprobante"),
                DB::raw("CONCAT('SALIDA #', isa.numero) as referencia"),
                'udis.cantidad'
            ])
            ->where('isa.tipo_documento', 'sa')
            ->where('isa.estado', true);
    }

    /**
     * Query para gastos de compras (gastos_u)
     */
    public static function gastosComprasQuery()
    {
        return DB::table('unidadderivadainmutablecompra as udic')
            ->join('productoalmacencompra as pac', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->join('compra as comp', 'pac.compra_id', '=', 'comp.id')
            ->where('comp.estado_de_compra', '!=', 'an')
            ->selectRaw('SUM(udic.cantidad * pac.costo) as subtotal, ANY_VALUE(comp.percepcion) as percepcion')
            ->groupBy('comp.id');
    }

    /**
     * Query para pérdidas por salidas (usado en resumen)
     */
    public static function perdidaSalidasSumQuery()
    {
        return DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as isa', 'pais.ingreso_id', '=', 'isa.id')
            ->where('isa.tipo_documento', 'sa')
            ->where('isa.estado', true);
    }
}
