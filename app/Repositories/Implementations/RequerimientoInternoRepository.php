<?php

namespace App\Repositories\Implementations;

use App\Models\RequerimientoInterno;
use App\Repositories\Interfaces\RequerimientoInternoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RequerimientoInternoRepository implements RequerimientoInternoRepositoryInterface
{
    public function findById(int $id): ?RequerimientoInterno
    {
        return RequerimientoInterno::with([
            'user:id,name',
            'proveedorSugerido:id,razon_social,ruc',
            'productos.producto:id,cod_producto,name,marca_id,unidad_medida_id',
            'productos.producto.marca:id,name',
            'productos.producto.unidadMedida:id,name',
            'servicios',
            'vehiculo:id,name,tipo,placa',
        ])->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = RequerimientoInterno::with([
            'user:id,name',
            'proveedorSugerido:id,razon_social,ruc',
            'productos.producto:id,cod_producto,name,marca_id,unidad_medida_id',
            'productos.producto.marca:id,name',
            'productos.producto.unidadMedida:id,name',
            'servicios',
            'vehiculo:id,name,tipo,placa',
        ]);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getAprobadosOC(): Collection
    {
        return RequerimientoInterno::with([
            'user:id,name',
            'productos.producto:id,cod_producto,name,marca_id,unidad_medida_id',
            'productos.producto.marca:id,name',
            'productos.producto.unidadMedida:id,name',
        ])
            ->whereIn('estado', ['aprobado', 'pendiente'])
            ->where('tipo_solicitud', 'OC')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): RequerimientoInterno
    {
        return RequerimientoInterno::create($data);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        return RequerimientoInterno::where('id', $id)->update(['estado' => $nuevoEstado]);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['estado'])) {
            $estados = is_array($filters['estado']) ? $filters['estado'] : explode(',', $filters['estado']);
            $query->whereIn('estado', (array)$estados);
        }

        if (!empty($filters['tipo_solicitud'])) {
            $query->where('tipo_solicitud', $filters['tipo_solicitud']);
        }

        if (!empty($filters['assigned_cargo_id'])) {
            // Búsqueda por ID del cargo asignado - solo si está especificado
            $query->where('assigned_cargo_id', $filters['assigned_cargo_id']);
        }

        if (!empty($filters['prioridad'])) {
            $query->where('prioridad', $filters['prioridad']);
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if (!empty($filters['hasta'])) {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                  ->orWhere('titulo', 'like', "%{$search}%")
                  ->orWhere('cargo', 'like', "%{$search}%");
            });
        }
    }
}
