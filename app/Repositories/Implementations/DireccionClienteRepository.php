<?php

namespace App\Repositories\Implementations;

use App\Models\DireccionCliente;
use App\Repositories\Interfaces\DireccionClienteRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DireccionClienteRepository implements DireccionClienteRepositoryInterface
{
    /**
     * Obtener todas las direcciones de un cliente ordenadas por tipo
     */
    public function findByCliente(int $clienteId): Collection
    {
        return DireccionCliente::where('cliente_id', $clienteId)
            ->orderByRaw("FIELD(tipo, 'D1', 'D2', 'D3', 'D4')")
            ->get();
    }

    /**
     * Obtener una dirección por su ID
     */
    public function findById(int $id): ?DireccionCliente
    {
        return DireccionCliente::find($id);
    }

    /**
     * Crear una nueva dirección
     */
    public function create(array $data): DireccionCliente
    {
        return DireccionCliente::create($data);
    }

    /**
     * Actualizar una dirección existente
     */
    public function update(int $id, array $data): DireccionCliente
    {
        $direccion = DireccionCliente::findOrFail($id);
        $direccion->update($data);
        return $direccion->fresh();
    }

    /**
     * Eliminar una dirección
     */
    public function delete(int $id): bool
    {
        $direccion = DireccionCliente::findOrFail($id);
        return $direccion->delete();
    }

    /**
     * Contar direcciones de un cliente
     */
    public function countByCliente(int $clienteId): int
    {
        return DireccionCliente::where('cliente_id', $clienteId)->count();
    }

    /**
     * Obtener la dirección principal de un cliente
     */
    public function findPrincipalByCliente(int $clienteId): ?DireccionCliente
    {
        return DireccionCliente::where('cliente_id', $clienteId)
            ->where('es_principal', true)
            ->first();
    }

    /**
     * Actualizar el estado principal de las direcciones de un cliente.
     *
     * IMPORTANTE: No reasigna el campo `tipo` (D1/D2/D3/D4) porque la tabla
     * tiene un UNIQUE CONSTRAINT sobre (cliente_id, tipo) y reasignar tipos
     * causa un duplicate key error. El flag `es_principal` es suficiente para
     * indicar cuál es la dirección principal — el `tipo` es solo un label.
     */
    public function updatePrincipalStatus(int $clienteId, int $nuevaPrincipalId): void
    {
        DB::transaction(function () use ($clienteId, $nuevaPrincipalId) {
            // Desmarcar todas las direcciones del cliente como no principales
            DireccionCliente::where('cliente_id', $clienteId)
                ->update(['es_principal' => false]);

            // Marcar la nueva dirección como principal (sin cambiar tipo)
            DireccionCliente::where('id', $nuevaPrincipalId)
                ->update(['es_principal' => true]);
        });
    }
}
