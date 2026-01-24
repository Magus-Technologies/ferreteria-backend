<?php

namespace App\Services\Interfaces;

use App\DTOs\MovimientoInterno\CrearMovimientoInternoDTO;

interface MovimientoInternoServiceInterface
{
    /**
     * Crear un movimiento interno entre sub-cajas
     */
    public function crearMovimiento(CrearMovimientoInternoDTO $dto, string|int $userId): array;

    /**
     * Listar todos los movimientos internos del usuario
     */
    public function listarMovimientos(string|int $userId): array;

    /**
     * Listar depósitos de seguridad (Efectivo → Banco/Billetera)
     */
    public function listarDepositosSeguridad(string|int $userId): array;
}
