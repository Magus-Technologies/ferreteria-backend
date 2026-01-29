<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ProductoFileServiceInterface
{
    /**
     * Upload files for a single product (image and/or technical sheet)
     *
     * @param int $productId Product ID
     * @param Request $request Request with file uploads
     * @return JsonResponse
     */
    public function uploadProductFiles(int $productId, Request $request): JsonResponse;

    /**
     * Upload multiple files for multiple products at once
     *
     * @param Request $request Request with multiple files and product mapping
     * @return JsonResponse
     */
    public function uploadMultipleProductFiles(Request $request): JsonResponse;

    /**
     * Delete a specific file from a product
     *
     * @param int $productId Product ID
     * @param string $fileType Type of file ('img' or 'ficha_tecnica')
     * @return JsonResponse
     */
    public function deleteProductFile(int $productId, string $fileType): JsonResponse;

    /**
     * Get file information and URLs for a product
     *
     * @param int $productId Product ID
     * @return JsonResponse
     */
    public function getProductFiles(int $productId): JsonResponse;

    /**
     * Validate file upload request
     *
     * @param Request $request Request to validate
     * @param string $type Type of validation ('single' or 'multiple')
     * @return array Validation rules
     */
    public function getFileValidationRules(string $type = 'single'): array;

    /**
     * Clean up orphaned files (files that don't belong to any product)
     *
     * @return JsonResponse
     */
    public function cleanupOrphanedFiles(): JsonResponse;
}
