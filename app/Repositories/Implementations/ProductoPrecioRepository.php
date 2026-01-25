<?php

namespace App\Repositories\Implementations;

use App\Models\ProductoAlmacenUnidadDerivada;
use App\Models\ProductoAlmacen;
use App\Repositories\Interfaces\ProductoPrecioRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductoPrecioRepository implements ProductoPrecioRepositoryInterface
{
    /**
     * Find a price record by ID
     */
    public function findById(int $id): ?ProductoAlmacenUnidadDerivada
    {
        return ProductoAlmacenUnidadDerivada::with('unidadDerivada')->find($id);
    }

    /**
     * Get all prices for a ProductoAlmacen
     */
    public function findByProductoAlmacen(int $productoAlmacenId): Collection
    {
        return ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->with('unidadDerivada')
            ->orderBy('factor', 'desc')
            ->get();
    }

    /**
     * Get prices for a product in a specific warehouse
     */
    public function findByProductoAndAlmacen(int $productoId, int $almacenId): Collection
    {
        $productoAlmacen = ProductoAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();

        if (!$productoAlmacen) {
            return new Collection();
        }

        return $this->findByProductoAlmacen($productoAlmacen->id);
    }

    /**
     * Create a single price record
     */
    public function create(array $data): ProductoAlmacenUnidadDerivada
    {
        return ProductoAlmacenUnidadDerivada::create($data);
    }

    /**
     * Create multiple price records in batch
     */
    public function createBatch(int $productoAlmacenId, array $prices): bool
    {
        $preciosData = array_map(function ($item) use ($productoAlmacenId) {
            return [
                'producto_almacen_id' => $productoAlmacenId,
                'unidad_derivada_id' => $item['unidad_derivada_id'],
                'factor' => $item['factor'],
                'precio_publico' => $item['precio_publico'],
                'comision_publico' => $item['comision_publico'] ?? 0,
                'precio_especial' => $item['precio_especial'] ?? 0,
                'comision_especial' => $item['comision_especial'] ?? 0,
                'activador_especial' => $item['activador_especial'] ?? null,
                'precio_minimo' => $item['precio_minimo'] ?? 0,
                'comision_minimo' => $item['comision_minimo'] ?? 0,
                'activador_minimo' => $item['activador_minimo'] ?? null,
                'precio_ultimo' => $item['precio_ultimo'] ?? null,
                'comision_ultimo' => $item['comision_ultimo'] ?? 0,
                'activador_ultimo' => $item['activador_ultimo'] ?? null,
            ];
        }, $prices);

        return ProductoAlmacenUnidadDerivada::insert($preciosData);
    }

    /**
     * Update a single price record
     */
    public function update(int $id, array $data): ProductoAlmacenUnidadDerivada
    {
        $precio = ProductoAlmacenUnidadDerivada::findOrFail($id);
        $precio->update($data);

        return $precio->fresh();
    }

    /**
     * Update multiple price records in batch
     */
    public function updateBatch(int $productoAlmacenId, array $prices): bool
    {
        return DB::transaction(function () use ($productoAlmacenId, $prices) {
            foreach ($prices as $priceData) {
                if (isset($priceData['id'])) {
                    ProductoAlmacenUnidadDerivada::where('id', $priceData['id'])
                        ->where('producto_almacen_id', $productoAlmacenId)
                        ->update($priceData);
                }
            }

            return true;
        });
    }

    /**
     * Delete a single price record
     */
    public function delete(int $id): bool
    {
        $precio = ProductoAlmacenUnidadDerivada::findOrFail($id);

        return $precio->delete();
    }

    /**
     * Delete all prices for a ProductoAlmacen
     */
    public function deleteByProductoAlmacen(int $productoAlmacenId): int
    {
        return ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)->delete();
    }

    /**
     * Replace all prices for a ProductoAlmacen (delete all and create new)
     */
    public function replaceAll(int $productoAlmacenId, array $prices): bool
    {
        return DB::transaction(function () use ($productoAlmacenId, $prices) {
            $this->deleteByProductoAlmacen($productoAlmacenId);

            return $this->createBatch($productoAlmacenId, $prices);
        });
    }

    /**
     * Get price by unit derivative
     */
    public function findByUnidadDerivada(int $productoAlmacenId, int $unidadDerivadaId): ?ProductoAlmacenUnidadDerivada
    {
        return ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->where('unidad_derivada_id', $unidadDerivadaId)
            ->first();
    }

    /**
     * Get base price (highest factor, typically the base unit)
     */
    public function getBasePrice(int $productoAlmacenId): ?ProductoAlmacenUnidadDerivada
    {
        return ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->orderBy('factor', 'desc')
            ->first();
    }

    /**
     * Get products with price issues (missing prices, zero prices, etc.)
     */
    public function getProductsWithPriceIssues(int $almacenId): Collection
    {
        return ProductoAlmacen::where('almacen_id', $almacenId)
            ->where(function ($query) {
                // Products with no prices
                $query->whereDoesntHave('unidadesDerivadas')
                    // Or products with zero public price
                    ->orWhereHas('unidadesDerivadas', function ($q) {
                        $q->where('precio_publico', '<=', 0);
                    });
            })
            ->with(['producto', 'unidadesDerivadas'])
            ->get();
    }

    /**
     * Apply percentage adjustment to prices
     */
    public function applyPercentageAdjustment(int $productoAlmacenId, float $percentage, array $priceFields = ['precio_publico']): bool
    {
        $multiplier = 1 + ($percentage / 100);

        $updateData = [];
        foreach ($priceFields as $field) {
            $updateData[$field] = DB::raw("{$field} * {$multiplier}");
        }

        return ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->update($updateData) > 0;
    }

    /**
     * Get price history for a product (placeholder - requires history table)
     */
    public function getPriceHistory(int $productoAlmacenId, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        // This would require a price_history table to track changes
        // For now, return current prices as a placeholder
        return $this->findByProductoAlmacen($productoAlmacenId);
    }
}
