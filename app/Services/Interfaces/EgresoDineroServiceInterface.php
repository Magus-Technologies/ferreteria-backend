<?php

namespace App\Services\Interfaces;

interface EgresoDineroServiceInterface
{
    /**
     * Obtener reporte detallado de gastos
     */
    public function obtenerReporteGastos(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Obtener resumen de gastos para las cards
     */
    public function obtenerResumenGastos(array $filtros): array;

    /**
     * Crear nuevo gasto de dinero
     */
    public function crearGasto(array $data): array;

    /**
     * Actualizar gasto existente
     */
    public function actualizarGasto(string $id, array $data): array;

    /**
     * Anular/cancelar gasto
     */
    public function anularGasto(string $id, string $motivo): bool;

    /**
     * Exportar reporte de gastos
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array;

    /**
     * Enviar reporte por correo electrónico
     */
    public function enviarReportePorCorreo(string $email, array $filtros): void;
}