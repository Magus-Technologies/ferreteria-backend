<?php

namespace App\Services\Interfaces;

use App\DTOs\PrestamoVendedor\CrearSolicitudEfectivoDTO;
use App\DTOs\PrestamoVendedor\RechazarSolicitudDTO;

interface PrestamoVendedorServiceInterface
{
    public function crearSolicitud(CrearSolicitudEfectivoDTO $dto, int|string $vendedorSolicitanteId): array;
    
    public function aprobarSolicitud(string $solicitudId, int|string $vendedorPrestamistaId, int $subCajaOrigenId, ?float $montoAprobado = null): array;
    
    public function rechazarSolicitud(string $solicitudId, RechazarSolicitudDTO $dto, int|string $vendedorPrestamistaId): void;
    
    public function listarSolicitudesPendientes(int|string $vendedorId): array;
    
    public function listarTodasLasSolicitudes(int|string $vendedorId): array;
    
    public function obtenerVendedoresConEfectivo(string $aperturaId, int|string $vendedorActualId): array;
    
    public function calcularEfectivoDisponible(string $aperturaId, int|string $vendedorId): float;
    
    public function listarTransferencias(int|string $vendedorId): array;
}
