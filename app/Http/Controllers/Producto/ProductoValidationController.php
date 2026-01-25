<?php

namespace App\Http\Controllers\Producto;

use App\Contracts\ProductoValidationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Product Validation operations
 *
 * Handles:
 * - Validate product code (GET /api/productos/validar-codigo)
 * - Validate barcode (GET /api/productos/validar-codigo-barra)
 * - Validate product name (GET /api/productos/validar-nombre)
 */
class ProductoValidationController extends Controller
{
    public function __construct(
        private ProductoValidationServiceInterface $validationService
    ) {}

    /**
     * Validate if a product code already exists.
     *
     * GET /api/productos/validar-codigo?cod_producto=ABC123&exclude_id=123
     *
     * Returns:
     * - exists: true if code already exists
     * - message: Validation message
     * - producto: Product data if exists (optional)
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cod_producto' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);

        return $this->validationService->validateProductCode(
            $validated['cod_producto'],
            $validated['exclude_id'] ?? null
        );
    }

    /**
     * Validate if a barcode already exists.
     *
     * GET /api/productos/validar-codigo-barra?cod_barra=7501234567890&exclude_id=123
     *
     * Returns:
     * - exists: true if barcode already exists
     * - message: Validation message
     * - producto: Product data if exists (optional)
     */
    public function validateBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cod_barra' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);

        return $this->validationService->validateBarcode(
            $validated['cod_barra'],
            $validated['exclude_id'] ?? null
        );
    }

    /**
     * Validate if a product name already exists.
     *
     * GET /api/productos/validar-nombre?name=ProductoXYZ&exclude_id=123
     *
     * Returns:
     * - exists: true if name already exists
     * - message: Validation message
     * - producto: Product data if exists (optional)
     */
    public function validateName(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);

        return $this->validationService->validateProductName(
            $validated['name'],
            $validated['exclude_id'] ?? null
        );
    }
}
