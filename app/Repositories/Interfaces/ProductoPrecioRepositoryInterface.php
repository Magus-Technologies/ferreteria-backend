<?php

namespace App\Repositories\Interfaces;

use App\Models\ProductoAlmacenUnidadDerivada;
use Illuminate\Database\Eloquent\Collection;

interface ProductoPrecioRepositoryInterface
{
    /**
     * Find a price record by ID
     */
    public function findById(int $id): ?ProductoAlmacenUnidadDerivada;

    /**
     * Get all prices for a ProductoAlmacen
     */
    public function findByProductoAlmacen(int $productoAlmacenId): Collection;

    /**
     * Get prices for a product in a specific warehouse
     */
    public function findByProductoAndAlmacen(int $productoId, int $almacenId): Collection;

    /**
     * Create a single price record
     */
    public function create(array $data): ProductoAlmacenUnidadDerivada;

    /**
     * Create multiple price records in batch
     */
    public function createBatch(int $productoAlmacenId, array $prices): bool;

    /**
     * Update a single price record
     */
    public function update(int $id, array $data): ProductoAlmacenUnidadDerivada;

    /**
     * Update multiple price records in batch
     */
    public function updateBatch(int $productoAlmacenId, array $prices): bool;

    /**
     * Delete a single price record
     */
    public function delete(int $id): bool;

    /**
     * Delete all prices for a ProductoAlmacen
     */
    public function deleteByProductoAlmacen(int $productoAlmacenId): int;

    /**
     * Replace all prices for a ProductoAlmacen (delete all and create new)
     */
    public function replaceAll(int $productoAlmacenId, array $prices): bool;

    /**
     * Get price by unit derivative
     */
    public function findByUnidadDerivada(int $productoAlmacenId, int $unidadDerivadaId): ?ProductoAlmacenUnidadDerivada;

    /**
     * Get base price (factor = unidades_contenidas of product)
     */
    public function getBasePrice(int $productoAlmacenId): ?ProductoAlmacenUnidadDerivada;

    /**
     * Get products with price issues (missing prices, zero prices, etc.)
     */
    public function getProductsWithPriceIssues(int $almacenId): Collection;

    /**
     * Apply percentage adjustment to prices
     */
    public function applyPercentageAdjustment(int $productoAlmacenId, float $percentage, array $priceFields = ['precio_publico']): bool;

    /**
     * Get price history for a product (if tracking is enabled)
     */
    public function getPriceHistory(int $productoAlmacenId, ?string $fromDate = null, ?string $toDate = null): Collection;
}
