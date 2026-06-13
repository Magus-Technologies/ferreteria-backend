<?php

namespace App\Services\Interfaces;

interface DashboardContableServiceInterface
{
    /** Margen de ganancia (%) por mes del periodo. */
    public function porcentajeGananciasPorMes(array $filtros): array;

    /** Pérdida total (faltantes) de cierres de caja por mes. */
    public function cierresCajaConPerdidaPorMes(array $filtros): array;

    /** Top clientes por deuda vencida (ventas a crédito vencidas y no pagadas). */
    public function clientesMorosos(array $filtros, int $limit = 10): array;

    /** Ganancia generada por cada cliente recomendador. */
    public function gananciasPorRecomendador(array $filtros, int $limit = 10): array;

    /** KPIs de las tarjetas: ganancias, capital, ventas por cobrar, compras por pagar, caja. */
    public function resumenCards(array $filtros): array;
}
