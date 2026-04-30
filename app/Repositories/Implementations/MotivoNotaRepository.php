<?php

namespace App\Repositories\Implementations;

use App\Models\MotivoNota;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MotivoNotaRepository implements MotivoNotaRepositoryInterface
{
    public function findById(int $id): ?MotivoNota
    {
        $motivo = MotivoNota::find($id);
        
        if ($motivo) {
        } else {
        }
        
        return $motivo;
    }

    public function findByCodigo(string $codigo): ?MotivoNota
    {
        return MotivoNota::where('codigo', $codigo)->first();
    }

    public function getAllActivos(): Collection
    {
        return MotivoNota::where('estado', 1)
            ->orderBy('tipo')
            ->orderBy('codigo_sunat')
            ->get();
    }

    public function getByTipo(string $tipo): Collection
    {
        return MotivoNota::where('tipo', $tipo)
            ->where('estado', 1)
            ->orderBy('codigo_sunat')
            ->get();
    }

    public function getMotivosDebito(): Collection
    {
        return $this->getByTipo('ND');
    }

    public function getMotivosCredito(): Collection
    {
        return $this->getByTipo('NC');
    }
}
