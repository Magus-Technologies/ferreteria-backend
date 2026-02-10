<?php

namespace App\Repositories\Implementations;

use App\Models\NotaCredito;
use App\Repositories\Interfaces\NotaCreditoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotaCreditoRepository implements NotaCreditoRepositoryInterface
{
    public function findById(string $id): ?NotaCredito
    {
        return NotaCredito::with([
            'venta.cliente',
            'motivo',
            'usuario',
            'almacen',
            'comprobanteReferencia.detalles' //  Cargar comprobante con detalles
        ])->find($id);
    }

    public function findBySerieNumero(string $serie, int $numero): ?NotaCredito
    {
        return NotaCredito::where('serie', $serie)
            ->where('numero', $numero)
            ->with(['venta', 'motivo', 'usuario', 'almacen']) // Removido comprobanteElectronico
            ->first();
    }

    public function getAll(array $filters = []): Collection
    {
        $query = NotaCredito::with([
            'venta.cliente',
            'motivo',
            'usuario',
            'almacen',
            'comprobanteReferencia.detalles' //  Cargar comprobante con detalles
        ]);
        $this->applyFilters($query, $filters);
        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = NotaCredito::with([
            'venta.cliente',
            'motivo',
            'usuario',
            'almacen',
            'comprobanteReferencia.detalles' //  Cargar comprobante con detalles
        ]);
        $this->applyFilters($query, $filters);
        return $query->orderBy('fecha', 'desc')->paginate($perPage);
    }

    public function getByVenta(string $ventaId): Collection
    {
        return NotaCredito::where('venta_id', $ventaId)
            ->with(['motivo', 'usuario']) // Removido comprobanteElectronico
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function getByAlmacen(int $almacenId, array $filters = []): Collection
    {
        $query = NotaCredito::where('almacen_id', $almacenId)
            ->with(['venta', 'motivo', 'usuario']); // Removido comprobanteElectronico
        $this->applyFilters($query, $filters);
        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getByUsuario(string $usuarioId, array $filters = []): Collection
    {
        $query = NotaCredito::where('usuario_id', $usuarioId)
            ->with(['venta', 'motivo', 'almacen']); // Removido comprobanteElectronico
        $this->applyFilters($query, $filters);
        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getByEstado(string $estado): Collection
    {
        return NotaCredito::where('estado', $estado)
            ->with(['venta', 'motivo', 'usuario', 'almacen']) // Removido comprobanteElectronico
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function getPendientesEnvio(): Collection
    {
        return NotaCredito::whereIn('estado', ['borrador', 'pendiente'])
            ->with(['venta', 'motivo', 'usuario', 'almacen'])
            ->orderBy('fecha', 'asc')
            ->get();
    }

    public function create(array $data): NotaCredito
    {
        return NotaCredito::create($data);
    }

    public function update(string $id, array $data): NotaCredito
    {
        $notaCredito = NotaCredito::findOrFail($id);
        $notaCredito->update($data);
        return $notaCredito->fresh(['venta', 'motivo', 'usuario', 'almacen']); // Removido comprobanteElectronico
    }

    public function delete(string $id): bool
    {
        $notaCredito = NotaCredito::findOrFail($id);
        return $notaCredito->delete();
    }

    public function cambiarEstado(string $id, string $nuevoEstado): bool
    {
        return NotaCredito::where('id', $id)->update(['estado' => $nuevoEstado]);
    }

    public function getSiguienteNumero(string $serie): int
    {
        $ultimaNota = NotaCredito::where('serie', $serie)
            ->orderBy('numero', 'desc')
            ->first();
        return $ultimaNota ? $ultimaNota->numero + 1 : 1;
    }

    public function existeSerieNumero(string $serie, int $numero, ?string $excludeId = null): bool
    {
        $query = NotaCredito::where('serie', $serie)->where('numero', $numero);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }
        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $query->whereBetween('fecha', [$filters['fecha_inicio'], $filters['fecha_fin']]);
        }
        if (!empty($filters['motivo_id'])) {
            $query->where('motivo_id', $filters['motivo_id']);
        }
        if (!empty($filters['serie'])) {
            $query->where('serie', $filters['serie']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('numero', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhereHas('venta', function ($ventaQuery) use ($search) {
                        $ventaQuery->where('serie', 'like', "%{$search}%")
                            ->orWhere('numero', 'like', "%{$search}%");
                    });
            });
        }
    }
}
