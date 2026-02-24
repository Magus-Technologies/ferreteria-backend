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
        // CORREGIDO: Buscar apertura del día actual (abierta o cerrada)
        // La caja debe estar visible hasta el día siguiente, incluso después del arqueo diario
        $hoy = now()->startOfDay();
        $manana = now()->addDay()->startOfDay();

        return AperturaCierreCaja::where('user_id', $userId)
            ->where('fecha_apertura', '>=', $hoy)
            ->where('fecha_apertura', '<', $manana)
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
