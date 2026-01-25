<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;

interface ProductoValidationServiceInterface
{
    /**
     * Validate if a product code already exists
     *
     * @param string $codProducto Product code to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateProductCode(string $codProducto, ?int $excludeId = null): JsonResponse;

    /**
     * Validate if a barcode already exists
     *
     * @param string $codBarra Barcode to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateBarcode(string $codBarra, ?int $excludeId = null): JsonResponse;

    /**
     * Validate if a product name already exists
     *
     * @param string $name Product name to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateProductName(string $name, ?int $excludeId = null): JsonResponse;

    /**
     * Validate product data before creation
     *
     * @param array $data Product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateProductCreation(array $data): array;

    /**
     * Validate product data before update
     *
     * @param int $productId Product ID being updated
     * @param array $data Product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateProductUpdate(int $productId, array $data): array;

    /**
     * Validate if a product can be deleted
     *
     * @param int $productId Product ID to validate for deletion
     * @return array Validation results with blocking issues
     */
    public function validateProductDeletion(int $productId): array;

    /**
     * Validate stock levels and business rules
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param array $stockData Stock data to validate
     * @return array Validation results
     */
    public function validateStockLevels(int $productId, int $almacenId, array $stockData): array;

    /**
     * Check if required catalog data exists (categories, brands, units)
     *
     * @param array $catalogData Catalog IDs to validate
     * @return array Validation results with missing items
     */
    public function validateCatalogData(array $catalogData): array;
}
