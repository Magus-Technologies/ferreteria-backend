<?php

namespace App\Repositories\Interfaces;

use App\Models\NotaDebito;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotaDebitoRepositoryInterface
{
    /**
     * Buscar nota de débito por ID
     */
    public function findById(string $id): ?NotaDebito;

    /**
     * Buscar nota de débito por serie y número
     */
    public function findBySerieNumero(string $serie, int $numero): ?NotaDebito;

    /**
     * Obtener todas las notas de débito con filtros
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Obtener notas de débito paginadas
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtener notas de débito por venta
     */
    public function getByVenta(string $ventaId): Collection;

    /**
     * Obtener notas de débito por almacén
     */
    public function getByAlmacen(int $almacenId, array $filters = []): Collection;

    /**
     * Obtener notas de débito por usuario
     */
    public function getByUsuario(string $usuarioId, array $filters = []): Collection;

    /**
     * Obtener notas de débito por estado
     */
    public function getByEstado(string $estado): Collection;

    /**
     * Obtener notas de débito pendientes de envío
     */
    public function getPendientesEnvio(): Collection;

    /**
     * Crear nota de débito
     */
    public function create(array $data): NotaDebito;

    /**
     * Actualizar nota de débito
     */
    public function update(string $id, array $data): NotaDebito;

    /**
     * Eliminar nota de débito
     */
    public function delete(string $id): bool;

    /**
     * Cambiar estado de nota de débito
     */
    public function cambiarEstado(string $id, string $nuevoEstado): bool;

    /**
     * Obtener siguiente número de correlativo para una serie
     */
    public function getSiguienteNumero(string $serie): int;

    /**
     * Verificar si existe una nota de débito con serie y número
     */
    public function existeSerieNumero(string $serie, int $numero, ?string $excludeId = null): bool;
}
