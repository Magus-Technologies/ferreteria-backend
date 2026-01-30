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
    
    public function existeConfiguracionDuplicada(int $cajaPrincipalId, array $desplieguePagoIds, array $tiposComprobante, ?string $excludeId = null): bool;
    
    public function buscarSubCajaParaVenta(int $cajaPrincipalId, string $tipoComprobante, string $desplieguePagoId): ?SubCaja;
}
