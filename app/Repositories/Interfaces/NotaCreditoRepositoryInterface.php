<?php

namespace App\Repositories\Interfaces;

use App\Models\NotaCredito;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotaCreditoRepositoryInterface
{
    public function findById(string $id): ?NotaCredito;
    public function findBySerieNumero(string $serie, int $numero): ?NotaCredito;
    public function getAll(array $filters = []): Collection;
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getByVenta(string $ventaId): Collection;
    public function getByAlmacen(int $almacenId, array $filters = []): Collection;
    public function getByUsuario(string $usuarioId, array $filters = []): Collection;
    public function getByEstado(string $estado): Collection;
    public function getPendientesEnvio(): Collection;
    public function create(array $data): NotaCredito;
    public function update(string $id, array $data): NotaCredito;
    public function delete(string $id): bool;
    public function cambiarEstado(string $id, string $nuevoEstado): bool;
    public function getSiguienteNumero(string $serie): int;
    public function existeSerieNumero(string $serie, int $numero, ?string $excludeId = null): bool;
}
