<?php

namespace App\Services\Interfaces;

use App\DTOs\Prestamo\AprobarPrestamoDTO;
use App\DTOs\Prestamo\CrearPrestamoDTO;
use App\DTOs\Prestamo\RechazarPrestamoDTO;
use App\Models\PrestamoEntreCajas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PrestamoEntreCajasServiceInterface
{
    public function listarPrestamos(int $perPage = 15): LengthAwarePaginator;
    
    public function listarPendientes(string $userId): Collection;
    
    public function crearSolicitud(CrearPrestamoDTO $dto): PrestamoEntreCajas;
    
    public function aprobar(AprobarPrestamoDTO $dto): PrestamoEntreCajas;
    
    public function rechazar(RechazarPrestamoDTO $dto): PrestamoEntreCajas;
    
    public function devolver(string $prestamoId, string $userId): PrestamoEntreCajas;
}
