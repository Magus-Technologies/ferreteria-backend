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
    public function findByAlmacen(?int $almacenId, array $filters = [], int $perPage = 100): LengthAwarePaginator;

    /**
     * Listado LIGERO de productos por almacén (sin compras, sin
     * productoComplementario, sin tiene_ingresos). Para el modal de búsqueda
     * que carga todo de una.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findListadoLigeroByAlmacen(int $almacenId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Listado COMPLETO de productos por almacén para la vista "Mi Almacén",
     * devuelto como array PHP plano (mismo shape que Eloquent->toArray()) y
     * construido con Query Builder para evitar la sobrecarga de serializar
     * miles de modelos Eloquent. Ver implementación para detalles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findListadoCompletoArrayByAlmacen(int $almacenId): array;

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

    /**
     * Get products with batches nearing expiration
     */
    public function getVencimientos(int $almacenId, int $dias): \Illuminate\Support\Collection;
}
