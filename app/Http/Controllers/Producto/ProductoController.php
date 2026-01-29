<?php

namespace App\Http\Controllers\Producto;

use App\Contracts\ProductoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producto\CreateProductoRequest;
use App\Http\Requests\Producto\IndexProductoRequest;
use App\Http\Requests\Producto\UpdateProductoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller for Product CRUD operations
 *
 * Handles:
 * - List products with filters (GET /api/productos)
 * - Create product (POST /api/productos)
 * - Show product (GET /api/productos/{id})
 * - Update product (PUT /api/productos/{id})
 * - Delete product (DELETE /api/productos/{id})
 */
class ProductoController extends Controller
{
    public function __construct(
        private ProductoServiceInterface $productoService
    ) {}

    /**
     * Display a listing of products.
     *
     * GET /api/productos
     *
     * Required: almacen_id
     * Optional filters: search, estado, categoria_id, marca_id, unidad_medida_id,
     *                   accion_tecnica, ubicacion_id, cs_stock, cs_comision,
     *                   per_page, page
     */
    public function index(IndexProductoRequest $request): JsonResponse
    {
        return $this->productoService->getAllByAlmacen($request->validated());
    }

    /**
     * Store a newly created product.
     *
     * POST /api/productos
     *
     * Creates complete product with:
     * - Product (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Prices (tabla productoalmacenunidadderivada)
     * - Optional initial stock (8 tables if has stock)
     */
    public function store(CreateProductoRequest $request): JsonResponse
    {
        return $this->productoService->create($request->validated());
    }

    /**
     * Display the specified product.
     *
     * GET /api/productos/{id}
     */
    public function show(int $id): JsonResponse
    {
        return $this->productoService->getById($id);
    }

    /**
     * Update the specified product.
     *
     * PUT /api/productos/{id}
     *
     * Updates complete product:
     * - Product (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Prices (DELETE ALL + CREATE MANY in productoalmacenunidadderivada)
     *
     * NOTE: Does NOT modify stock (compra field is ignored)
     */
    public function update(UpdateProductoRequest $request, int $id): JsonResponse
    {
        return $this->productoService->update($id, $request->validated());
    }

    /**
     * Remove the specified product.
     *
     * DELETE /api/productos/{id}
     *
     * Deletes product with validations:
     * - Verifies it has no inventory movements
     * - Verifies it has no sales
     * - Verifies it has no more than 1 purchase
     * - If has 1 purchase, verifies it's initial stock
     * - Deletes the initial stock purchase
     * - Deletes the product (cascade deletes producto_almacen and prices)
     */
    public function destroy(int $id): JsonResponse
    {
        return $this->productoService->delete($id);
    }
}
