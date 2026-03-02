<?php

namespace App\Services\Interfaces;

use App\Models\AbonoDeudaPersonal;

interface AbonoDeudaServiceInterface
{
    /**
     * Registrar un abono a una deuda personal
     *
     * @param array $data
     * @return AbonoDeudaPersonal
     */
    public function registrarAbono(array $data): AbonoDeudaPersonal;

    /**
     * Obtener historial de abonos de una deuda
     *
     * @param int $deudaId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerHistorialAbonos(int $deudaId);

    /**
     * Obtener resumen de deudas de un usuario
     *
     * @param int|string $userId
     * @return array
     */
    public function obtenerResumenDeudas(int|string $userId): array;

    /**
     * Actualizar un abono existente
     *
     * @param int $abonoId
     * @param array $data
     * @return AbonoDeudaPersonal
     */
    public function actualizarAbono(int $abonoId, array $data): AbonoDeudaPersonal;

    /**
     * Eliminar un abono
     *
     * @param int $abonoId
     * @return bool
     */
    public function eliminarAbono(int $abonoId): bool;
}
