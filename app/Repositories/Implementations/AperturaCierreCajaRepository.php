<?php

namespace App\Repositories\Implementations;

use App\Models\AperturaCierreCaja;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;

class AperturaCierreCajaRepository implements AperturaCierreCajaRepositoryInterface
{
    public function findById(string $id): ?AperturaCierreCaja
    {
        return AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'supervisor', 'distribucionesVendedores.vendedor'])
            ->find($id);
    }

    public function findCajaActiva(string $userId): ?AperturaCierreCaja
    {
        // La caja activa va DESDE la apertura HASTA el cierre, sin importar el día.
        // En un día puede haber varios ciclos (abre 8am, cierra 12pm, abre 2pm, cierra 8pm),
        // y una apertura puede quedar abierta de un día para otro. Por eso NO se filtra por
        // fecha: la caja activa es simplemente la apertura que sigue abierta (sin cerrar).
        return AperturaCierreCaja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->whereNull('fecha_cierre')
            ->with(['cajaPrincipal', 'subCaja', 'user', 'supervisor', 'distribucionesVendedores.vendedor'])
            ->orderBy('fecha_apertura', 'desc')
            ->first();
    }

    public function obtenerAperturaActiva(int $cajaId, ?int $subCajaId): ?AperturaCierreCaja
    {
        $query = AperturaCierreCaja::where('estado', 'abierta')
            ->where('caja_principal_id', $cajaId);

        if ($subCajaId) {
            $query->where('sub_caja_id', $subCajaId);
        } else {
            $query->whereNull('sub_caja_id');
        }

        return $query->with(['cajaPrincipal', 'subCaja', 'user'])
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
