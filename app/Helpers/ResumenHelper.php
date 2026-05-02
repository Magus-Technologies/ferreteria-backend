<?php

namespace App\Helpers;

class ResumenHelper
{
    /**
     * Redondear un array de valores a 2 decimales
     */
    public static function roundArray(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $data[$key] = round($data[$key], 2);
            }
        }
        return $data;
    }

    /**
     * Crear resumen de ganancias con valores redondeados
     */
    public static function buildResumenGanancias(
        float $ventas,
        float $costo,
        float $ganancia,
        float $gastosU,
        float $perdida,
        int $totalTransacciones = 0
    ): array {
        $neto = $ganancia - $gastosU - $perdida;

        return self::roundArray([
            'ventas' => $ventas,
            'costo' => $costo,
            'ganancia' => $ganancia,
            'gastos_u' => $gastosU,
            'neto' => $neto,
            'perdida' => $perdida,
            'total_transacciones' => $totalTransacciones,
        ], ['ventas', 'costo', 'ganancia', 'gastos_u', 'neto', 'perdida']);
    }

    /**
     * Crear resumen de datos de página
     */
    public static function buildResumenDatos(
        float $ventas,
        float $costo,
        float $ganancia,
        float $gastosU,
        float $perdida,
        int $totalRegistros
    ): array {
        $neto = $ganancia - $gastosU - $perdida;

        return self::roundArray([
            'ventas' => $ventas,
            'costo' => $costo,
            'ganancia' => $ganancia,
            'gastos_u' => $gastosU,
            'neto' => $neto,
            'perdida' => $perdida,
            'total_registros' => $totalRegistros,
        ], ['ventas', 'costo', 'ganancia', 'gastos_u', 'neto', 'perdida']);
    }

    /**
     * Crear resumen de pagos y compras
     */
    public static function buildResumenPagosCompras(
        float $totalCompras,
        float $totalPagado,
        float $totalGastos,
        float $pendiente
    ): array {
        return self::roundArray([
            'total_compras' => $totalCompras,
            'total_pagado' => $totalPagado,
            'total_gastos' => $totalGastos,
            'pendiente' => max(0, $pendiente),
        ], ['total_compras', 'total_pagado', 'total_gastos', 'pendiente']);
    }

    /**
     * Crear resumen de pérdidas
     */
    public static function buildResumenPerdidas(
        float $totalVentasBajoCosto,
        float $totalSalidasAlmacen
    ): array {
        return self::roundArray([
            'total_ventas_bajo_costo' => $totalVentasBajoCosto,
            'total_salidas_almacen' => $totalSalidasAlmacen,
            'total_perdida' => $totalVentasBajoCosto + $totalSalidasAlmacen,
        ], ['total_ventas_bajo_costo', 'total_salidas_almacen', 'total_perdida']);
    }
}
