<?php

namespace App\Repositories\Interfaces;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductoRepositoryInterface
{
    /**
     * Find a product by ID with optional relations
     */
    public function findById(int $id, array $relations = []): ?Producto;

    /**
     * Find a product by code
     */
    public function findByCode(string $code): ?Producto;

    /**
     * Find a product by barcode
     */
    public function findByBarcode(string $barcode): ?Producto;

    /**
     * Get paginated products by warehouse with filters
     */
    public function findByAlmacen(int $almacenId, array $filters = [], int $perPage = 100): LengthAwarePaginator;

    /**
     * Get all products (no pagination)
     */
    public function getAll(array $relations = []): Collection;

    /**
     * Create a new product
     */
    public function create(array $data): Producto;

    /**
     * Update an existing product
     */
    public function update(int $id, array $data): Producto;

    /**
     * Delete a product
     */
    public function delete(int $id): bool;

    /**
     * Check if a product exists by field
     */
    public function exists(string $field, $value, ?int $excludeId = null): bool;

    /**
     * Check if product has inventory movements (ingresos/salidas)
     */
    public function hasMovements(int $id): bool;

    /**
     * Check if product has sales
     */
    public function hasSales(int $id): bool;

    /**
     * Check if product has purchases
     */
    public function hasPurchases(int $id): bool;

    /**
     * Get the count of purchases for a product
     */
    public function getPurchasesCount(int $id): int;

    /**
     * Get the first purchase for a product
     */
    public function getFirstPurchase(int $id): ?object;

    /**
     * Generate the next product code
     */
    public function generateNextCode(): string;

    /**
     * Search products by term (name, code, barcode)
     */
    public function search(string $term, int $almacenId, int $limit = 20): Collection;
}
