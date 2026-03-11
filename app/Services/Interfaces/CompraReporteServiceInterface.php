<?php

namespace App\Services\Interfaces;

interface CompraReporteServiceInterface
{
    public function obtenerResumenMensual(array $filtros): array;
    public function obtenerReporteCompras(array $filtros, int $perPage = 50, int $page = 1): array;
    public function obtenerResumenCompras(array $filtros): array;
}
