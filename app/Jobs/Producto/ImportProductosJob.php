<?php

namespace App\Jobs\Producto;

use App\Contracts\ProductoImportServiceInterface;
use App\Events\ProductoImported;
use App\Events\ImportProgressUpdated;
use App\Events\ImportCompleted;
use App\Events\ImportFailed;
use App\Models\ImportProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportProductosJob implements ShouldQueue
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
    public $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $data,
        public string $importId,
        public int $userId,
        public string $fileName = 'import.xlsx'
    ) {
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(ProductoImportServiceInterface $importService): void
    {
        Log::info("Starting ImportProductosJob", [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'total_products' => count($this->data),
            'file_name' => $this->fileName
        ]);

        // Initialize progress tracking
        $this->initializeProgress();

        try {
            // Step 1: Validate import data
            $this->updateProgress(5, 'Validando datos...');
            $validation = $importService->validateImportData($this->data);

            if (!$validation['is_valid']) {
                throw new \Exception('Datos de importación inválidos: ' . implode(', ', $validation['errors']));
            }

            // Step 2: Prepare catalog cache
            $this->updateProgress(15, 'Preparando catálogos...');
            $catalogCache = $importService->prepareCatalogCache($this->data);

            // Step 3: Process in batches with progress tracking
            $totalProducts = count($this->data);
            $batchSize = 25; // Smaller batches for better progress tracking
            $batches = array_chunk($this->data, $batchSize);
            $processed = 0;
            $imported = 0;
            $duplicates = [];
            $errors = [];

            foreach ($batches as $batchIndex => $batch) {
                // Check if import should be cancelled
                if ($this->shouldCancel()) {
                    $this->updateProgress(
                        $this->calculateProgress($processed, $totalProducts, 15, 85),
                        'Import cancelado por el usuario',
                        'cancelled'
                    );
                    return;
                }

                // Process batch
                $batchResult = $this->processBatch($batch, $catalogCache, $batchIndex);

                $imported += $batchResult['imported'];
                $duplicates = array_merge($duplicates, $batchResult['duplicates']);
                $errors = array_merge($errors, $batchResult['errors']);

                $processed += count($batch);

                // Update progress
                $progressPercent = $this->calculateProgress($processed, $totalProducts, 15, 85);
                $this->updateProgress(
                    $progressPercent,
                    "Procesando lote " . ($batchIndex + 1) . " de " . count($batches) . "..."
                );

                // Dispatch progress event for real-time updates
                ImportProgressUpdated::dispatch($this->importId, [
                    'processed' => $processed,
                    'total' => $totalProducts,
                    'imported' => $imported,
                    'duplicates' => count($duplicates),
                    'errors' => count($errors),
                    'current_batch' => $batchIndex + 1,
                    'total_batches' => count($batches)
                ]);

                // Small delay to prevent overwhelming the database
                usleep(100000); // 0.1 seconds
            }

            // Step 4: Finalize import
            $this->updateProgress(90, 'Finalizando importación...');

            $summary = [
                'total' => $totalProducts,
                'imported' => $imported,
                'duplicates' => count($duplicates),
                'errors' => count($errors),
                'file_name' => $this->fileName,
                'started_at' => $this->getProgress()['started_at'],
                'completed_at' => now()->toISOString(),
                'duration_seconds' => now()->diffInSeconds($this->getProgress()['started_at'])
            ];

            // Complete progress
            $this->updateProgress(100, 'Import completado exitosamente', 'completed');

            // Store final results
            $this->storeFinalResults($summary, $duplicates, $errors);

            // Dispatch completion event
            ImportCompleted::dispatch($this->importId, $summary, $this->userId);

            Log::info("ImportProductosJob completed successfully", [
                'import_id' => $this->importId,
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
            'message' => 'Iniciando importación...',
            'started_at' => now()->toISOString(),
            'total_products' => count($this->data),
            'imported' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'user_id' => $this->userId,
            'file_name' => $this->fileName
        ];

        Cache::put("import_progress_{$this->importId}", $progress, 3600); // 1 hour
    }

    /**
     * Update progress
     */
    private function updateProgress(int $percent, string $message, string $status = 'processing'): void
    {
        $progress = Cache::get("import_progress_{$this->importId}", []);
        $progress['progress'] = $percent;
        $progress['message'] = $message;
        $progress['status'] = $status;
        $progress['updated_at'] = now()->toISOString();

        Cache::put("import_progress_{$this->importId}", $progress, 3600);

        Log::debug("Import progress updated", [
            'import_id' => $this->importId,
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
        return Cache::get("import_progress_{$this->importId}", []);
    }

    /**
     * Check if import should be cancelled
     */
    private function shouldCancel(): bool
    {
        return Cache::has("import_cancel_{$this->importId}");
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress(int $processed, int $total, int $minPercent, int $maxPercent): int
    {
        if ($total === 0) return $maxPercent;

        $progress = ($processed / $total) * ($maxPercent - $minPercent) + $minPercent;
        return (int) min($maxPercent, max($minPercent, $progress));
    }

    /**
     * Process a batch of products
     */
    private function processBatch(array $batch, array $catalogCache, int $batchIndex): array
    {
        $imported = 0;
        $duplicates = [];
        $errors = [];

        try {
            DB::transaction(function () use ($batch, $catalogCache, &$imported, &$duplicates, &$errors) {
                foreach ($batch as $item) {
                    try {
                        $result = $this->importSingleProduct($item, $catalogCache);

                        if ($result['success']) {
                            $imported++;

                            // Dispatch product imported event
                            ProductoImported::dispatch(
                                $result['producto_id'],
                                $item,
                                $this->userId,
                                $this->importId
                            );
                        } else {
                            if ($result['is_duplicate']) {
                                $duplicates[] = $item;
                            } else {
                                $errors[] = [
                                    'item' => $item,
                                    'error' => $result['error']
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'item' => $item,
                            'error' => $e->getMessage()
                        ];

                        Log::warning("Error importing single product in batch", [
                            'batch_index' => $batchIndex,
                            'product_name' => $item['name'] ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }, 3); // 3 retry attempts for deadlocks

        } catch (\Exception $e) {
            Log::error("Error processing batch {$batchIndex}", [
                'error' => $e->getMessage(),
                'batch_size' => count($batch)
            ]);

            // Mark all items in batch as errors
            foreach ($batch as $item) {
                $errors[] = [
                    'item' => $item,
                    'error' => "Batch processing failed: " . $e->getMessage()
                ];
            }
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors
        ];
    }

    /**
     * Import a single product (extracted from ProductoImportService)
     */
    private function importSingleProduct(array $item, array $catalogCache): array
    {
        try {
            $productoEnAlmacenesCreate = $item['producto_en_almacenes']['create'];
            unset($item['producto_en_almacenes']);

            // Adjust cost by dividing by unidades_contenidas
            $costoAjustado = $productoEnAlmacenesCreate['costo'] / $item['unidades_contenidas'];

            // Extract catalog names from Prisma connectOrCreate structure
            $categoriaNombre = $this->extractCatalogName($item, 'categoria');
            $marcaNombre = $this->extractCatalogName($item, 'marca');
            $unidadMedidaNombre = $this->extractCatalogName($item, 'unidad_medida');

            // Resolve IDs using cache
            $categoriaId = $this->resolveCatalogId($categoriaNombre, $catalogCache['categorias']);
            $marcaId = $this->resolveCatalogId($marcaNombre, $catalogCache['marcas']);
            $unidadMedidaId = $this->resolveCatalogId($unidadMedidaNombre, $catalogCache['unidades_medida']);

            if (!$categoriaId || !$marcaId || !$unidadMedidaId) {
                return [
                    'success' => false,
                    'is_duplicate' => false,
                    'error' => 'IDs de catálogo no encontrados'
                ];
            }

            // Create product
            $producto = \App\Models\Producto::create([
                'cod_producto' => $item['cod_producto'] ?? null,
                'cod_barra' => $item['cod_barra'] ?? null,
                'name' => $item['name'],
                'name_ticket' => $item['name_ticket'],
                'categoria_id' => $categoriaId,
                'marca_id' => $marcaId,
                'unidad_medida_id' => $unidadMedidaId,
                'accion_tecnica' => $item['accion_tecnica'] ?? null,
                'stock_min' => $item['stock_min'] ?? 0,
                'stock_max' => $item['stock_max'] ?? null,
                'unidades_contenidas' => $item['unidades_contenidas'],
                'permitido' => $item['permitido'] ?? true,
                'estado' => true,
            ]);

            // Create product_almacen
            \App\Models\ProductoAlmacen::create([
                'producto_id' => $producto->id,
                'almacen_id' => $productoEnAlmacenesCreate['almacen_id'],
                'stock_fraccion' => $productoEnAlmacenesCreate['stock_fraccion'],
                'costo' => $costoAjustado,
                'ubicacion_id' => $productoEnAlmacenesCreate['ubicacion_id'],
            ]);

            return [
                'success' => true,
                'is_duplicate' => false,
                'producto_id' => $producto->id
            ];

        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violations (duplicates)
            if ($e->getCode() === '23000') {
                return [
                    'success' => false,
                    'is_duplicate' => true,
                    'error' => 'Producto duplicado'
                ];
            }
            throw $e;
        }
    }

    /**
     * Extract catalog name from item data
     */
    private function extractCatalogName(array $item, string $catalog): ?string
    {
        if (isset($item[$catalog]['connectOrCreate']['where']['name'])) {
            return $item[$catalog]['connectOrCreate']['where']['name'];
        } elseif (isset($item[$catalog . '_id'])) {
            return $item[$catalog . '_id'];
        }
        return null;
    }

    /**
     * Resolve catalog ID from cache
     */
    private function resolveCatalogId(?string $name, array $cache): ?int
    {
        if (!$name) {
            return null;
        }

        if (is_numeric($name)) {
            return (int) $name;
        }

        return $cache[$name] ?? null;
    }

    /**
     * Store final results in cache with longer expiration
     */
    private function storeFinalResults(array $summary, array $duplicates, array $errors): void
    {
        $results = [
            'summary' => $summary,
            'duplicates' => array_slice($duplicates, 0, 100), // Limit to first 100
            'errors' => array_slice($errors, 0, 50), // Limit to first 50
            'has_more_duplicates' => count($duplicates) > 100,
            'has_more_errors' => count($errors) > 50,
            'total_duplicates' => count($duplicates),
            'total_errors' => count($errors)
        ];

        Cache::put("import_results_{$this->importId}", $results, 86400); // 24 hours
    }

    /**
     * Handle job failure
     */
    private function handleFailure(\Throwable $exception): void
    {
        Log::error("ImportProductosJob failed", [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->updateProgress(
            0,
            'Import falló: ' . $exception->getMessage(),
            'failed'
        );

        // Store error details
        Cache::put("import_error_{$this->importId}", [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ], 3600);

        // Dispatch failure event
        ImportFailed::dispatch($this->importId, $exception->getMessage(), $this->userId);
    }

    /**
     * Generate unique import ID
     */
    public static function generateImportId(): string
    {
        return 'import_' . uniqid() . '_' . time();
    }
}
