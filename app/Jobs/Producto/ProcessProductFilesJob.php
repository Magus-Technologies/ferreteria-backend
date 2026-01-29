<?php

namespace App\Jobs\Producto;

use App\Events\ProductFileProcessed;
use App\Events\FileProcessingCompleted;
use App\Events\FileProcessingFailed;
use App\Models\Producto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessProductFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900; // 15 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $files,
        public string $fileType,
        public string $processingId,
        public int $userId,
        public array $options = []
    ) {
        $this->onQueue('files');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting ProcessProductFilesJob", [
            'processing_id' => $this->processingId,
            'user_id' => $this->userId,
            'file_count' => count($this->files),
            'file_type' => $this->fileType
        ]);

        // Initialize progress tracking
        $this->initializeProgress();

        try {
            $totalFiles = count($this->files);
            $processed = 0;
            $successful = [];
            $failed = [];
            $notFound = [];

            // Process files in batches
            $batchSize = 10; // Process 10 files at a time
            $batches = array_chunk($this->files, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                // Check if processing should be cancelled
                if ($this->shouldCancel()) {
                    $this->updateProgress(
                        $this->calculateProgress($processed, $totalFiles),
                        'Procesamiento cancelado por el usuario',
                        'cancelled'
                    );
                    return;
                }

                // Process batch
                $batchResult = $this->processBatch($batch, $batchIndex);

                $successful = array_merge($successful, $batchResult['successful']);
                $failed = array_merge($failed, $batchResult['failed']);
                $notFound = array_merge($notFound, $batchResult['not_found']);

                $processed += count($batch);

                // Update progress
                $progressPercent = $this->calculateProgress($processed, $totalFiles);
                $this->updateProgress(
                    $progressPercent,
                    "Procesando lote " . ($batchIndex + 1) . " de " . count($batches) . "..."
                );

                // Small delay to prevent overwhelming the storage system
                usleep(50000); // 0.05 seconds
            }

            // Finalize processing
            $summary = [
                'total_files' => $totalFiles,
                'successful' => count($successful),
                'failed' => count($failed),
                'not_found' => count($notFound),
                'file_type' => $this->fileType,
                'started_at' => $this->getProgress()['started_at'],
                'completed_at' => now()->toISOString(),
                'duration_seconds' => now()->diffInSeconds($this->getProgress()['started_at'])
            ];

            // Complete progress
            $this->updateProgress(100, 'Procesamiento completado exitosamente', 'completed');

            // Store final results
            $this->storeFinalResults($summary, $successful, $failed, $notFound);

            // Dispatch completion event
            FileProcessingCompleted::dispatch($this->processingId, $summary, $this->userId);

            Log::info("ProcessProductFilesJob completed successfully", [
                'processing_id' => $this->processingId,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    /**
     * Initialize progress tracking
     */
    private function initializeProgress(): void
    {
        $progress = [
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Iniciando procesamiento de archivos...',
            'started_at' => now()->toISOString(),
            'total_files' => count($this->files),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'not_found' => 0,
            'user_id' => $this->userId,
            'file_type' => $this->fileType
        ];

        Cache::put("file_processing_{$this->processingId}", $progress, 3600); // 1 hour
    }

    /**
     * Update progress
     */
    private function updateProgress(int $percent, string $message, string $status = 'processing'): void
    {
        $progress = Cache::get("file_processing_{$this->processingId}", []);
        $progress['progress'] = $percent;
        $progress['message'] = $message;
        $progress['status'] = $status;
        $progress['updated_at'] = now()->toISOString();

        Cache::put("file_processing_{$this->processingId}", $progress, 3600);

        Log::debug("File processing progress updated", [
            'processing_id' => $this->processingId,
            'progress' => $percent,
            'message' => $message,
            'status' => $status
        ]);
    }

    /**
     * Get current progress
     */
    private function getProgress(): array
    {
        return Cache::get("file_processing_{$this->processingId}", []);
    }

    /**
     * Check if processing should be cancelled
     */
    private function shouldCancel(): bool
    {
        return Cache::has("file_processing_cancel_{$this->processingId}");
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress(int $processed, int $total): int
    {
        if ($total === 0) return 100;
        return (int) min(100, max(0, ($processed / $total) * 100));
    }

    /**
     * Process a batch of files
     */
    private function processBatch(array $batch, int $batchIndex): array
    {
        $successful = [];
        $failed = [];
        $notFound = [];

        foreach ($batch as $fileInfo) {
            try {
                $result = $this->processFile($fileInfo);

                if ($result['success']) {
                    $successful[] = $result['data'];

                    // Dispatch file processed event
                    ProductFileProcessed::dispatch(
                        $result['data']['product_id'],
                        $this->fileType,
                        $result['data']['file_path'],
                        $this->userId,
                        $this->processingId
                    );
                } else {
                    if ($result['reason'] === 'product_not_found') {
                        $notFound[] = $result['data'];
                    } else {
                        $failed[] = $result['data'];
                    }
                }

            } catch (\Exception $e) {
                $failed[] = [
                    'original_name' => $fileInfo['original_name'] ?? 'unknown',
                    'product_code' => $fileInfo['product_code'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];

                Log::warning("Error processing file in batch", [
                    'batch_index' => $batchIndex,
                    'file_name' => $fileInfo['original_name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'not_found' => $notFound
        ];
    }

    /**
     * Process a single file
     */
    private function processFile(array $fileInfo): array
    {
        try {
            // Extract product code from filename
            $productCode = $this->extractProductCodeFromFilename($fileInfo['original_name']);

            if (!$productCode) {
                return [
                    'success' => false,
                    'reason' => 'invalid_filename',
                    'data' => [
                        'original_name' => $fileInfo['original_name'],
                        'error' => 'No se pudo extraer código de producto del nombre del archivo'
                    ]
                ];
            }

            // Find product
            $producto = Producto::where('cod_producto', $productCode)->first();

            if (!$producto) {
                return [
                    'success' => false,
                    'reason' => 'product_not_found',
                    'data' => [
                        'product_code' => $productCode,
                        'original_name' => $fileInfo['original_name'],
                        'error' => 'Producto no encontrado'
                    ]
                ];
            }

            // Determine field name and directory
            $fieldName = $this->fileType === 'img' ? 'img' : 'ficha_tecnica';
            $directory = $this->fileType === 'img' ? 'productos/imgs' : 'productos/fichas-tecnicas';

            // Delete previous file if exists
            if ($producto->$fieldName && Storage::disk('public')->exists($producto->$fieldName)) {
                Storage::disk('public')->delete($producto->$fieldName);
            }

            // Validate file
            $this->validateFile($fileInfo);

            // Move file to final location
            $extension = pathinfo($fileInfo['original_name'], PATHINFO_EXTENSION);
            $filename = $productCode . '.' . strtolower($extension);
            $finalPath = $directory . '/' . $filename;

            // Copy from temporary location to final location
            Storage::disk('public')->copy($fileInfo['temp_path'], $finalPath);

            // Update product record
            $producto->update([$fieldName => $finalPath]);

            // Clean up temporary file
            if (Storage::disk('public')->exists($fileInfo['temp_path'])) {
                Storage::disk('public')->delete($fileInfo['temp_path']);
            }

            return [
                'success' => true,
                'data' => [
                    'product_id' => $producto->id,
                    'product_code' => $productCode,
                    'product_name' => $producto->name,
                    'original_name' => $fileInfo['original_name'],
                    'file_path' => $finalPath,
                    'file_url' => asset('storage/' . $finalPath),
                    'file_size' => Storage::disk('public')->size($finalPath)
                ]
            ];

        } catch (\Exception $e) {
            // Clean up temporary file on error
            if (isset($fileInfo['temp_path']) && Storage::disk('public')->exists($fileInfo['temp_path'])) {
                Storage::disk('public')->delete($fileInfo['temp_path']);
            }

            throw $e;
        }
    }

    /**
     * Extract product code from filename
     */
    private function extractProductCodeFromFilename(string $filename): ?string
    {
        // Remove extension
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Try different patterns
        $patterns = [
            '/^([A-Z0-9-_]+)_/',      // CODE_something.ext
            '/^([A-Z0-9-_]+)\s/',     // CODE something.ext
            '/^([A-Z0-9-_]+)\./',     // CODE.ext (if extension was part of name)
            '/^([A-Z0-9-_]+)$/',      // CODE (exact match)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $nameWithoutExt, $matches)) {
                return strtoupper($matches[1]);
            }
        }

        // If no pattern matches, try to use the whole filename as code
        if (preg_match('/^[A-Z0-9-_]+$/i', $nameWithoutExt)) {
            return strtoupper($nameWithoutExt);
        }

        return null;
    }

    /**
     * Validate file based on type
     */
    private function validateFile(array $fileInfo): void
    {
        $tempPath = $fileInfo['temp_path'];

        if (!Storage::disk('public')->exists($tempPath)) {
            throw new \Exception('Archivo temporal no encontrado');
        }

        $fileSize = Storage::disk('public')->size($tempPath);
        $maxSize = $this->fileType === 'img' ? 5242880 : 10485760; // 5MB for images, 10MB for PDFs

        if ($fileSize > $maxSize) {
            throw new \Exception('Archivo demasiado grande. Máximo permitido: ' . ($maxSize / 1024 / 1024) . 'MB');
        }

        // Validate file type by extension (basic validation)
        $extension = strtolower(pathinfo($fileInfo['original_name'], PATHINFO_EXTENSION));

        if ($this->fileType === 'img') {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception('Tipo de archivo no permitido para imágenes: ' . $extension);
            }
        } elseif ($this->fileType === 'ficha_tecnica') {
            if ($extension !== 'pdf') {
                throw new \Exception('Solo se permiten archivos PDF para fichas técnicas');
            }
        }
    }

    /**
     * Store final results in cache
     */
    private function storeFinalResults(array $summary, array $successful, array $failed, array $notFound): void
    {
        $results = [
            'summary' => $summary,
            'successful' => array_slice($successful, 0, 50), // Limit to first 50
            'failed' => array_slice($failed, 0, 50),
            'not_found' => array_slice($notFound, 0, 50),
            'has_more_successful' => count($successful) > 50,
            'has_more_failed' => count($failed) > 50,
            'has_more_not_found' => count($notFound) > 50,
            'total_successful' => count($successful),
            'total_failed' => count($failed),
            'total_not_found' => count($notFound)
        ];

        Cache::put("file_processing_results_{$this->processingId}", $results, 86400); // 24 hours
    }

    /**
     * Handle job failure
     */
    private function handleFailure(\Throwable $exception): void
    {
        Log::error("ProcessProductFilesJob failed", [
            'processing_id' => $this->processingId,
            'user_id' => $this->userId,
            'file_type' => $this->fileType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->updateProgress(
            0,
            'Procesamiento falló: ' . $exception->getMessage(),
            'failed'
        );

        // Store error details
        Cache::put("file_processing_error_{$this->processingId}", [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ], 3600);

        // Dispatch failure event
        FileProcessingFailed::dispatch($this->processingId, $exception->getMessage(), $this->userId);

        // Clean up temporary files on failure
        $this->cleanupTempFiles();
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->files as $fileInfo) {
            if (isset($fileInfo['temp_path']) && Storage::disk('public')->exists($fileInfo['temp_path'])) {
                try {
                    Storage::disk('public')->delete($fileInfo['temp_path']);
                } catch (\Exception $e) {
                    Log::warning("Failed to cleanup temp file", [
                        'temp_path' => $fileInfo['temp_path'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Generate unique processing ID
     */
    public static function generateProcessingId(): string
    {
        return 'file_processing_' . uniqid() . '_' . time();
    }
}
