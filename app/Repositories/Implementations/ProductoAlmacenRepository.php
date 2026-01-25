<?php

namespace App\Repositories\Implementations;

use App\Models\ProductoAlmacen;
use App\Models\Producto;
use App\Repositories\Interfaces\ProductoAlmacenRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductoAlmacenRepository implements ProductoAlmacenRepositoryInterface
{
    /**
     * Find a ProductoAlmacen by ID
     */
    public function findById(int $id, array $relations = []): ?ProductoAlmacen
    {
        $query = ProductoAlmacen::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Find by product ID and warehouse ID
     */
    public function findByProductoAndAlmacen(int $productoId, int $almacenId): ?ProductoAlmacen
    {
        return ProductoAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();
    }

    /**
     * Get all ProductoAlmacen for a product
     */
    public function findByProducto(int $productoId): Collection
    {
        return ProductoAlmacen::where('producto_id', $productoId)
            ->with(['almacen', 'ubicacion', 'unidadesDerivadas'])
            ->get();
    }

    /**
     * Get all ProductoAlmacen for a warehouse
     */
    public function findByAlmacen(int $almacenId): Collection
    {
        return ProductoAlmacen::where('almacen_id', $almacenId)
            ->with(['producto', 'ubicacion', 'unidadesDerivadas'])
            ->get();
    }

    /**
     * Create a new ProductoAlmacen
     */
    public function create(array $data): ProductoAlmacen
    {
        return ProductoAlmacen::create($data);
    }

    /**
     * Update an existing ProductoAlmacen
     */
    public function update(int $id, array $data): ProductoAlmacen
    {
        $productoAlmacen = ProductoAlmacen::findOrFail($id);
        $productoAlmacen->update($data);

        return $productoAlmacen->fresh();
    }

    /**
     * Update by product and warehouse
     */
    public function updateByProductoAndAlmacen(int $productoId, int $almacenId, array $data): ProductoAlmacen
    {
        $productoAlmacen = ProductoAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->firstOrFail();

        $productoAlmacen->update($data);

        return $productoAlmacen->fresh();
    }

    /**
     * Delete a ProductoAlmacen
     */
    public function delete(int $id): bool
    {
        $productoAlmacen = ProductoAlmacen::findOrFail($id);

        return $productoAlmacen->delete();
    }

    /**
     * Get current stock for a product in a warehouse
     */
    public function getStock(int $productoId, int $almacenId): float
    {
        $productoAlmacen = $this->findByProductoAndAlmacen($productoId, $almacenId);

        return $productoAlmacen ? (float) $productoAlmacen->stock_fraccion : 0;
    }

    /**
     * Update stock for a product in a warehouse
     */
    public function updateStock(int $productoId, int $almacenId, float $stock): bool
    {
        return ProductoAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->update(['stock_fraccion' => $stock]) > 0;
    }

    /**
     * Increment stock
     */
    public function incrementStock(int $productoId, int $almacenId, float $amount): bool
    {
        $productoAlmacen = $this->findByProductoAndAlmacen($productoId, $almacenId);

        if (!$productoAlmacen) {
            return false;
        }

        $productoAlmacen->increment('stock_fraccion', $amount);

        return true;
    }

    /**
     * Decrement stock
     */
    public function decrementStock(int $productoId, int $almacenId, float $amount): bool
    {
        $productoAlmacen = $this->findByProductoAndAlmacen($productoId, $almacenId);

        if (!$productoAlmacen) {
            return false;
        }

        $productoAlmacen->decrement('stock_fraccion', $amount);

        return true;
    }

    /**
     * Get products with low stock in a warehouse
     */
    public function getProductsWithLowStock(int $almacenId): Collection
    {
        return ProductoAlmacen::where('almacen_id', $almacenId)
            ->whereHas('producto', function ($q) {
                $q->whereColumn('productoalmacen.stock_fraccion', '<=', 'productos.stock_min');
            })
            ->with(['producto', 'ubicacion'])
            ->get();
    }

    /**
     * Get products with zero stock in a warehouse
     */
    public function getProductsWithZeroStock(int $almacenId): Collection
    {
        return ProductoAlmacen::where('almacen_id', $almacenId)
            ->where('stock_fraccion', '<=', 0)
            ->with(['producto', 'ubicacion'])
            ->get();
    }

    /**
     * Check if product exists in warehouse
     */
    public function existsInAlmacen(int $productoId, int $almacenId): bool
    {
        return ProductoAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->exists();
    }
}
