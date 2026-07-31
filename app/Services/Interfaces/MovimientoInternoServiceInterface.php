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
     * Listar los movimientos internos de TODAS las sub-cajas de una caja principal
     * (sin importar qué usuario los hizo). Usado por el tab "Traslado de Efectivo"
     * del modal de sub-cajas: listarMovimientos() filtra por usuario logueado, así
     * que un admin viendo la caja de otro vendedor no veía nada aunque sí existieran.
     */
    public function listarMovimientosPorCajaPrincipal(int $cajaPrincipalId): array;

    /**
     * Listar depósitos de seguridad (Efectivo → Banco/Billetera)
     */
    public function listarDepositosSeguridad(string|int $userId): array;

    /**
     * Saldos disponibles para movimiento interno por sub-caja.
     * Solo se puede mover dinero de sesiones CERRADAS: el saldo disponible
     * excluye lo generado durante la apertura activa (si la hay).
     */
    public function saldosDisponibles(): array;

    /**
     * Usuarios con saldo disponible de cierres cerrados, agrupado por sub-caja.
     */
    public function usuariosConSaldoDisponible(): array;
}
