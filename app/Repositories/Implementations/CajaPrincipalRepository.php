<?php

namespace App\Repositories\Implementations;

use App\Models\CajaPrincipal;
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CajaPrincipalRepository implements CajaPrincipalRepositoryInterface
{
    public function findById(int $id): ?CajaPrincipal
    {
        return CajaPrincipal::with(['user', 'subCajas'])->find($id);
    }

    public function findByCodigo(string $codigo): ?CajaPrincipal
    {
        return CajaPrincipal::where('codigo', $codigo)->first();
    }

    public function findByUserId(string $userId): Collection
    {
        return CajaPrincipal::where('user_id', $userId)
            ->with(['user', 'subCajas'])
            ->get();
    }

    public function getAll(): Collection
    {
        return CajaPrincipal::with(['user', 'subCajas'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): CajaPrincipal
    {
        return CajaPrincipal::create($data);
    }

    public function update(int $id, array $data): CajaPrincipal
    {
        $caja = CajaPrincipal::findOrFail($id);
        $caja->update($data);

        return $caja->fresh(['user', 'subCajas']);
    }

    public function delete(int $id): bool
    {
        $caja = CajaPrincipal::findOrFail($id);

        return $caja->delete();
    }

    public function generarSiguienteCodigo(): string
    {
        $maxNumero = CajaPrincipal::whereRaw("codigo REGEXP '^V[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED)) as max_num')
            ->value('max_num');

        $nuevoNumero = ($maxNumero ?? 0) + 1;

        return 'V'.str_pad($nuevoNumero, 2, '0', STR_PAD_LEFT);
    }
}
