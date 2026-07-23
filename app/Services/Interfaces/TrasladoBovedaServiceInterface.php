<?php

namespace App\Services\Interfaces;

use App\Models\TrasladoBoveda;
use Illuminate\Database\Eloquent\Collection;

interface TrasladoBovedaServiceInterface
{
    /**
     * Registrar un nuevo traslado a bóveda
     *
     * @param array $data
     * @return TrasladoBoveda
     * @throws \Exception
     */
    public function registrarTraslado(array $data): TrasladoBoveda;

    /**
     * Obtener traslados de una apertura/cierre específica
     *
     * @param string $aperturaCierreId
     * @return Collection
     */
    public function obtenerTrasladosPorCaja(string $aperturaCierreId): Collection;

    public function obtenerTodosLosTrasladosPorCaja(string $aperturaCierreId): Collection;

    /**
     * Obtener el total trasladado de una caja
     *
     * @param string $aperturaCierreId
     * @return float
     */
    public function obtenerTotalTrasladado(string $aperturaCierreId): float;

    /**
     * Validar contraseña de supervisor
     *
     * @param string $supervisorId
     * @param string $password
     * @return bool
     */
    public function validarSupervisor(string $supervisorId, string $password): bool;

    /**
     * Anular un traslado (requiere supervisor)
     *
     * @param string $trasladoId
     * @param string $supervisorId
     * @param string $password
     * @return bool
     * @throws \Exception
     */
    public function anularTraslado(string $trasladoId, string $supervisorId, string $password): bool;
}

