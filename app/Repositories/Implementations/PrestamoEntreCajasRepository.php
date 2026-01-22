<?php

namespace App\Repositories\Implementations;

use App\Models\PrestamoEntreCajas;
use App\Repositories\Interfaces\PrestamoEntreCajasRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PrestamoEntreCajasRepository implements PrestamoEntreCajasRepositoryInterface
{
    public function findById(string $id): ?PrestamoEntreCajas
    {
        return PrestamoEntreCajas::with([
            'cajaPrincipalOrigen',
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe',
            'aprobadoPor'
        ])->find($id);
    }

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return PrestamoEntreCajas::with([
            'cajaPrincipalOrigen',
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe'
        ])
        ->orderBy('fecha_prestamo', 'desc')
        ->paginate($perPage);
    }

    public function getPendientesByUserId(string $userId): Collection
    {
        // Solo préstamos creados en la última hora
        $unaHoraAtras = now()->subHour();
        
        return PrestamoEntreCajas::with([
            'cajaPrincipalOrigen',
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe'
        ])
        ->where('user_presta_id', $userId) // Solo los que YO debo aprobar
        ->where('estado_aprobacion', 'pendiente_aprobacion')
        ->where('fecha_prestamo', '>=', $unaHoraAtras) // Solo de la última hora
        ->orderBy('fecha_prestamo', 'desc')
        ->get();
    }

    public function create(array $data): PrestamoEntreCajas
    {
        $prestamo = PrestamoEntreCajas::create($data);
        
        return $prestamo->load([
            'cajaPrincipalOrigen',
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe'
        ]);
    }

    public function update(string $id, array $data): PrestamoEntreCajas
    {
        $prestamo = PrestamoEntreCajas::findOrFail($id);
        $prestamo->update($data);
        
        return $prestamo->fresh([
            'cajaPrincipalOrigen',
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe',
            'aprobadoPor'
        ]);
    }
}
