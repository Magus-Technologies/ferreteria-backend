<?php

namespace App\Services\Interfaces;

interface GananciasServiceInterface
{
    /**
     * Obtener reporte detallado de ganancias
     */
    public function obtenerReporteGanancias(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Obtener resumen de ganancias para las cards
     */
    public function obtenerResumenGanancias(array $filtros): array;

    /**
     * Exportar reporte de ganancias
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array;

    /**
     * Enviar reporte por correo electrónico
     */
    public function enviarReportePorCorreo(string $email, array $filtros): void;

    /**
     * Obtener pagos de compras
     */
    public function obtenerPagosCompras(array $filtros): array;

    /**
     * Obtener detalle de pérdidas
     */
    public function obtenerPerdidasDetalle(array $filtros): array;
}