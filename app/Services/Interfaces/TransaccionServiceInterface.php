<?php

namespace App\Services\Interfaces;

use App\Models\TransaccionCaja;

interface TransaccionServiceInterface
{
    public function registrarIngreso(
        int $subCajaId,
        float $monto,
        string $descripcion,
        ?string $referenciaId = null,
        ?string $referenciaTipo = null,
        ?array $conteoBilletesMonedas = null,
        ?string $desplieguePagoId = null
    ): TransaccionCaja;
    
    public function registrarEgreso(
        int $subCajaId,
        float $monto,
        string $descripcion,
        ?string $referenciaId = null,
        ?string $referenciaTipo = null,
        ?array $conteoBilletesMonedas = null,
        ?string $desplieguePagoId = null
    ): TransaccionCaja;
    
    public function obtenerTransacciones(int $subCajaId, int $perPage = 15): array;
}
