<?php

namespace App\Services\Interfaces;

use App\Models\RequerimientoInterno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RequerimientoInternoServiceInterface
{
    /**
     * Listar requerimientos con filtros y paginación
     */
    public function listarPaginado(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtener requerimientos aprobados de tipo OC (para el modal de solicitudes)
     */
    public function getAprobadosOC(): Collection;

    /**
     * Obtener un requerimiento por ID
     */
    public function obtenerPorId(int $id): RequerimientoInterno;

    /**
     * Crear un requerimiento interno con sus productos (OC) o servicio (OS)
     */
    public function crear(array $data): RequerimientoInterno;

    /**
     * Cambiar estado de un requerimiento (aprobar, rechazar, anular)
     */
    public function cambiarEstado(int $id, string $nuevoEstado): RequerimientoInterno;
}
