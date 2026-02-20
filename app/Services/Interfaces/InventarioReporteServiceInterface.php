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
}
