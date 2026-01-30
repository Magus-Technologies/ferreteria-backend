<?php

namespace App\Repositories\Implementations;

use App\Models\SolicitudEfectivoVendedor;
use App\Repositories\Interfaces\SolicitudEfectivoVendedorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SolicitudEfectivoVendedorRepository implements SolicitudEfectivoVendedorRepositoryInterface
{
    public function crear(array $data): SolicitudEfectivoVendedor
    {
        return SolicitudEfectivoVendedor::create($data);
    }
    
    public function encontrarPorId(string $id): ?SolicitudEfectivoVendedor
    {
        return SolicitudEfectivoVendedor::find($id);
    }
    
    public function encontrarPorIdConRelaciones(string $id, array $relaciones): ?SolicitudEfectivoVendedor
    {
        return SolicitudEfectivoVendedor::with($relaciones)->find($id);
    }
    
    public function listarPendientesPorVendedor(int $vendedorId): Collection
    {
        return SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'aperturaCierreCaja'])
            ->where('vendedor_prestamista_id', $vendedorId)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();
    }
    
    public function actualizar(SolicitudEfectivoVendedor $solicitud, array $data): bool
    {
        return $solicitud->update($data);
    }
}
