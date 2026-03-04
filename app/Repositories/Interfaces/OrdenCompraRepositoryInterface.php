<?php

namespace App\Repositories\Interfaces;

use App\Models\OrdenCompra;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrdenCompraRepositoryInterface
{
    public function findById(int $id): ?OrdenCompra;

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function create(array $data): OrdenCompra;

    public function cambiarEstado(int $id, string $nuevoEstado): bool;
}
