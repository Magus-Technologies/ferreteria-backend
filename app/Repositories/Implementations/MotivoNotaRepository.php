<?php

namespace App\Repositories\Implementations;

use App\Models\MotivoNota;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MotivoNotaRepository implements MotivoNotaRepositoryInterface
{
    public function findById(int $id): ?MotivoNota
    {
        return MotivoNota::find($id);
    }

    public function findByCodigo(string $codigo): ?MotivoNota
    {
        return MotivoNota::where('codigo', $codigo)->first();
    }

    public function getAllActivos(): Collection
    {
        return MotivoNota::where('activo', true)
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->get();
    }

    public function getByTipo(string $tipo): Collection
    {
        return MotivoNota::where('tipo', $tipo)
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();
    }

    public function getMotivosDebito(): Collection
    {
        return $this->getByTipo('debito');
    }

    public function getMotivosCredito(): Collection
    {
        return $this->getByTipo('credito');
    }
}
