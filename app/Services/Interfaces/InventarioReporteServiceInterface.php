<?php

namespace App\Services\Interfaces;

interface InventarioReporteServiceInterface
{
    /**
     * Top productos por ventas, utilidad o recurrencia
     */
    public function obtenerTopProductos(array $filtros, string $tipo = 'ventas', int $limit = 20): array;

    /**
     * Resumen KPI de inventario
     */
    public function obtenerResumenInventario(array $filtros): array;

    /**
     * Stock valorizado (todos los productos con stock * costo)
     */
    public function obtenerStockValorizado(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Productos con stock bajo (menor a stock_min)
     */
    public function obtenerProductosStockBajo(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Cantidades vendidas por producto
     */
    public function obtenerCantidadesVendidas(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Demanda (unidades vendidas) agrupada por categoría de producto
     */
    public function obtenerDemandaPorCategoria(array $filtros, int $limit = 10): array;

    /**
     * Costo de ajuste de inventario (ingresos/salidas manuales) en el periodo
     */
    public function obtenerCostoAjuste(array $filtros): float;

    /**
     * Productos rotados (con venta en el periodo) y total de productos
     */
    public function obtenerProductosRotados(array $filtros): array;

    /**
     * Valorización del inventario al inicio y fin del año
     */
    public function obtenerInventarioPorAnio(array $filtros): array;

    /**
     * Productos con stock pero sin ventas en el periodo (sin rotar)
     */
    public function obtenerProductosSinRotar(array $filtros, int $perPage = 100, int $page = 1): array;
}
