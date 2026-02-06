<?php

namespace App\Jobs;

use App\Models\ComprobanteElectronico;
use App\Services\Interfaces\FacturaServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job para enviar FACTURAS a SUNAT automáticamente después de 5 días
 * 
 * IMPORTANTE: 
 * - Solo envía FACTURAS (tipo_documento '01' y '03')
 * - Las Notas de Débito y Crédito se envían MANUALMENTE
 * - Se ejecuta diariamente a las 2:00 AM
 */
class EnviarComprobantesASunatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 3; // 3 intentos

    public function __construct()
    {
        //
    }

    public function handle(FacturaServiceInterface $facturaService): void
    {
        Log::info('=== Iniciando envío automático de FACTURAS a SUNAT ===');

        // Obtener SOLO FACTURAS (01=Factura, 03=Boleta) pendientes de envío con más de 5 días
        $fechaLimite = Carbon::now()->subDays(5);

        $facturasPendientes = ComprobanteElectronico::whereIn('tipo_documento', ['01', '03'])
            ->where('estado_sunat', 'pendiente')
            ->whereNull('fecha_envio_sunat')
            ->where('fecha_emision', '<=', $fechaLimite)
            ->with('venta')
            ->get();

        Log::info("Facturas pendientes encontradas: {$facturasPendientes->count()}");

        if ($facturasPendientes->isEmpty()) {
            Log::info('No hay facturas pendientes de envío automático');
            return;
        }

        foreach ($facturasPendientes as $comprobante) {
            try {
                Log::info("Procesando factura: {$comprobante->tipo_documento}-{$comprobante->serie}-{$comprobante->numero}", [
                    'comprobante_id' => $comprobante->id,
                    'documento_id' => $comprobante->documento_id,
                    'fecha_emision' => $comprobante->fecha_emision,
                ]);

                $resultado = $facturaService->enviarASunat($comprobante->documento_id, 'automatico');
                
                Log::info("Factura enviada exitosamente: {$comprobante->id}", [
                    'resultado' => $resultado,
                ]);

            } catch (\Exception $e) {
                Log::error("Error al procesar factura {$comprobante->id}: {$e->getMessage()}", [
                    'comprobante_id' => $comprobante->id,
                    'tipo' => $comprobante->tipo_documento,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('=== Finalizado envío automático de FACTURAS a SUNAT ===');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job de envío automático de FACTURAS a SUNAT falló completamente', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
