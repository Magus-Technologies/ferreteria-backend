<?php

namespace App\Repositories\Implementations;

use App\Models\DespliegueDePago;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DesplieguePagoRepository implements DesplieguePagoRepositoryInterface
{
    public function findById(string $id): ?DespliegueDePago
    {
        return DespliegueDePago::with('metodoDePago')->find($id);
    }

    public function getAll(): Collection
    {
        return DespliegueDePago::with('metodoDePago')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllMostrar(): Collection
    {
        return DespliegueDePago::with('metodoDePago')
            ->where('mostrar', 1)
            ->orderBy('name', 'asc')
            ->get();
    }
}
