<?php

namespace App\Repositories\Interfaces;

use App\Models\DireccionCliente;
use Illuminate\Support\Collection;

interface DireccionClienteRepositoryInterface
{
    /**
     * Obtener todas las direcciones de un cliente ordenadas por tipo
     */
    public function findByCliente(int $clienteId): Collection;

    /**
     * Obtener una dirección por su ID
     */
    public function findById(int $id): ?DireccionCliente;

    /**
     * Crear una nueva dirección
     */
    public function create(array $data): DireccionCliente;

    /**
     * Actualizar una dirección existente
     */
    public function update(int $id, array $data): DireccionCliente;

    /**
     * Eliminar una dirección
     */
    public function delete(int $id): bool;

    /**
     * Contar direcciones de un cliente
     */
    public function countByCliente(int $clienteId): int;

    /**
     * Obtener la dirección principal de un cliente
     */
    public function findPrincipalByCliente(int $clienteId): ?DireccionCliente;

    /**
     * Actualizar el estado principal de las direcciones de un cliente
     */
    public function updatePrincipalStatus(int $clienteId, int $nuevaPrincipalId): void;
}
