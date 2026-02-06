<?php

namespace App\Services\Interfaces;

use App\DTOs\FacturacionElectronica\NotaCreditoDTO;
use App\Models\NotaCredito;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotaCreditoServiceInterface
{
    public function crear(NotaCreditoDTO $dto): NotaCredito;
    public function obtenerPorId(string $id): ?NotaCredito;
    public function listar(array $filtros = []): Collection;
    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator;
    public function obtenerPorVenta(string $ventaId): Collection;
    public function actualizar(string $id, NotaCreditoDTO $dto): NotaCredito;
    public function cancelar(string $id, string $motivo): bool;
    public function enviarASunat(string $id, string $modoEnvio = 'manual'): array;
    public function consultarEstadoSunat(string $id): array;
    public function obtenerXml(string $id): string;
    public function obtenerCdr(string $id): string;
    public function validarVentaParaNotaCredito(string $ventaId): array;
    public function calcularTotales(array $items): array;
}
