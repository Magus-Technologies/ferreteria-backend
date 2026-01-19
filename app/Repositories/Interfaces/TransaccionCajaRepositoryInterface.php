<?php

namespace App\Repositories\Interfaces;

use App\Models\TransaccionCaja;
use Illuminate\Pagination\LengthAwarePaginator;

interface TransaccionCajaRepositoryInterface
{
    public function findById(string $id): ?TransaccionCaja;
    
    public function create(array $data): TransaccionCaja;
    
    public function getBySubCaja(int $subCajaId, int $perPage = 15): LengthAwarePaginator;
    
    public function getByCajaPrincipal(int $cajaPrincipalId, int $perPage = 15): LengthAwarePaginator;
    
    public function getByFechaRango(int $subCajaId, string $fechaInicio, string $fechaFin): array;
}
