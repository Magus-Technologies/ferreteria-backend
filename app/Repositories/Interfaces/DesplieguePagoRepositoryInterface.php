<?php

namespace App\Repositories\Interfaces;

use App\Models\DespliegueDePago;
use Illuminate\Database\Eloquent\Collection;

interface DesplieguePagoRepositoryInterface
{
    public function findById(string $id): ?DespliegueDePago;
    
    public function getAll(): Collection;
    
    public function getAllMostrar(): Collection;
    
    public function create(array $data): DespliegueDePago;
    
    public function update(string $id, array $data): ?DespliegueDePago;
    
    public function delete(string $id): bool;
}
