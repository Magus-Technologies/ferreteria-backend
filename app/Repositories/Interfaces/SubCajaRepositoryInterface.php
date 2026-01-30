<?php

namespace App\Repositories\Interfaces;

use App\Models\SubCaja;
use Illuminate\Database\Eloquent\Collection;

interface SubCajaRepositoryInterface
{
<<<<<<< HEAD
    public function findById(int $id): ?SubCaja;
=======
    public function findById(string $id): ?SubCaja;
>>>>>>> e952a7ec840df1a48f482add8cb992efc1f2ca3e
    
    public function findByCodigo(string $codigo): ?SubCaja;
    
    public function findByCajaPrincipalId(int $cajaPrincipalId): Collection;
    
    public function findCajaChica(int $cajaPrincipalId): ?SubCaja;
    
    public function create(array $data): SubCaja;
    
<<<<<<< HEAD
    public function update(int $id, array $data): SubCaja;
    
    public function delete(int $id): bool;
    
    public function actualizarSaldo(int $id, float $nuevoSaldo): bool;
    
    public function generarSiguienteCodigo(string $codigoCajaPrincipal): string;
    
    public function existeConfiguracionDuplicada(int $cajaPrincipalId, array $desplieguePagoIds, array $tiposComprobante, ?int $excludeId = null): bool;
=======
    public function update(string $id, array $data): SubCaja;
    
    public function delete(string $id): bool;
    
    public function actualizarSaldo(string $id, float $nuevoSaldo): bool;
    
    public function generarSiguienteCodigo(string $codigoCajaPrincipal): string;
    
    public function existeConfiguracionDuplicada(int $cajaPrincipalId, array $desplieguePagoIds, array $tiposComprobante, ?string $excludeId = null): bool;
>>>>>>> e952a7ec840df1a48f482add8cb992efc1f2ca3e
    
    public function buscarSubCajaParaVenta(int $cajaPrincipalId, string $tipoComprobante, string $desplieguePagoId): ?SubCaja;
}
