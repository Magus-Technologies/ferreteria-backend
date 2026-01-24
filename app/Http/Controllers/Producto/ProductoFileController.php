<?php

namespace App\Http\Controllers\Producto;

use App\Contracts\ProductoFileServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Product File operations
 *
 * Handles:
 * - Upload files for a product (POST /api/productos/{id}/upload-files)
 * - Upload multiple files for multiple products (POST /api/productos/upload-files-masivo)
 */
class ProductoFileController extends Controller
{
    public function __construct(
        private ProductoFileServiceInterface $fileService
    ) {}

    /**
     * Upload files for a specific product.
     *
     * POST /api/productos/{id}/upload-files
     *
     * Body (multipart/form-data):
     * - img: Image file (jpg, jpeg, png, gif, webp) - max 5MB
     * - ficha_tecnica: Technical sheet file (pdf, doc, docx) - max 10MB
     *
     * Returns updated product with file paths.
     */
    public function upload(Request $request, int $id): JsonResponse
    {
        return $this->fileService->uploadProductFiles($id, $request);
    }

    /**
     * Upload multiple files for multiple products at once.
     *
     * POST /api/productos/upload-files-masivo
     *
     * Body (multipart/form-data):
     * - files: Array of files (max 50 files, 10MB each)
     * - tipo: 'img' | 'ficha_tecnica'
     *
     * Files are matched to products by filename (cod_producto).
     * Example: file named "17337.jpg" will be assigned to product with cod_producto "17337"
     *
     * Response:
     * {
     *   "data": {
     *     "uploaded": ["17337", "17338"],  // Successfully uploaded
     *     "not_found": ["99999"]            // Products not found
     *   }
     * }
     */
    public function uploadMasivo(Request $request): JsonResponse
    {
        return $this->fileService->uploadMultipleProductFiles($request);
    }
}
