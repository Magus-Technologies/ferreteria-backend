<?php

namespace App\Services;

use App\Models\DireccionCliente;
use App\Repositories\Interfaces\DireccionClienteRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para manejar la lógica de negocio de Direcciones de Clientes
 */
class DireccionClienteService
{
    public function __construct(
        private DireccionClienteRepositoryInterface $repository
    ) {}

    /**
     * Listar todas las direcciones de un cliente
     */
    public function listarDirecciones(int $clienteId): Collection
    {
        return $this->repository->findByCliente($clienteId);
    }

    /**
     * Crear una nueva dirección para un cliente
     * 
     * @throws ValidationException
     */
    public function crearDireccion(int $clienteId, array $data): DireccionCliente
    {
        return DB::transaction(function () use ($clienteId, $data) {
            // Validar límite de 4 direcciones
            $this->validarLimiteDirecciones($clienteId);

            // Asignar tipo automáticamente
            $tipo = $this->asignarTipoAutomatico($clienteId);

            // Determinar si es la primera dirección (será principal)
            $esPrimera = $this->repository->countByCliente($clienteId) === 0;

            // Crear la dirección
            return $this->repository->create([
                'cliente_id' => $clienteId,
                'tipo' => $tipo,
                'direccion' => $data['direccion'],
                'latitud' => $data['latitud'] ?? null,
                'longitud' => $data['longitud'] ?? null,
                'es_principal' => $esPrimera,
            ]);
        });
    }

    /**
     * Actualizar una dirección existente
     */
    public function actualizarDireccion(int $direccionId, array $data): DireccionCliente
    {
        $direccion = $this->repository->findById($direccionId);

        if (!$direccion) {
            throw ValidationException::withMessages([
                'direccion' => ['Dirección no encontrada'],
            ]);
        }

        $datosActualizacion = [];

        if (isset($data['direccion'])) {
            $datosActualizacion['direccion'] = $data['direccion'];
        }

        if (isset($data['latitud'])) {
            $datosActualizacion['latitud'] = $data['latitud'];
        }

        if (isset($data['longitud'])) {
            $datosActualizacion['longitud'] = $data['longitud'];
        }

        return $this->repository->update($direccionId, $datosActualizacion);
    }

    /**
     * Eliminar una dirección (excepto si es principal)
     * 
     * @throws ValidationException
     */
    public function eliminarDireccion(int $direccionId): bool
    {
        $direccion = $this->repository->findById($direccionId);

        if (!$direccion) {
            throw ValidationException::withMessages([
                'direccion' => ['Dirección no encontrada'],
            ]);
        }

        if ($direccion->es_principal) {
            throw ValidationException::withMessages([
                'direccion' => ['No se puede eliminar la dirección principal. Primero marque otra dirección como principal.'],
            ]);
        }

        return $this->repository->delete($direccionId);
    }

    /**
     * Marcar una dirección como principal
     */
    public function marcarComoPrincipal(int $direccionId): DireccionCliente
    {
        $direccion = $this->repository->findById($direccionId);

        if (!$direccion) {
            throw ValidationException::withMessages([
                'direccion' => ['Dirección no encontrada'],
            ]);
        }

        // Actualizar el estado principal de todas las direcciones del cliente
        $this->repository->updatePrincipalStatus($direccion->cliente_id, $direccionId);

        // Retornar la dirección actualizada
        return $this->repository->findById($direccionId);
    }

    /**
     * Validar que el cliente no tenga más de 4 direcciones
     * 
     * @throws ValidationException
     */
    private function validarLimiteDirecciones(int $clienteId): void
    {
        $count = $this->repository->countByCliente($clienteId);

        if ($count >= 4) {
            throw ValidationException::withMessages([
                'limite' => ['El cliente ya tiene el máximo de 4 direcciones permitidas'],
            ]);
        }
    }

    /**
     * Asignar tipo automático según las direcciones existentes
     */
    private function asignarTipoAutomatico(int $clienteId): string
    {
        $direccionesExistentes = $this->repository->findByCliente($clienteId);
        $tiposUsados = $direccionesExistentes->pluck('tipo')->toArray();

        $tiposDisponibles = ['D1', 'D2', 'D3', 'D4'];

        foreach ($tiposDisponibles as $tipo) {
            if (!in_array($tipo, $tiposUsados)) {
                return $tipo;
            }
        }

        // Esto no debería ocurrir si validarLimiteDirecciones funciona correctamente
        return 'D1';
    }
}
