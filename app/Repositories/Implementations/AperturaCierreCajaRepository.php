<?php

namespace App\Repositories\Implementations;

use App\Models\AperturaCierreCaja;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;

class AperturaCierreCajaRepository implements AperturaCierreCajaRepositoryInterface
{
    public function findById(string $id): ?AperturaCierreCaja
    {
        return AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'supervisor'])
            ->find($id);
    }

    public function findCajaActiva(string $userId): ?AperturaCierreCaja
    {
        // Buscar apertura activa donde el usuario tenga distribución de efectivo
        return AperturaCierreCaja::where('estado', 'abierta')
            ->whereHas('distribucionesVendedores', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['cajaPrincipal', 'subCaja', 'user'])
            ->first();
    }

    public function create(array $data): AperturaCierreCaja
    {
        return AperturaCierreCaja::create($data);
    }

    public function update(string $id, array $data): bool
    {
        $apertura = $this->findById($id);
        
        if (!$apertura) {
            return false;
        }

        return $apertura->update($data);
    }
}
