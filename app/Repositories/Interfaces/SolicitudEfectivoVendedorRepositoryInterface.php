<?php

namespace App\Repositories\Interfaces;

use App\Models\SolicitudEfectivoVendedor;
use Illuminate\Database\Eloquent\Collection;

interface SolicitudEfectivoVendedorRepositoryInterface
{
    public function crear(array $data): SolicitudEfectivoVendedor;
    
    public function encontrarPorId(string $id): ?SolicitudEfectivoVendedor;
    
    public function encontrarPorIdConRelaciones(string $id, array $relaciones): ?SolicitudEfectivoVendedor;
    
    public function listarPendientesPorVendedor(int $vendedorId): Collection;
    
    public function actualizar(SolicitudEfectivoVendedor $solicitud, array $data): bool;
}
