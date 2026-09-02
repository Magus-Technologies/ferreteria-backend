<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Da de baja un comprobante ante SUNAT eligiendo el mecanismo correcto según
 * el tipo: Factura (01) va por Comunicación de Baja (VoidedDocuments);
 * Boleta (03) va por Resumen Diario corrector (SUNAT rechaza boletas en
 * VoidedDocuments con "DocumentTypeCode inválido", código 2308).
 *
 * Compartido entre ComunicacionBajaController (envío manual) y
 * EnviarComprobantesASunatJob (envío automático) para no duplicar esta
 * lógica en los dos lugares.
 */
class ComunicacionBajaService
{
    public function __construct(
        private SunatApiServiceInterface $sunatApiService
    ) {}

    public function darDeBaja(ComprobanteElectronico $comprobante, string $motivo): array
    {
        $tipoDoc = $comprobante->tipo_comprobante;

        // FACTURA nunca declarada: hay que DECLARARLA antes de anularla.
        //
        // SUNAT no puede dar de baja un documento que no tiene: responde
        // [2663] "El documento indicado no existe". Y como FacturaService
        // bloquea el envío de una venta anulada, se formaba un candado mutuo
        // que dejaba el comprobante trabado hasta que vencía el plazo de
        // envío (3 días calendario) y el correlativo se perdía.
        //
        // Las BOLETAS no pasan por acá: su Resumen Diario ya hace los dos
        // pasos (declarar con estado 1, luego baja con estado 3).
        if ($tipoDoc === '01' && $comprobante->estado_sunat === 'PENDIENTE' && $comprobante->venta_id) {
            try {
                // Se resuelve en el momento y no por constructor para no armar
                // una dependencia circular entre los dos servicios.
                app(\App\Services\Interfaces\FacturaServiceInterface::class)
                    ->enviarASunat($comprobante->venta_id, 'baja-previa', true);

                $comprobante->refresh();

                Log::info('Factura declarada a SUNAT antes de su comunicación de baja', [
                    'comprobante_id' => $comprobante->id,
                    'documento' => $comprobante->serie.'-'.$comprobante->correlativo,
                ]);
            } catch (\Throwable $e) {
                // Si no se pudo declarar, la baja tampoco va a prosperar. Se
                // devuelve el motivo real en vez del 2663 genérico, que no le
                // dice nada al usuario.
                Log::error('No se pudo declarar la factura previo a la baja', [
                    'comprobante_id' => $comprobante->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'mensaje_sunat' => 'No se pudo declarar el comprobante antes de darlo de baja: '
                        .$e->getMessage(),
                    'codigo_sunat' => null,
                ];
            }
        }

        $result = $tipoDoc === '01'
            ? $this->sunatApiService->generarYEnviarComunicacionBaja([
                'detalles' => [[
                    'tipo_doc' => $tipoDoc,
                    'serie' => $comprobante->serie,
                    'correlativo' => (string) $comprobante->correlativo,
                    'motivo' => $motivo,
                ]],
            ])
            : $this->sunatApiService->generarYEnviarResumenBaja($comprobante);

        // El envío a SUNAT no tocaba el comprobante en la base: quedaba
        // PENDIENTE para siempre aunque la baja se aceptara. Eso hacía que
        // el job automático lo volviera a intentar enviar como si fuera un
        // comprobante nuevo, y que la campanita de alertas siguiera
        // avisando de algo ya dado de baja.
        if ($result['success']) {
            $comprobante->update([
                'estado_sunat' => 'BAJA_ACEPTADA',
                'fecha_respuesta_sunat' => now(),
                'codigo_respuesta_sunat' => $result['codigo_sunat'] ?? null,
                'mensaje_respuesta_sunat' => $result['mensaje_sunat'] ?? 'Comunicación de baja aceptada',
                'motivo_anulacion' => $motivo,
            ]);
        } elseif ($result['declarada_previamente'] ?? false) {
            // Caso parcial: la boleta nunca informada se declaró OK ante SUNAT
            // (paso 1 del Resumen Diario) pero la baja en sí falló. SUNAT ya la
            // tiene como válida, así que ese es el estado REAL — dejarla en
            // PENDIENTE sería mentirle a la base y, además, haría que el
            // reintento volviera a declararla (duplicado). Marcándola ACEPTADO,
            // el reintento toma el camino de un solo envío, que es el que
            // corresponde ahora.
            $comprobante->update([
                'estado_sunat' => 'ACEPTADO',
                'fecha_envio_sunat' => now(),
                'mensaje_respuesta_sunat' => 'Declarada a SUNAT vía Resumen Diario; falta completar la baja: '
                    . ($result['mensaje_sunat'] ?? ''),
            ]);
        }

        return $result;
    }
}
