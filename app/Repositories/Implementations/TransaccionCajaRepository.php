<?php

namespace App\Repositories\Implementations;

use App\Models\TransaccionCaja;
use App\Repositories\Interfaces\TransaccionCajaRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TransaccionCajaRepository implements TransaccionCajaRepositoryInterface
{
    public function findById(string $id): ?TransaccionCaja
    {
        return TransaccionCaja::with(['subCaja', 'user'])->find($id);
    }

    public function create(array $data): TransaccionCaja
    {
        return TransaccionCaja::create($data);
    }

    public function getBySubCaja(int $subCajaId, int $perPage = 15): LengthAwarePaginator
    {
        return TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->with('user:id,name')
            ->orderBy('fecha', 'desc')
            ->paginate($perPage);
    }

    public function getByCajaPrincipal(int $cajaPrincipalId, int $perPage = 15): LengthAwarePaginator
    {
        return TransaccionCaja::whereHas('subCaja', function ($query) use ($cajaPrincipalId) {
                $query->where('caja_principal_id', $cajaPrincipalId);
            })
            ->with(['user:id,name', 'subCaja:id,nombre,caja_principal_id'])
            ->orderBy('fecha', 'desc')
            ->paginate($perPage);
    }

    public function getByFechaRango(int $subCajaId, string $fechaInicio, string $fechaFin): array
    {
        return TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->with('user:id,name')
            ->orderBy('fecha', 'desc')
            ->get()
            ->toArray();
    }
}
