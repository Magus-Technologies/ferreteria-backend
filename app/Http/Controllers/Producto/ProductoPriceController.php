<?php

namespace App\Http\Controllers\Producto;

use App\Contracts\ProductoPriceServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Product Price operations
 *
 * Handles:
 * - Get price details (GET /api/productos/{id}/detalle-precios)
 * - Update prices (PUT /api/productos/{id}/precios)
 * - Bulk price update (POST /api/productos/precios/bulk-update)
 */
class ProductoPriceController extends Controller
{
    public function __construct(
        private ProductoPriceServiceInterface $priceService
    ) {}

    /**
     * Get price details for a specific product in a warehouse.
     *
     * GET /api/productos/{id}/detalle-precios?almacen_id={id}
     *
     * Returns complete price information including:
     * - All unit derivatives with prices
     * - Cost information
     * - Margins and commissions
     * - Comparison across warehouses (if applicable)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        return $this->priceService->getProductPriceDetails($id, $validated['almacen_id']);
    }

    /**
     * Update prices for a specific product in a warehouse.
     *
     * PUT /api/productos/{id}/precios
     *
     * Body:
     * {
     *   "almacen_id": 1,
     *   "precios": [
     *     {
     *       "unidad_derivada_id": 1,
     *       "precio_publico": 100.00,
     *       "precio_especial": 90.00,
     *       "precio_minimo": 80.00,
     *       ...
     *     }
     *   ]
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'almacen_id' => 'required|integer|exists:almacen,id',
            'precios' => 'required|array|min:1',
            'precios.*.unidad_derivada_id' => 'required|exists:unidadderivada,id',
            'precios.*.precio_publico' => 'required|numeric|min:0',
            'precios.*.comision_publico' => 'nullable|numeric',
            'precios.*.precio_especial' => 'nullable|numeric',
            'precios.*.comision_especial' => 'nullable|numeric',
            'precios.*.activador_especial' => 'nullable|numeric',
            'precios.*.precio_minimo' => 'nullable|numeric',
            'precios.*.comision_minimo' => 'nullable|numeric',
            'precios.*.activador_minimo' => 'nullable|numeric',
            'precios.*.precio_ultimo' => 'nullable|numeric',
            'precios.*.comision_ultimo' => 'nullable|numeric',
            'precios.*.activador_ultimo' => 'nullable|numeric',
        ]);

        return $this->priceService->updateProductPrices($id, $validated['almacen_id'], $validated['precios']);
    }

    /**
     * Bulk update prices for multiple products.
     *
     * POST /api/productos/precios/bulk-update
     *
     * Body:
     * {
     *   "almacen_id": 1,
     *   "ajuste_tipo": "porcentaje" | "valor",
     *   "ajuste_valor": 10,
     *   "campos": ["precio_publico", "precio_especial"],
     *   "producto_ids": [1, 2, 3]  // Optional, if empty applies to all
     * }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'almacen_id' => 'required|integer|exists:almacen,id',
            'ajuste_tipo' => 'required|in:porcentaje,valor',
            'ajuste_valor' => 'required|numeric',
            'campos' => 'required|array|min:1',
            'campos.*' => 'required|in:precio_publico,precio_especial,precio_minimo,precio_ultimo',
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'integer|exists:producto,id',
        ]);

        return $this->priceService->bulkUpdatePrices($validated);
    }
}
