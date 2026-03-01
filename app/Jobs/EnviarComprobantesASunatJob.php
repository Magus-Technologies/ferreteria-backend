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
use Illuminate\Support\Facades\Cache;
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
        Log::info('=== Iniciando proceso de envío automático SUNAT ===');

        // 1. PROCESAR FACTURAS (01)
        if (Cache::get('greenter_auto_send_factura_enabled', config('greenter.auto_send_factura_enabled', false))) {
            $afterDays = (int) Cache::get('greenter_auto_send_factura_after_days', config('greenter.auto_send_factura_after_days', 3));
            $this->procesarTipoDocumento($facturaService, '01', 'factura', $afterDays);
        } else {
            Log::info('Envío automático de FACTURAS está DESACTIVADO.');
        }

        // 2. PROCESAR BOLETAS (03)
        if (Cache::get('greenter_auto_send_boleta_enabled', config('greenter.auto_send_boleta_enabled', false))) {
            $afterDays = (int) Cache::get('greenter_auto_send_boleta_after_days', config('greenter.auto_send_boleta_after_days', 0));
            $this->procesarTipoDocumento($facturaService, '03', 'boleta', $afterDays);
        } else {
            Log::info('Envío automático de BOLETAS está DESACTIVADO.');
        }

        Log::info('=== Finalizado proceso de envío automático SUNAT ===');
    }

    private function procesarTipoDocumento(FacturaServiceInterface $facturaService, string $tipoDoc, string $configKey, int $diasAntiguedad): void
    {
        // Plazo máximo legal de SUNAT
        $maxDiasPlazo = ($tipoDoc === '01') ? 3 : 7;

        // Se envían los comprobantes que tienen AL MENOS $diasAntiguedad de emitidos
        $fechaLimiteMin = Carbon::now()->subDays($diasAntiguedad);

        // Pero que NO superan el plazo máximo legal de SUNAT
        $fechaLimiteMax = Carbon::now()->subDays($maxDiasPlazo);

        $pendientes = ComprobanteElectronico::where('tipo_comprobante', $tipoDoc)
            ->where('estado_sunat', 'PENDIENTE')
            ->whereNull('fecha_envio_sunat')
            ->whereDate('fecha_emision', '<=', $fechaLimiteMin->toDateString())
            ->whereDate('fecha_emision', '>=', $fechaLimiteMax->toDateString())
            ->with('venta')
            ->get();

        if ($pendientes->isEmpty()) {
            Log::info("No hay {$configKey}s pendientes dentro del plazo legal ({$maxDiasPlazo} días) con antigüedad >= {$diasAntiguedad} días.");
            return;
        }

        Log::info("Encontrados {$pendientes->count()} {$configKey}s para enviar (Plazo legal: {$maxDiasPlazo} días, Antigüedad config: {$diasAntiguedad} días).");

        foreach ($pendientes as $comprobante) {
            try {
                Log::info("Enviando {$configKey} a SUNAT: {$comprobante->serie}-{$comprobante->correlativo} (Venta ID: {$comprobante->venta_id})");
                $facturaService->enviarASunat($comprobante->venta_id, 'automatico');
                Log::info("{$configKey} {$comprobante->serie}-{$comprobante->correlativo} enviado.");
            } catch (\Exception $e) {
                Log::error("Error enviando {$configKey} {$comprobante->id}: {$e->getMessage()}");
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job de envío automático de FACTURAS a SUNAT falló completamente', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
