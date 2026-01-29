<?php

namespace App\Repositories\Interfaces;

use App\Models\ProductoAlmacen;
use Illuminate\Database\Eloquent\Collection;

interface ProductoAlmacenRepositoryInterface
{
    /**
     * Find a ProductoAlmacen by ID
     */
    public function findById(int $id, array $relations = []): ?ProductoAlmacen;

    /**
     * Find by product ID and warehouse ID
     */
    public function findByProductoAndAlmacen(int $productoId, int $almacenId): ?ProductoAlmacen;

    /**
     * Get all ProductoAlmacen for a product
     */
    public function findByProducto(int $productoId): Collection;

    /**
     * Get all ProductoAlmacen for a warehouse
     */
    public function findByAlmacen(int $almacenId): Collection;

    /**
     * Create a new ProductoAlmacen
     */
    public function create(array $data): ProductoAlmacen;

    /**
     * Update an existing ProductoAlmacen
     */
    public function update(int $id, array $data): ProductoAlmacen;

    /**
     * Update by product and warehouse
     */
    public function updateByProductoAndAlmacen(int $productoId, int $almacenId, array $data): ProductoAlmacen;

    /**
     * Delete a ProductoAlmacen
     */
    public function delete(int $id): bool;

    /**
     * Get current stock for a product in a warehouse
     */
    public function getStock(int $productoId, int $almacenId): float;

    /**
     * Update stock for a product in a warehouse
     */
    public function updateStock(int $productoId, int $almacenId, float $stock): bool;

    /**
     * Increment stock
     */
    public function incrementStock(int $productoId, int $almacenId, float $amount): bool;

    /**
     * Decrement stock
     */
    public function decrementStock(int $productoId, int $almacenId, float $amount): bool;

    /**
     * Get products with low stock in a warehouse
     */
    public function getProductsWithLowStock(int $almacenId): Collection;

    /**
     * Get products with zero stock in a warehouse
     */
    public function getProductsWithZeroStock(int $almacenId): Collection;

    /**
     * Check if product exists in warehouse
     */
    public function existsInAlmacen(int $productoId, int $almacenId): bool;
}
