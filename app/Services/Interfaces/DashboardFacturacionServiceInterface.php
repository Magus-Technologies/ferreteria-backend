<?php

namespace App\Services\Interfaces;

interface DashboardFacturacionServiceInterface
{
    /**
     * KPIs de las tarjetas superiores: total vendido, n° de ventas y total por
     * tipo de documento (facturas, boletas, notas de venta).
     */
    public function obtenerResumen(array $filtros): array;

    /** Ventas (importe) agrupadas por categoría de producto. */
    public function ventasPorCategoria(array $filtros, int $limit = 10): array;

    /** Ventas (importe) agrupadas por marca. */
    public function ventasPorMarca(array $filtros, int $limit = 12): array;

    /** Ventas (monto cobrado) agrupadas por método de pago. */
    public function ventasPorMetodoPago(array $filtros): array;

    /** Top de productos más vendidos por importe. */
    public function productosMasVendidos(array $filtros, int $limit = 10): array;

    /** Ventas (importe y n° de ventas) agrupadas por tipo de documento. */
    public function ventasPorTipoDocumento(array $filtros): array;

    /** Ingresos y n° de pedidos agrupados por tipo de canal (despacho). */
    public function ingresosPorCanal(array $filtros): array;
}
