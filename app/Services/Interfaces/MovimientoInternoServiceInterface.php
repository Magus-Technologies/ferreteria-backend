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
     * Desglose del "Saldo No Cerrado" de una sub-caja, por despliegue de pago y usuario.
     */
    public function detalleNoCerrado(int $subCajaId): array;

    /**
     * Saldo REAL de una caja principal (Cerrado + No Cerrado de sus sub-cajas).
     */
    public function saldoRealCajaPrincipal(int $cajaPrincipalId): float;

    /**
     * Usuarios con saldo disponible de cierres cerrados, agrupado por sub-caja.
     */
    public function usuariosConSaldoDisponible(): array;

    /**
     * Anular un movimiento interno (Traslado de Efectivo) ya aprobado: revierte
     * las transacciones, movimientos de caja y saldos, y lo marca como anulado.
     */
    public function anularMovimiento(string $movimientoId, string|int $userId): void;
}
