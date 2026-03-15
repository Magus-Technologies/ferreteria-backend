<?php

namespace App\Services\Interfaces;

use App\Models\CajaPrincipal;
use App\Models\SubCaja;
use Illuminate\Database\Eloquent\Collection;

interface CajaServiceInterface
{
    public function crearCajaPrincipal(string $userId, string $nombre, ?string $metodoPagoId = null, ?string $nombreMetodoPago = null): CajaPrincipal;
    
    public function crearSubCaja(int $cajaPrincipalId, array $data): SubCaja;
    
    public function actualizarSubCaja(int $subCajaId, array $data): SubCaja;
    
    public function eliminarSubCaja(int $subCajaId): bool;
    
    public function obtenerCajaPorUsuario(string $userId): Collection;
    
    public function obtenerSubCajas(int $cajaPrincipalId): array;
}
