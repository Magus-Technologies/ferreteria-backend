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
 * Job para enviar FACTURAS y BOLETAS a SUNAT automáticamente
 * 
 * IMPORTANTE: 
 * - Facturas (01): se envían después de 3 días calendario (límite SUNAT)
 * - Boletas (03): se envían después de 5 MINUTOS (TESTING - en producción son 7 días)
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
        // ✅ Verificar si el envío automático está habilitado
        if (!config('greenter.auto_send_enabled', false)) {
            Log::info('Envío automático a SUNAT está DESACTIVADO. No se procesarán comprobantes.');
            return;
        }

        Log::info('=== Iniciando envío automático de FACTURAS a SUNAT ===');

        // Obtener FACTURAS (01) con más de 3 días y BOLETAS (03) con más de 5 MINUTOS (TESTING)
        $fechaLimiteFacturas = Carbon::now()->subDays(3); // Facturas: 3 días máximo
        $fechaLimiteBoletas = Carbon::now()->subMinutes(5);  // Boletas: 5 MINUTOS para testing

        // Obtener facturas (01) pendientes con más de 3 días
        $facturasPendientes = ComprobanteElectronico::where('tipo_documento', '01')
            ->where('estado_sunat', 'pendiente')
            ->whereNull('fecha_envio_sunat')
            ->where('fecha_emision', '<=', $fechaLimiteFacturas)
            ->with('venta')
            ->get();

        // Obtener boletas (03) pendientes con más de 5 MINUTOS (TESTING)
        $boletasPendientes = ComprobanteElectronico::where('tipo_documento', '03')
            ->where('estado_sunat', 'pendiente')
            ->whereNull('fecha_envio_sunat')
            ->where('fecha_emision', '<=', $fechaLimiteBoletas)
            ->with('venta')
            ->get();

        // Combinar ambas colecciones
        $comprobantesPendientes = $facturasPendientes->merge($boletasPendientes);

        Log::info("Comprobantes pendientes encontrados: {$comprobantesPendientes->count()}", [
            'facturas' => $facturasPendientes->count(),
            'boletas' => $boletasPendientes->count(),
        ]);

        if ($comprobantesPendientes->isEmpty()) {
            Log::info('No hay comprobantes pendientes de envío automático');
            return;
        }

        foreach ($comprobantesPendientes as $comprobante) {
            try {
                Log::info("Procesando comprobante: {$comprobante->tipo_documento}-{$comprobante->serie}-{$comprobante->numero}", [
                    'comprobante_id' => $comprobante->id,
                    'documento_id' => $comprobante->documento_id,
                    'fecha_emision' => $comprobante->fecha_emision,
                    'tipo' => $comprobante->tipo_documento === '01' ? 'Factura' : 'Boleta',
                ]);

                $resultado = $facturaService->enviarASunat($comprobante->documento_id, 'automatico');
                
                Log::info("Comprobante enviado exitosamente: {$comprobante->id}", [
                    'resultado' => $resultado,
                ]);

            } catch (\Exception $e) {
                Log::error("Error al procesar comprobante {$comprobante->id}: {$e->getMessage()}", [
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
