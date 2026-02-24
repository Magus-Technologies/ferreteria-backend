<?php

namespace App\Repositories\Interfaces;

use App\Models\SubCaja;
use Illuminate\Database\Eloquent\Collection;

interface SubCajaRepositoryInterface
{
    public function findById(string $id): ?SubCaja;

    public function findByCodigo(string $codigo): ?SubCaja;

    public function findByCajaPrincipalId(int $cajaPrincipalId): Collection;

    public function findCajaChica(int $cajaPrincipalId): ?SubCaja;

    public function create(array $data): SubCaja;

    public function update(string $id, array $data): SubCaja;

    public function delete(string $id): bool;

    public function actualizarSaldo(string $id, float $nuevoSaldo): bool;

    public function generarSiguienteCodigo(string $codigoCajaPrincipal): string;

    /**
     * Valida que los métodos de pago proporcionados no estén en uso
     * por otras sub-cajas de la misma caja principal, exceptuando
     * aquellos métodos que sean de tipo 'efectivo'.
     *
     * @param int $cajaPrincipalId
     * @param array $desplieguePagoIds
     * @param string|null $excludeId ID de la sub-caja a excluir (para ediciones)
     * @throws \Exception Si un método exclusivo ya está en uso
     * @return void
     */
    public function validarExclusividadMetodosPago(
        int $cajaPrincipalId,
        array $desplieguePagoIds,
        ?string $excludeId = null
    ): void;

    public function existeConfiguracionDuplicada(int $cajaPrincipalId, array $desplieguePagoIds, array $tiposComprobante, ?string $excludeId = null): bool;

    public function buscarSubCajaParaVenta(int $cajaPrincipalId, string $tipoComprobante, string $desplieguePagoId): ?SubCaja;
}
