<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ProductoServiceInterface
{
    /**
     * Get paginated list of products with filters and relations
     *
     * @param array $filters Filters for the query (almacen_id is required)
     * @return JsonResponse
     */
    public function getAllByAlmacen(array $filters): JsonResponse;

    /**
     * Get a single product by ID with all its relations
     *
     * @param int $id Product ID
     * @return JsonResponse
     */
    public function getById(int $id): JsonResponse;

    /**
     * Create a new product with all related data
     *
     * @param array $data Validated product data
     * @return JsonResponse
     */
    public function create(array $data): JsonResponse;

    /**
     * Update an existing product with all related data
     *
     * @param int $id Product ID
     * @param array $data Validated product data
     * @return JsonResponse
     */
    public function update(int $id, array $data): JsonResponse;

    /**
     * Delete a product with proper validations
     *
     * @param int $id Product ID
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse;
}
