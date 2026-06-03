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
     * Listado LIGERO de productos por almacén para el modal de búsqueda.
     *
     * Diseñado para que el cliente NO pagine y vea todos los productos de una.
     * Carga solo las relaciones necesarias para la grilla (sin `compras`,
     * sin `productoComplementario`, sin `tiene_ingresos`).
     *
     * Devuelve un array plano de productos ordenados por nombre.
     * Cache: 10 minutos por almacén.
     *
     * @return JsonResponse { data: Producto[] }
     */
    public function getListadoLigeroPorAlmacen(int $almacenId): JsonResponse;

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

    /**
     * Get products with batches nearing expiration
     *
     * @param array $filters
     * @return JsonResponse
     */
    public function getVencimientos(array $filters): JsonResponse;
}
