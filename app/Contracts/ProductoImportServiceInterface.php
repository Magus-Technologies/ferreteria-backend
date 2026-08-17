<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;

interface ProductoImportServiceInterface
{
    /**
     * Import products from Excel data in batches
     *
     * @param array $data Array of product data from Excel
     * @return JsonResponse
     */
    public function importFromExcel(array $data): JsonResponse;

    /**
     * Update existing products from Excel data.
     *
     * Each row must identify the product by cod_producto (or cod_barra) and
     * only the fields present in the row are updated (partial update).
     *
     * @param array $data Array of product data from Excel
     * @return JsonResponse
     */
    public function updateFromExcel(array $data): JsonResponse;

    /**
     * Get import progress status
     *
     * @param string $importId Import job ID
     * @return JsonResponse
     */
    public function getImportProgress(string $importId): JsonResponse;

    /**
     * Cancel an ongoing import process
     *
     * @param string $importId Import job ID
     * @return JsonResponse
     */
    public function cancelImport(string $importId): JsonResponse;

    /**
     * Validate Excel data before importing
     *
     * @param array $data Array of product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateImportData(array $data): array;

    /**
     * Pre-load and cache catalog data for import optimization
     *
     * @param array $data Array of product data to analyze
     * @return array Cached catalog data (categories, brands, units)
     */
    public function prepareCatalogCache(array $data): array;
}
