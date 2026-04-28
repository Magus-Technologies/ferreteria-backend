<?php

namespace App\Repositories\Implementations;

use App\Models\KardexFacturacion;
use App\Repositories\Interfaces\KardexRepositoryInterface;
use Illuminate\Support\Facades\DB;

class KardexFacturacionRepository implements KardexRepositoryInterface
{
    public function getPaginated(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        $query = KardexFacturacion::query();
        $this->applyFilters($query, $filters);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $data = $query
            ->orderByDesc('fecha')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
        ];
    }

    public function create(array $data): KardexFacturacion
    {
        return KardexFacturacion::create($data);
    }

    public function getTotal(array $filters = []): int
    {
        $query = KardexFacturacion::query();
        $this->applyFilters($query, $filters);
        return $query->count();
    }

    public function getStockInfo(?int $productoId, ?int $almacenId): array
    {
        $query = DB::table('productoalmacen');

        if ($productoId) {
            $query->where('producto_id', $productoId);
        }

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        $stockActual = (float) $query->sum('stock_fraccion');

        return [
            'stock_actual' => $stockActual,
        ];
    }

    public function getPeriodTotals(array $filters = []): array
    {
        $query = KardexFacturacion::query();
        $this->applyFilters($query, $filters);

        $totals = $query
            ->selectRaw('
                COALESCE(SUM(cant_ingreso), 0) as total_entradas,
                COALESCE(SUM(cant_salida), 0) as total_salidas
            ')
            ->first();

        return [
            'total_entradas' => (float) $totals->total_entradas,
            'total_salidas' => (float) $totals->total_salidas,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['producto_id'])) {
            $query->where('producto_almacen_id', $filters['producto_id']);
        }

        if (!empty($filters['almacen_id'])) {
            $query->whereHas('productoAlmacen', fn($q) => $q->where('almacen_id', $filters['almacen_id']));
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('fecha', '>=', $filters['desde']);
        }

        if (!empty($filters['hasta'])) {
            $query->whereDate('fecha', '<=', $filters['hasta']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }
    }
}
