<?php

namespace App\Repositories\Interfaces;

interface KardexRepositoryInterface
{
    /**
     * Get paginated kardex data with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 50, int $page = 1): array;

    /**
     * Create a new kardex record
     */
    public function create(array $data): mixed;

    /**
     * Get total count with filters
     */
    public function getTotal(array $filters = []): int;

    /**
     * Get stock information for a product
     */
    public function getStockInfo(?int $productoId, ?int $almacenId): array;

    /**
     * Get period totals (entradas/salidas) for products
     */
    public function getPeriodTotals(array $filters = []): array;
}
