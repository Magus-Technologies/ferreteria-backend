<?php

namespace App\Repositories\Interfaces;

use App\Models\AperturaCierreCaja;

interface AperturaCierreCajaRepositoryInterface
{
    public function findById(string $id): ?AperturaCierreCaja;
    
    public function findCajaActiva(string $userId): ?AperturaCierreCaja;
    
    public function create(array $data): AperturaCierreCaja;
    
    public function update(string $id, array $data): bool;
}
