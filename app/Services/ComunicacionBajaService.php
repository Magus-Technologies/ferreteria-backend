<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Services\Interfaces\SunatApiServiceInterface;

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
                // Solo boletas (Resumen Diario) traen 'fecha_resumen' — facturas
                // van por Comunicación de Baja (Voided), que no la usa.
                'fecha_resumen_baja' => $result['fecha_resumen'] ?? null,
            ]);
        }

        return $result;
    }
}
