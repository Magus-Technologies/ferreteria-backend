<?php

namespace App\Services\Interfaces;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FacturaServiceInterface
{
    public function generarComprobanteDesdeVenta(FacturaDTO $dto): array;
    public function obtenerPorVentaId(string $ventaId): ?array;
    public function listar(array $filtros = []): Collection;
    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator;
    public function enviarASunat(string $ventaId, string $modoEnvio = 'manual', bool $permitirVentaAnulada = false): array;
    public function consultarEstadoSunat(string $ventaId): array;
    public function obtenerXml(string $ventaId): string;
    public function obtenerCdr(string $ventaId): string;
    public function validarVentaParaFacturacion(string $ventaId): array;
}
