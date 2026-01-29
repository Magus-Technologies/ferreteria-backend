<?php

namespace App\Listeners;

use App\Events\ProductoCreated;
use App\Events\ProductoImported;
use App\Events\ImportCompleted;
use App\Events\ImportFailed;
use App\Events\ProductFileProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LogProductoActivity implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'audit';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        try {
            switch (get_class($event)) {
                case ProductoCreated::class:
                    $this->logProductoCreated($event);
                    break;

                case ProductoImported::class:
                    $this->logProductoImported($event);
                    break;

                case ImportCompleted::class:
                    $this->logImportCompleted($event);
                    break;

                case ImportFailed::class:
                    $this->logImportFailed($event);
                    break;

                case ProductFileProcessed::class:
                    $this->logFileProcessed($event);
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Failed to log product activity", [
                'event_class' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log product creation activity
     */
    private function logProductoCreated(ProductoCreated $event): void
    {
        $this->createActivityLog('PRODUCTO_CREADO', [
            'producto_id' => $event->producto->id,
            'cod_producto' => $event->producto->cod_producto,
            'name' => $event->producto->name,
            'categoria_id' => $event->producto->categoria_id,
            'marca_id' => $event->producto->marca_id,
            'context' => $event->context
        ], $event->userId);

        Log::info("Product created", [
            'producto_id' => $event->producto->id,
            'cod_producto' => $event->producto->cod_producto,
            'name' => $event->producto->name,
            'user_id' => $event->userId
        ]);
    }

    /**
     * Log product import activity
     */
    private function logProductoImported(ProductoImported $event): void
    {
        $this->createActivityLog('PRODUCTO_IMPORTADO', [
            'producto_id' => $event->productoId,
            'product_name' => $event->productData['name'] ?? null,
            'product_code' => $event->productData['cod_producto'] ?? null,
            'import_id' => $event->importId
        ], $event->userId);

        Log::info("Product imported", [
            'producto_id' => $event->productoId,
            'import_id' => $event->importId,
            'user_id' => $event->userId
        ]);
    }

    /**
     * Log import completion
     */
    private function logImportCompleted(ImportCompleted $event): void
    {
        $this->createActivityLog('IMPORT_COMPLETADO', [
            'import_id' => $event->importId,
            'summary' => $event->summary
        ], $event->userId);

        Log::info("Import completed", [
            'import_id' => $event->importId,
            'summary' => $event->summary,
            'user_id' => $event->userId
        ]);
    }

    /**
     * Log import failure
     */
    private function logImportFailed(ImportFailed $event): void
    {
        $this->createActivityLog('IMPORT_FALLIDO', [
            'import_id' => $event->importId,
            'error_message' => $event->errorMessage
        ], $event->userId);

        Log::warning("Import failed", [
            'import_id' => $event->importId,
            'error' => $event->errorMessage,
            'user_id' => $event->userId
        ]);
    }

    /**
     * Log file processing
     */
    private function logFileProcessed(ProductFileProcessed $event): void
    {
        $this->createActivityLog('ARCHIVO_PROCESADO', [
            'producto_id' => $event->productId,
            'file_type' => $event->fileType,
            'file_path' => $event->filePath,
            'processing_id' => $event->processingId
        ], $event->userId);

        Log::info("Product file processed", [
            'producto_id' => $event->productId,
            'file_type' => $event->fileType,
            'processing_id' => $event->processingId,
            'user_id' => $event->userId
        ]);
    }

    /**
     * Create activity log entry in database
     */
    private function createActivityLog(string $action, array $data, int $userId): void
    {
        try {
            DB::table('activity_log')->insert([
                'log_name' => 'producto',
                'description' => $action,
                'subject_type' => 'App\\Models\\Producto',
                'subject_id' => $data['producto_id'] ?? null,
                'causer_type' => 'App\\Models\\User',
                'causer_id' => $userId,
                'properties' => json_encode($data),
                'batch_uuid' => null,
                'event' => strtolower($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database logging fails
            Log::warning("Failed to create activity log in database", [
                'action' => $action,
                'data' => $data,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine if this listener should be queued.
     */
    public function shouldQueue(): bool
    {
        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed($event, $exception): void
    {
        Log::error("LogProductoActivity listener failed", [
            'event_class' => get_class($event),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Get the tags that should be assigned to the listener when queuing.
     */
    public function tags(): array
    {
        return ['audit', 'product-activity'];
    }
}
