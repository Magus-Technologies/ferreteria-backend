<?php

namespace App\Services\Interfaces;

use App\DTOs\PrestamoVendedor\CrearSolicitudEfectivoDTO;
use App\DTOs\PrestamoVendedor\RechazarSolicitudDTO;

interface PrestamoVendedorServiceInterface
{
    public function crearSolicitud(CrearSolicitudEfectivoDTO $dto, int $vendedorSolicitanteId): array;
    
    public function aprobarSolicitud(string $solicitudId, int $vendedorPrestamistaId): array;
    
    public function rechazarSolicitud(string $solicitudId, RechazarSolicitudDTO $dto, int $vendedorPrestamistaId): void;
    
    public function listarSolicitudesPendientes(int $vendedorId): array;
    
    public function listarTodasLasSolicitudes(int $vendedorId): array;
    
    public function obtenerVendedoresConEfectivo(string $aperturaId, int $vendedorActualId): array;
    
    public function calcularEfectivoDisponible(string $aperturaId, int $vendedorId): float;
    
    public function listarTransferencias(int $vendedorId): array;
}
