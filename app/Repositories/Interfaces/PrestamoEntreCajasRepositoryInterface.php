<?php

namespace App\Repositories\Interfaces;

use App\Models\PrestamoEntreCajas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PrestamoEntreCajasRepositoryInterface
{
    public function findById(string $id): ?PrestamoEntreCajas;
    
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;
    
    public function getPendientesByUserId(string $userId): Collection;
    
    public function create(array $data): PrestamoEntreCajas;
    
    public function update(string $id, array $data): PrestamoEntreCajas;
}
