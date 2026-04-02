<?php

namespace App\Repositories\Implementations;

use App\Models\OrdenCompra;
use App\Repositories\Interfaces\OrdenCompraRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrdenCompraRepository implements OrdenCompraRepositoryInterface
{
    public function findById(int $id): ?OrdenCompra
    {
        return OrdenCompra::with([
            'user:id,name',
            'proveedor:id,razon_social,ruc',
            'requerimiento:id,codigo,titulo,area,prioridad',
            'productos',
        ])->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = OrdenCompra::with([
            'user:id,name',
            'proveedor:id,razon_social,ruc',
            'requerimiento:id,codigo,titulo,area,prioridad',
            'productos',
        ])->withCount('productos');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data): OrdenCompra
    {
        return OrdenCompra::create($data);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $orden = OrdenCompra::find($id);
        if (!$orden) {
            return false;
        }
        return $orden->update(['estado' => $nuevoEstado]);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['almacen_id'])) {
            $query->where('almacen_id', $filters['almacen_id']);
        }

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (!empty($filters['proveedor_id'])) {
            $query->where('proveedor_id', $filters['proveedor_id']);
        }

        if (!empty($filters['requerimiento_id'])) {
            $query->where('requerimiento_id', $filters['requerimiento_id']);
        }

        if (!empty($filters['tipo_documento'])) {
            $query->where('tipo_documento', $filters['tipo_documento']);
        }

        if (!empty($filters['forma_de_pago'])) {
            $query->where('forma_de_pago', $filters['forma_de_pago']);
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('fecha', '>=', $filters['desde']);
        }

        if (!empty($filters['hasta'])) {
            $query->whereDate('fecha', '<=', $filters['hasta']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                  ->orWhere('ruc', 'like', "%{$search}%")
                  ->orWhereHas('proveedor', function ($pq) use ($search) {
                      $pq->where('razon_social', 'like', "%{$search}%");
                  });
            });
        }
    }
}
