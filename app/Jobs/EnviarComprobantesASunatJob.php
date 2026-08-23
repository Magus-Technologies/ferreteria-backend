<?php

namespace App\Jobs;

use App\Models\ComprobanteElectronico;
use App\Models\Empresa;
use App\Services\Interfaces\FacturaServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job para enviar FACTURAS y BOLETAS a SUNAT automáticamente.
 *
 * - Días de espera y plazo máximo: configurables en Mi Empresa → SUNAT
 *   (por defecto Facturas 3 días / Boletas 0 días; tope legal SUNAT 3 y 7).
 * - Las Notas de Débito y Crédito se envían MANUALMENTE.
 * - Se ejecuta 5 veces al día (ver routes/console.php), cada comprobante
 *   además reintenta hasta 3 veces en el momento si falla (enviarConReintentos).
 */
class EnviarComprobantesASunatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 10 minutos: con el reintento en el momento (hasta 3 intentos x
    // comprobante, 5s de espera entre cada uno) una corrida con varios
    // comprobantes pendientes tarda más que antes — 300s se quedaba corto.
    public $timeout = 600;
    public $tries = 3; // 3 intentos

    public function __construct()
    {
        //
    }

    public function handle(FacturaServiceInterface $facturaService): void
    {
        $empresa = Empresa::first();

        // 1. PROCESAR FACTURAS (01)
        if ($empresa?->sunat_auto_send_factura_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_factura_after_days;
            $this->procesarTipoDocumento($facturaService, '01', 'factura', $afterDays);
        }

        // 2. PROCESAR BOLETAS (03)
        if ($empresa?->sunat_auto_send_boleta_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_boleta_after_days;
            $this->procesarTipoDocumento($facturaService, '03', 'boleta', $afterDays);
        }
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
            return;
        }


        foreach ($pendientes as $comprobante) {
            $this->enviarConReintentos($facturaService, $comprobante, $configKey);
        }
    }

    /**
     * Reintenta el envío de UN comprobante varias veces, en el momento, antes
     * de rendirse. Antes se intentaba una sola vez por corrida: si fallaba
     * (ej. la API de SUNAT con un hipo momentáneo), quedaba esperando hasta la
     * PRÓXIMA corrida programada (podían ser varias horas) en vez de resolverse
     * solo en segundos — el usuario pedía exactamente que reintente "ahí mismo".
     */
    private function enviarConReintentos(FacturaServiceInterface $facturaService, ComprobanteElectronico $comprobante, string $configKey): void
    {
        $maxIntentos = 3;
        $segundosEntreIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $facturaService->enviarASunat($comprobante->venta_id, 'automatico');
                return;
            } catch (\Exception $e) {
                $esUltimoIntento = $intento === $maxIntentos;
                Log::error("Error enviando {$configKey} {$comprobante->id} (intento {$intento}/{$maxIntentos}): {$e->getMessage()}", [
                    'venta_id' => $comprobante->venta_id,
                    'ultimo_intento' => $esUltimoIntento,
                ]);

                if (!$esUltimoIntento) {
                    sleep($segundosEntreIntentos);
                }
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
