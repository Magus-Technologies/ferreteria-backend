<?php

namespace App\Http\Controllers\Producto;

use App\Contracts\ProductoImportServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producto\ImportProductoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Controller for Product Import operations
 *
 * Handles:
 * - Import products from Excel (POST /api/productos/import)
 * - Get import progress (GET /api/productos/import-progress/{importId})
 * - Cancel import (POST /api/productos/import-cancel/{importId})
 * - Get import results (GET /api/productos/import-results/{importId})
 */
class ProductoImportController extends Controller
{
    public function __construct(
        private ProductoImportServiceInterface $importService
    ) {}

    /**
     * Import products from Excel data.
     *
     * POST /api/productos/import
     *
     * Imports products asynchronously in batches:
     * - Creates producto + producto_almacen
     * - Adjusts cost by dividing by unidades_contenidas
     * - Returns import_id for tracking progress
     * - Returns duplicates (products that already exist)
     *
     * @return JsonResponse HTTP 202 with import_id for async tracking
     */
    public function import(ImportProductoRequest $request): JsonResponse
    {
        return $this->importService->importFromExcel($request->validated()['data']);
    }

    /**
     * Get import progress status.
     *
     * GET /api/productos/import-progress/{importId}
     *
     * Returns:
     * - status: processing | completed | failed | cancelled
     * - progress: 0-100
     * - message: Current status message
     * - total_products: Total products to import
     * - processed: Products processed so far
     * - imported: Successfully imported
     * - duplicates: Skipped duplicates
     * - errors: Failed imports
     * - started_at: Import start time
     * - estimated_remaining: Estimated time remaining (seconds)
     */
    public function progress(string $importId): JsonResponse
    {
        return $this->importService->getImportProgress($importId);
    }

    /**
     * Cancel an ongoing import process.
     *
     * POST /api/productos/import-cancel/{importId}
     *
     * Sets the import status to 'cancelled'.
     * The import job will check this status and stop processing.
     */
    public function cancel(string $importId): JsonResponse
    {
        return $this->importService->cancelImport($importId);
    }

    /**
     * Get final import results.
     *
     * GET /api/productos/import-results/{importId}
     *
     * Returns complete import summary with:
     * - Final statistics
     * - List of duplicates
     * - List of errors
     * - Duration
     */
    public function results(string $importId): JsonResponse
    {
        return $this->importService->getImportResults($importId);
    }
}
