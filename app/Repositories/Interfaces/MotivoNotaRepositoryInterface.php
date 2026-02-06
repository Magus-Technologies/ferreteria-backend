<?php

namespace App\Repositories\Interfaces;

use App\Models\MotivoNota;
use Illuminate\Database\Eloquent\Collection;

interface MotivoNotaRepositoryInterface
{
    /**
     * Buscar motivo por ID
     */
    public function findById(int $id): ?MotivoNota;

    /**
     * Buscar motivo por código
     */
    public function findByCodigo(string $codigo): ?MotivoNota;

    /**
     * Obtener todos los motivos activos
     */
    public function getAllActivos(): Collection;

    /**
     * Obtener motivos por tipo (debito o credito)
     */
    public function getByTipo(string $tipo): Collection;

    /**
     * Obtener motivos de débito activos
     */
    public function getMotivosDebito(): Collection;

    /**
     * Obtener motivos de crédito activos
     */
    public function getMotivosCredito(): Collection;
}
