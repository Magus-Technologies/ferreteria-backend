<?php

namespace App\Services\Interfaces;

interface ClienteReporteServiceInterface
{
    /**
     * Top clientes por monto de compras
     */
    public function obtenerTopClientes(array $filtros, int $limit = 10): array;

    /**
     * Resumen KPI de clientes
     */
    public function obtenerResumenClientes(array $filtros): array;

    /**
     * Clientes con deuda pendiente (crédito)
     */
    public function obtenerClientesPorCobrar(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Listado de clientes con historial de compras
     */
    public function obtenerListadoClientes(array $filtros, int $perPage = 50, int $page = 1): array;

    /**
     * Clientes frecuentes (más transacciones)
     */
    public function obtenerClientesFrecuentes(array $filtros, int $limit = 10): array;

    /**
     * Clientes registrados recientemente
     */
    public function obtenerClientesRecientes(array $filtros, int $perPage = 50, int $page = 1): array;
}
