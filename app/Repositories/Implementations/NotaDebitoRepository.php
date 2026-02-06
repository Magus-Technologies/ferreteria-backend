<?php

namespace App\Repositories\Implementations;

use App\Models\NotaDebito;
use App\Repositories\Interfaces\NotaDebitoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotaDebitoRepository implements NotaDebitoRepositoryInterface
{
    public function findById(string $id): ?NotaDebito
    {
        return NotaDebito::with([
            'venta',
            'motivo',
            'usuario',
            'almacen',
            'comprobanteElectronico',
            'comprobanteReferencia'
        ])->find($id);
    }

    public function findBySerieNumero(string $serie, int $numero): ?NotaDebito
    {
        return NotaDebito::where('serie', $serie)
            ->where('numero', $numero)
            ->with(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico'])
            ->first();
    }

    public function getAll(array $filters = []): Collection
    {
        $query = NotaDebito::with(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = NotaDebito::with(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')->paginate($perPage);
    }

    public function getByVenta(string $ventaId): Collection
    {
        return NotaDebito::where('venta_id', $ventaId)
            ->with(['motivo', 'usuario', 'comprobanteElectronico'])
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function getByAlmacen(int $almacenId, array $filters = []): Collection
    {
        $query = NotaDebito::where('almacen_id', $almacenId)
            ->with(['venta', 'motivo', 'usuario', 'comprobanteElectronico']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getByUsuario(string $usuarioId, array $filters = []): Collection
    {
        $query = NotaDebito::where('usuario_id', $usuarioId)
            ->with(['venta', 'motivo', 'almacen', 'comprobanteElectronico']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')->get();
    }

    public function getByEstado(string $estado): Collection
    {
        return NotaDebito::where('estado', $estado)
            ->with(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico'])
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function getPendientesEnvio(): Collection
    {
        return NotaDebito::whereIn('estado', ['borrador', 'pendiente'])
            ->with(['venta', 'motivo', 'usuario', 'almacen'])
            ->orderBy('fecha', 'asc')
            ->get();
    }

    public function create(array $data): NotaDebito
    {
        return NotaDebito::create($data);
    }

    public function update(string $id, array $data): NotaDebito
    {
        $notaDebito = NotaDebito::findOrFail($id);
        $notaDebito->update($data);
        return $notaDebito->fresh(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico']);
    }

    public function delete(string $id): bool
    {
        $notaDebito = NotaDebito::findOrFail($id);
        return $notaDebito->delete();
    }

    public function cambiarEstado(string $id, string $nuevoEstado): bool
    {
        return NotaDebito::where('id', $id)->update(['estado' => $nuevoEstado]);
    }

    public function getSiguienteNumero(string $serie): int
    {
        $ultimaNota = NotaDebito::where('serie', $serie)
            ->orderBy('numero', 'desc')
            ->first();

        return $ultimaNota ? $ultimaNota->numero + 1 : 1;
    }

    public function existeSerieNumero(string $serie, int $numero, ?string $excludeId = null): bool
    {
        $query = NotaDebito::where('serie', $serie)->where('numero', $numero);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Aplicar filtros a la consulta
     */
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
