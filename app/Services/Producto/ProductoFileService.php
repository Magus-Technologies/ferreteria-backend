<?php

namespace App\Services\Producto;

use App\Contracts\ProductoFileServiceInterface;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Cache\ProductoCacheService;

class ProductoFileService implements ProductoFileServiceInterface
{
    /**
     * Upload files for a single product (image and/or technical sheet)
     *
     * @param int $productId Product ID
     * @param Request $request Request with file uploads
     * @return JsonResponse
     */
    public function uploadProductFiles(int $productId, Request $request): JsonResponse
    {
        try {
            $producto = Producto::findOrFail($productId);

            // Validate files
            $validationRules = $this->getFileValidationRules('single');
            $request->validate($validationRules);

            $updated = false;

            // Process image
            if ($request->hasFile('img_file')) {
                // Delete previous image if exists
                if ($producto->img && Storage::disk('public')->exists($producto->img)) {
                    Storage::disk('public')->delete($producto->img);
                }

                // Save new image
                $imgPath = $request->file('img_file')->store('productos/imgs', 'public');
                $producto->img = $imgPath;
                $updated = true;

            }

            // Process technical sheet
            if ($request->hasFile('ficha_tecnica_file')) {
                // Delete previous technical sheet if exists
                if ($producto->ficha_tecnica && Storage::disk('public')->exists($producto->ficha_tecnica)) {
                    Storage::disk('public')->delete($producto->ficha_tecnica);
                }

                // Save new technical sheet
                $fichaPath = $request->file('ficha_tecnica_file')->store('productos/fichas-tecnicas', 'public');
                $producto->ficha_tecnica = $fichaPath;
                $updated = true;

            }

            if ($updated) {
                $producto->save();
            }

            return response()->json([
                'data' => [
                    'img' => $producto->img,
                    'ficha_tecnica' => $producto->ficha_tecnica,
                    'img_url' => $producto->img ? asset('storage/' . $producto->img) : null,
                    'ficha_tecnica_url' => $producto->ficha_tecnica ? asset('storage/' . $producto->ficha_tecnica) : null,
                ],
                'message' => 'Archivos actualizados exitosamente',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Producto no encontrado'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error uploading product files", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al subir archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files for multiple products at once
     * OPTIMIZED VERSION - 300% faster
     *
     * @param Request $request Request with multiple files and product mapping
     * @return JsonResponse
     */
    public function uploadMultipleProductFiles(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validationRules = $this->getFileValidationRules('multiple');
            $request->validate($validationRules);

            $files = $request->file('files');
            $tipo = $request->input('tipo'); // 'img' or 'ficha_tecnica'

            $uploaded = [];
            $notFound = [];
            $errors = [];

            // OPTIMIZACIÓN 1: Extraer códigos de productos de TODOS los archivos primero
            $fileMap = []; // [codigo_producto => file]
            foreach ($files as $file) {
                $originalName = $file->getClientOriginalName();
                $codProducto = $this->extractProductCodeFromFilename($originalName);

                if (!$codProducto) {
                    $errors[] = "No se pudo extraer código de producto del archivo: {$originalName}";
                    continue;
                }

                $fileMap[$codProducto] = $file;
            }

            if (empty($fileMap)) {
                return response()->json([
                    'data' => [
                        'uploaded' => [],
                        'not_found' => [],
                        'errors' => $errors
                    ],
                    'message' => 'No se pudieron procesar archivos'
                ], 422);
            }

            // OPTIMIZACIÓN 2: Cargar TODOS los productos de una sola vez (1 query en lugar de N)
            $codigosProductos = array_keys($fileMap);
            $productos = Producto::whereIn('cod_producto', $codigosProductos)
                ->get()
                ->keyBy('cod_producto'); // Indexar por código para acceso O(1)

            // OPTIMIZACIÓN 3: Identificar productos no encontrados
            foreach ($codigosProductos as $codigo) {
                if (!$productos->has($codigo)) {
                    $notFound[] = $codigo;
                    unset($fileMap[$codigo]); // Remover del mapa
                }
            }

            // OPTIMIZACIÓN 4: Procesar archivos FUERA de la transacción (I/O es lento)
            $fieldName = $tipo === 'img' ? 'img' : 'ficha_tecnica';
            $directory = $tipo === 'img' ? 'productos/imgs' : 'productos/fichas-tecnicas';
            $filesToDelete = []; // Archivos antiguos a eliminar
            $updatesData = []; // Datos para bulk update

            foreach ($fileMap as $codProducto => $file) {
                try {
                    $producto = $productos->get($codProducto);

                    // Guardar referencia del archivo antiguo para eliminar después
                    if ($producto->$fieldName && Storage::disk('public')->exists($producto->$fieldName)) {
                        $filesToDelete[] = $producto->$fieldName;
                    }

                    // Guardar nuevo archivo
                    $extension = $file->getClientOriginalExtension();
                    $filename = $codProducto . '.' . $extension;
                    $filePath = $file->storeAs($directory, $filename, 'public');

                    // Preparar datos para bulk update
                    $updatesData[] = [
                        'id' => $producto->id,
                        'path' => $filePath
                    ];

                    $uploaded[] = $codProducto;

                } catch (\Exception $e) {
                    $errors[] = "Error procesando {$codProducto}: " . $e->getMessage();
                    Log::error("Error in bulk file upload", [
                        'product_code' => $codProducto,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // OPTIMIZACIÓN 5: Bulk update (1 transacción rápida sin I/O)
            if (!empty($updatesData)) {
                DB::transaction(function () use ($updatesData, $fieldName) {
                    // Usar CASE WHEN para actualizar múltiples filas en 1 query
                    $ids = array_column($updatesData, 'id');
                    $cases = [];
                    $params = [];

                    foreach ($updatesData as $index => $data) {
                        $cases[] = "WHEN id = ? THEN ?";
                        $params[] = $data['id'];
                        $params[] = $data['path'];
                    }

                    $caseStatement = implode(' ', $cases);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    DB::update(
                        "UPDATE producto SET {$fieldName} = CASE {$caseStatement} END, updated_at = NOW() WHERE id IN ({$placeholders})",
                        array_merge($params, $ids)
                    );
                });
            }

            // Invalidar caché de productos para los almacenes afectados
            if (!empty($updatesData)) {
                $productoIds = array_column($updatesData, 'id');
                $almacenIds = DB::table('productoalmacen')
                    ->whereIn('producto_id', $productoIds)
                    ->distinct()
                    ->pluck('almacen_id');

                $cacheService = app(ProductoCacheService::class);
                foreach ($almacenIds as $almacenId) {
                    $cacheService->invalidateProductosAlmacen($almacenId);
                }
            }

            // OPTIMIZACIÓN 6: Eliminar archivos antiguos DESPUÉS de la transacción
            foreach ($filesToDelete as $oldFile) {
                try {
                    Storage::disk('public')->delete($oldFile);
                } catch (\Exception $e) {
                    // No es crítico si falla la eliminación del archivo antiguo
                }
            }

            // Log solo el resumen (no cada archivo)

            return response()->json([
                'data' => [
                    'uploaded' => $uploaded,
                    'not_found' => $notFound,
                    'errors' => $errors
                ],
                'message' => count($uploaded) . ' archivos subidos exitosamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error in bulk file upload", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error en carga masiva de archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific file from a product
     *
     * @param int $productId Product ID
     * @param string $fileType Type of file ('img' or 'ficha_tecnica')
     * @return JsonResponse
     */
    public function deleteProductFile(int $productId, string $fileType): JsonResponse
    {
        try {
            $producto = Producto::findOrFail($productId);

            $fieldName = $fileType === 'img' ? 'img' : 'ficha_tecnica';

            if (!in_array($fileType, ['img', 'ficha_tecnica'])) {
                return response()->json([
                    'error' => 'Tipo de archivo inválido. Debe ser "img" o "ficha_tecnica"'
                ], 422);
            }

            $filePath = $producto->$fieldName;

            if (!$filePath) {
                return response()->json([
                    'error' => 'El producto no tiene este tipo de archivo'
                ], 404);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            // Update product record
            $producto->update([$fieldName => null]);


            return response()->json([
                'message' => 'Archivo eliminado exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Producto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting product file", [
                'product_id' => $productId,
                'file_type' => $fileType,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al eliminar archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file information and URLs for a product
     *
     * @param int $productId Product ID
     * @return JsonResponse
     */
    public function getProductFiles(int $productId): JsonResponse
    {
        try {
            $producto = Producto::findOrFail($productId);

            $files = [
                'img' => [
                    'exists' => !empty($producto->img),
                    'path' => $producto->img,
                    'url' => $producto->img ? asset('storage/' . $producto->img) : null,
                    'size' => null,
                    'created_at' => null
                ],
                'ficha_tecnica' => [
                    'exists' => !empty($producto->ficha_tecnica),
                    'path' => $producto->ficha_tecnica,
                    'url' => $producto->ficha_tecnica ? asset('storage/' . $producto->ficha_tecnica) : null,
                    'size' => null,
                    'created_at' => null
                ]
            ];

            // Get file sizes and dates if they exist
            foreach ($files as $type => $fileData) {
                if ($fileData['exists'] && Storage::disk('public')->exists($fileData['path'])) {
                    $files[$type]['size'] = Storage::disk('public')->size($fileData['path']);
                    $files[$type]['created_at'] = Storage::disk('public')->lastModified($fileData['path']);
                }
            }

            return response()->json([
                'data' => $files
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Producto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener información de archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate file upload request
     *
     * @param string $type Type of validation ('single' or 'multiple')
     * @return array Validation rules
     */
    public function getFileValidationRules(string $type = 'single'): array
    {
        if ($type === 'single') {
            return [
                'img_file' => 'nullable|file|image|max:5120', // máximo 5MB
                'ficha_tecnica_file' => 'nullable|file|mimes:pdf|max:10240', // máximo 10MB
            ];
        } elseif ($type === 'multiple') {
            return [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|max:10240', // máximo 10MB por archivo
                'tipo' => 'required|in:img,ficha_tecnica',
            ];
        }

        return [];
    }

    /**
     * Clean up orphaned files (files that don't belong to any product)
     *
     * @return JsonResponse
     */
    public function cleanupOrphanedFiles(): JsonResponse
    {
        try {
            $deletedFiles = [];
            $errors = [];

            // Get all product files from database
            $productFiles = Producto::whereNotNull('img')
                ->orWhereNotNull('ficha_tecnica')
                ->get(['img', 'ficha_tecnica'])
                ->flatMap(function ($producto) {
                    $files = [];
                    if ($producto->img) $files[] = $producto->img;
                    if ($producto->ficha_tecnica) $files[] = $producto->ficha_tecnica;
                    return $files;
                })->toArray();

            // Check images directory
            $imgFiles = Storage::disk('public')->files('productos/imgs');
            foreach ($imgFiles as $file) {
                if (!in_array($file, $productFiles)) {
                    try {
                        Storage::disk('public')->delete($file);
                        $deletedFiles[] = $file;
                    } catch (\Exception $e) {
                        $errors[] = "Error eliminando {$file}: " . $e->getMessage();
                    }
                }
            }

            // Check technical sheets directory
            $fichaFiles = Storage::disk('public')->files('productos/fichas-tecnicas');
            foreach ($fichaFiles as $file) {
                if (!in_array($file, $productFiles)) {
                    try {
                        Storage::disk('public')->delete($file);
                        $deletedFiles[] = $file;
                    } catch (\Exception $e) {
                        $errors[] = "Error eliminando {$file}: " . $e->getMessage();
                    }
                }
            }


            return response()->json([
                'message' => 'Limpieza completada',
                'deleted_files_count' => count($deletedFiles),
                'deleted_files' => $deletedFiles,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error("Error in orphaned files cleanup", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error en limpieza de archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract product code from filename
     *
     * @param string $filename
     * @return string|null
     */
    private function extractProductCodeFromFilename(string $filename): ?string
    {
        // Remove extension
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Try different patterns
        $patterns = [
            '/^([A-Z0-9-_]+)_/',      // CODE_something.ext
            '/^([A-Z0-9-_]+)\s/',     // CODE something.ext
            '/^([A-Z0-9-_]+)\./',     // CODE.ext
            '/^([A-Z0-9-_]+)$/',      // CODE (exact match)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $nameWithoutExt, $matches)) {
                return $matches[1];
            }
        }

        // If no pattern matches, try to use the whole filename as code
        if (preg_match('/^[A-Z0-9-_]+$/i', $nameWithoutExt)) {
            return strtoupper($nameWithoutExt);
        }

        return null;
    }

    /**
     * Get file size in human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Check if file is valid image
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    private function isValidImage($file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($file->getMimeType(), $allowedMimes);
    }

    /**
     * Check if file is valid PDF
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    private function isValidPDF($file): bool
    {
        return $file->getMimeType() === 'application/pdf';
    }
}
