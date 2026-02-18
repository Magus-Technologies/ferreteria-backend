<?php

namespace App\Services\Interfaces;

interface IngresoDineroServiceInterface
{
    /**
     * Obtener reporte detallado de ingresos
     */
    public function obtenerReporteIngresos(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Obtener resumen de ingresos para las cards
     */
    public function obtenerResumenIngresos(array $filtros): array;

    /**
     * Crear nuevo ingreso de dinero
     */
    public function crearIngreso(array $data): array;

    /**
     * Actualizar ingreso existente
     */
    public function actualizarIngreso(string $id, array $data): array;

    /**
     * Anular/cancelar ingreso
     */
    public function anularIngreso(string $id, string $motivo): bool;

    /**
     * Exportar reporte de ingresos
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array;

    /**
     * Enviar reporte por correo electrónico
     */
    public function enviarReportePorCorreo(string $email, array $filtros): void;
}