<?php

namespace App\Jobs;

use App\Models\ComprobanteElectronico;
use App\Models\Empresa;
use App\Models\GuiaRemision;
use App\Models\NotaCredito;
use App\Services\ComunicacionBajaService;
use App\Services\GuiaRemisionService;
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\Interfaces\NotaCreditoServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job para enviar FACTURAS y BOLETAS a SUNAT automáticamente, y para dar de
 * baja automáticamente las que correspondan a ventas ANULADAS.
 *
 * - Días de espera y plazo máximo: configurables en Mi Empresa → SUNAT
 *   (por defecto Facturas 3 días / Boletas 0 días; tope legal SUNAT 3 y 7).
 * - Las Notas de Crédito también pueden enviarse automáticamente (mismo
 *   plazo legal que factura: 3 días). Las Notas de Débito se envían
 *   MANUALMENTE — no hay pedido de auto-envío para ese tipo.
 * - Las Guías de Remisión Electrónicas también se envían automáticamente.
 *   Como la GRE-API de SUNAT es asíncrona (envío entrega un ticket, no un
 *   CDR), este job además CONSULTA en cada corrida el ticket de las guías
 *   que quedaron pendientes de un envío anterior, hasta que SUNAT confirme.
 * - Se ejecuta 5 veces al día (ver routes/console.php), cada comprobante
 *   además reintenta hasta 3 veces en el momento si falla (enviarConReintentos
 *   / darDeBajaConReintentos).
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

    public function handle(FacturaServiceInterface $facturaService, ComunicacionBajaService $comunicacionBajaService, NotaCreditoServiceInterface $notaCreditoService, GuiaRemisionService $guiaRemisionService): void
    {
        $empresa = Empresa::first();

        // 1. PROCESAR FACTURAS (01)
        if ($empresa?->sunat_auto_send_factura_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_factura_after_days;
            $this->procesarTipoDocumento($facturaService, '01', 'factura', $afterDays);
            $this->procesarBajasAutomaticas($comunicacionBajaService, '01', 'factura');
        }

        // 2. PROCESAR BOLETAS (03)
        if ($empresa?->sunat_auto_send_boleta_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_boleta_after_days;
            $this->procesarTipoDocumento($facturaService, '03', 'boleta', $afterDays);
            $this->procesarBajasAutomaticas($comunicacionBajaService, '03', 'boleta');
        }

        // 3. PROCESAR NOTAS DE CRÉDITO (07)
        if ($empresa?->sunat_auto_send_nota_credito_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_nota_credito_after_days;
            $this->procesarNotasCredito($notaCreditoService, $afterDays);
        }

        // 4. PROCESAR GUÍAS DE REMISIÓN ELECTRÓNICAS (09/31)
        if ($empresa?->sunat_auto_send_guia_enabled) {
            $afterDays = (int) $empresa->sunat_auto_send_guia_after_days;
            $this->procesarGuiasPendientesEnvio($guiaRemisionService, $afterDays);
        }

        // La consulta de tickets pendientes corre siempre que haya alguno,
        // sin importar el toggle: si ya se enviaron, hay que confirmarlas
        // con SUNAT tarde o temprano (desactivar el auto-envío no debería
        // dejar guías enviadas colgadas sin CDR para siempre).
        $this->procesarGuiasPendientesConsulta($guiaRemisionService);
    }

    /**
     * Da de baja automáticamente ventas ANULADAS cuyo comprobante SUNAT
     * sigue ACEPTADO/PENDIENTE — el desfase donde el sistema dice "anulada"
     * pero SUNAT sigue teniendo el comprobante vigente. Reusa el mismo
     * cálculo de plazo que la pantalla manual de Comunicación de Baja
     * (`ComprobanteElectronicoController::pendientesBaja`): 3 días factura,
     * 7 boleta, contados desde la emisión. Se activa/desactiva junto con el
     * envío automático normal de ese tipo de documento — no hay un toggle
     * separado para esto en Mi Empresa → SUNAT.
     */
    private function procesarBajasAutomaticas(ComunicacionBajaService $comunicacionBajaService, string $tipoDoc, string $configKey): void
    {
        $hoy = Carbon::now()->startOfDay();
        $plazoMaximo = $tipoDoc === '01' ? 3 : 7;

        // 'PENDIENTE' SÍ va acá — ver el mismo comentario en
        // ComprobanteElectronicoController::pendientesBaja(). Es el único
        // camino que el sistema ofrece para estos casos (FacturaService
        // bloquea el reenvío directo de una venta anulada).
        $pendientesBaja = ComprobanteElectronico::where('tipo_comprobante', $tipoDoc)
            ->whereIn('estado_sunat', ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES', 'PENDIENTE'])
            // Mismo criterio que la pantalla manual: si la venta se anuló con
            // nota de crédito, ya quedó corregida ante SUNAT y no va a baja.
            ->whereHas('venta', fn ($q) => $q->where('estado_de_venta', 'an')
                ->where(fn ($v) => $v->whereNull('anulado_por_nota_credito')
                    ->orWhere('anulado_por_nota_credito', false)))
            ->get()
            ->filter(function (ComprobanteElectronico $c) use ($hoy, $plazoMaximo) {
                $dias = (int) Carbon::parse($c->fecha_emision)->startOfDay()->diffInDays($hoy);
                return $dias <= $plazoMaximo;
            });

        foreach ($pendientesBaja as $comprobante) {
            $this->darDeBajaConReintentos($comunicacionBajaService, $comprobante, $configKey);
        }
    }

    private function darDeBajaConReintentos(ComunicacionBajaService $comunicacionBajaService, ComprobanteElectronico $comprobante, string $configKey): void
    {
        $maxIntentos = 3;
        $segundosEntreIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $result = $comunicacionBajaService->darDeBaja($comprobante, 'Venta anulada (envío automático)');
            if ($result['success']) {
                return;
            }

            $esUltimoIntento = $intento === $maxIntentos;
            Log::error("Error en baja automática de {$configKey} {$comprobante->serie}-{$comprobante->correlativo} (intento {$intento}/{$maxIntentos}): " . ($result['mensaje_sunat'] ?? 'desconocido'), [
                'comprobante_id' => $comprobante->id,
                'ultimo_intento' => $esUltimoIntento,
            ]);

            if (!$esUltimoIntento) {
                sleep($segundosEntreIntentos);
            }
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
        $maxIntentos = 2;
        $segundosEntreIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $facturaService->enviarASunat($comprobante->venta_id, 'automatico');

                return;
            } catch (\Exception $e) {
                $mensaje = $e->getMessage();

                // Si SUNAT ya lo tiene registrado, reintentar solo suma
                // rechazos. `enviarASunat` concilia el estado por su cuenta;
                // acá se corta en seco.
                if (str_contains($mensaje, '1033')
                    || str_contains($mensaje, '2109')
                    || stripos($mensaje, 'ya fue registrado') !== false
                    || stripos($mensaje, 'registrado anteriormente') !== false
                ) {
                    Log::warning("{$configKey} {$comprobante->id} ya estaba registrado en SUNAT: no se reintenta", [
                        'venta_id' => $comprobante->venta_id,
                        'mensaje' => $mensaje,
                    ]);

                    return;
                }

                // Un rechazo por REGLAS de SUNAT (2xxx/3xxx) es determinista:
                // el mismo XML va a fallar igual las veces que se mande. Solo
                // tiene sentido reintentar fallos de TRANSPORTE (timeout, red,
                // HTTP), que sí pueden resolverse solos.
                if (preg_match('/\[(2\d{3}|3\d{3})\]/', $mensaje)) {
                    Log::error("{$configKey} {$comprobante->id} rechazado por SUNAT (no se reintenta): {$mensaje}", [
                        'venta_id' => $comprobante->venta_id,
                    ]);

                    return;
                }

                $esUltimoIntento = $intento === $maxIntentos;
                Log::error("Error enviando {$configKey} {$comprobante->id} (intento {$intento}/{$maxIntentos}): {$mensaje}", [
                    'venta_id' => $comprobante->venta_id,
                    'ultimo_intento' => $esUltimoIntento,
                ]);

                if (!$esUltimoIntento) {
                    sleep($segundosEntreIntentos);
                }
            }
        }
    }

    /**
     * A diferencia de factura/boleta, una Nota de Crédito recién creada NO
     * tiene todavía un ComprobanteElectronico (ese registro se crea/actualiza
     * recién dentro de `NotaCreditoService::enviarASunat()`, después del envío
     * exitoso) — así que las pendientes se buscan por el propio estado de
     * `nota_credito`, no por `comprobantes_electronicos`. Mismo plazo legal
     * que factura (3 días) porque la Nota de Crédito corrige un comprobante
     * ya emitido y SUNAT la rechaza fuera de ese plazo.
     */
    private function procesarNotasCredito(NotaCreditoServiceInterface $notaCreditoService, int $diasAntiguedad): void
    {
        $maxDiasPlazo = 3;

        $fechaLimiteMin = Carbon::now()->subDays($diasAntiguedad);
        $fechaLimiteMax = Carbon::now()->subDays($maxDiasPlazo);

        $pendientes = NotaCredito::whereIn('estado', ['borrador', 'pendiente'])
            ->whereDate('fecha', '<=', $fechaLimiteMin->toDateString())
            ->whereDate('fecha', '>=', $fechaLimiteMax->toDateString())
            ->get();

        foreach ($pendientes as $notaCredito) {
            $this->enviarNotaCreditoConReintentos($notaCreditoService, $notaCredito);
        }
    }

    private function enviarNotaCreditoConReintentos(NotaCreditoServiceInterface $notaCreditoService, NotaCredito $notaCredito): void
    {
        $maxIntentos = 3;
        $segundosEntreIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $notaCreditoService->enviarASunat($notaCredito->id, 'automatico');
                return;
            } catch (\Exception $e) {
                $esUltimoIntento = $intento === $maxIntentos;
                Log::error("Error enviando nota_credito {$notaCredito->id} (intento {$intento}/{$maxIntentos}): {$e->getMessage()}", [
                    'nota_credito_id' => $notaCredito->id,
                    'ultimo_intento' => $esUltimoIntento,
                ]);

                if (!$esUltimoIntento) {
                    sleep($segundosEntreIntentos);
                }
            }
        }
    }

    /**
     * Envía a SUNAT las guías EMITIDAS que todavía no se enviaron
     * (`sunat_estado` nulo). No incluye guías FISICA (no llevan CPE) ni las
     * que ya están PENDIENTE/ACEPTADO (`enviarASunat` las rechaza).
     */
    private function procesarGuiasPendientesEnvio(GuiaRemisionService $guiaRemisionService, int $diasAntiguedad): void
    {
        $fechaLimite = Carbon::now()->subDays($diasAntiguedad);

        $pendientes = GuiaRemision::where('estado', 'EMITIDA')
            ->where('tipo_guia', '!=', 'FISICA')
            ->whereNull('sunat_estado')
            ->whereDate('fecha_emision', '<=', $fechaLimite->toDateString())
            ->get();

        foreach ($pendientes as $guia) {
            $this->enviarGuiaConReintentos($guiaRemisionService, $guia);
        }
    }

    private function enviarGuiaConReintentos(GuiaRemisionService $guiaRemisionService, GuiaRemision $guia): void
    {
        $maxIntentos = 3;
        $segundosEntreIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $guiaRemisionService->enviarASunat($guia);
                return;
            } catch (\Exception $e) {
                $esUltimoIntento = $intento === $maxIntentos;
                Log::error("Error enviando guía {$guia->id} (intento {$intento}/{$maxIntentos}): {$e->getMessage()}", [
                    'guia_id' => $guia->id,
                    'ultimo_intento' => $esUltimoIntento,
                ]);

                if (!$esUltimoIntento) {
                    sleep($segundosEntreIntentos);
                }
            }
        }
    }

    /**
     * Consulta el ticket de las guías que ya se enviaron (PENDIENTE) pero
     * todavía no se confirmaron. A diferencia de `enviarConReintentos`, acá
     * NO se reintenta en el momento: que SUNAT no haya terminado de procesar
     * el ticket es el caso normal, no un error transitorio — reintentar 3
     * veces con 5s de por medio no le da tiempo real a SUNAT. Se confirma
     * sola en una corrida posterior del job.
     */
    private function procesarGuiasPendientesConsulta(GuiaRemisionService $guiaRemisionService): void
    {
        $pendientes = GuiaRemision::where('sunat_estado', 'PENDIENTE')
            ->whereNotNull('sunat_ticket')
            ->get();

        foreach ($pendientes as $guia) {
            try {
                $guiaRemisionService->consultarEstadoSunat($guia);
            } catch (\Exception $e) {
                Log::error("Error consultando ticket de guía {$guia->id}: {$e->getMessage()}", [
                    'guia_id' => $guia->id,
                ]);
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
