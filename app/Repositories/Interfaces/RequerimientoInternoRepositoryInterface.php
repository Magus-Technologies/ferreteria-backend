<?php

namespace App\Repositories\Interfaces;

use App\Models\RequerimientoInterno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RequerimientoInternoRepositoryInterface
{
    public function findById(int $id): ?RequerimientoInterno;

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function getAprobadosOC(): Collection;

    public function create(array $data): RequerimientoInterno;

    public function cambiarEstado(int $id, string $nuevoEstado): bool;
}
