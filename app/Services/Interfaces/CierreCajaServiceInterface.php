<?php

namespace App\Services\Interfaces;

use App\DTOs\CierreCaja\CajaActivaDTO;
use App\DTOs\CierreCaja\CierreCajaResultadoDTO;

interface CierreCajaServiceInterface
{
    /**
     * Obtener la caja activa del usuario con su resumen
     */
    public function obtenerCajaActivaConResumen(string $userId): CajaActivaDTO;

    /**
     * Obtener detalle completo de movimientos
     */
    public function obtenerDetalleMovimientos(string $aperturaId): array;

    /**
     * Cerrar una caja con cálculo automático de diferencias
     */
    public function cerrarCajaConResumen(
        string $aperturaId,
        array $datosCierre
    ): CierreCajaResultadoDTO;

    /**
     * Validar si un usuario puede autorizar como supervisor
     */
    public function validarSupervisor(string $email, string $password): ?array;
}
