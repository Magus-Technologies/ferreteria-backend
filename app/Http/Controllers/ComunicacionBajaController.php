<?php

namespace App\Http\Controllers;

use App\Models\ComprobanteElectronico;
use App\Services\ComunicacionBajaService;
use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComunicacionBajaController extends Controller
{
    public function __construct(
        private SunatApiServiceInterface $sunatApiService,
        private ComunicacionBajaService $comunicacionBajaService
    ) {}

    /**
     * Generar XML de Comunicación de Baja (sin enviar a SUNAT).
     */
    public function generarXml(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'detalles' => 'required|array|min:1',
                // Solo Facturas (01): SUNAT rechaza VoidedDocuments para
                // boletas ("DocumentTypeCode inválido", código 2308) — las
                // boletas se anulan corrigiendo el Resumen Diario, no por
                // Comunicación de Baja. Ese flujo no está implementado acá,
                // así que se bloquea antes de que SUNAT lo rechace.
                'detalles.*.tipo_doc' => 'required|in:01',
                'detalles.*.serie' => 'required|string',
                'detalles.*.correlativo' => 'required|string',
                'detalles.*.motivo' => 'required|string',
            ], [
                'detalles.*.tipo_doc.in' => 'Las boletas no se pueden dar de baja por Comunicación de Baja (SUNAT solo lo permite para facturas). Usa Nota de Crédito.',
            ]);

            $xml = $this->sunatApiService->generarXmlComunicacionBaja($validated);

            return response()->json([
                'success' => true,
                'xml' => $xml,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar XML: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar y enviar Comunicación de Baja a SUNAT.
     *
     * Facturas (01) van por VoidedDocuments (Comunicación de Baja real).
     * Boletas (03) SUNAT las rechaza ahí ("DocumentTypeCode inválido",
     * código 2308) — se dan de baja corrigiendo el Resumen Diario con esa
     * boleta marcada estado=3, vía SunatApiService::generarYEnviarResumenBaja().
     * El frontend no necesita saber la diferencia: manda el mismo payload
     * y acá se elige el mecanismo según `tipo_doc`.
     */
    public function enviarBaja(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'detalles' => 'required|array|min:1',
                'detalles.*.tipo_doc' => 'required|in:01,03',
                'detalles.*.serie' => 'required|string',
                'detalles.*.correlativo' => 'required|string',
                'detalles.*.motivo' => 'required|string',
            ]);

            $ultimoResultado = null;
            $huboFallo = false;

            foreach ($validated['detalles'] as $detalle) {
                $comprobante = ComprobanteElectronico::where('tipo_comprobante', $detalle['tipo_doc'])
                    ->where('serie', $detalle['serie'])
                    ->where('correlativo', $detalle['correlativo'])
                    ->first();

                if (!$comprobante) {
                    $ultimoResultado = ['mensaje_sunat' => 'Comprobante no encontrado', 'codigo_sunat' => null];
                    $huboFallo = true;
                    continue;
                }

                $result = $this->comunicacionBajaService->darDeBaja($comprobante, $detalle['motivo']);

                if (!$result['success']) {
                    $huboFallo = true;
                }

                $ultimoResultado = $result;
            }

            return response()->json([
                'success' => !$huboFallo,
                'message' => $ultimoResultado['mensaje_sunat'] ?? ($huboFallo ? 'Error' : 'Comunicación de baja enviada'),
                'codigo_sunat' => $ultimoResultado['codigo_sunat'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar comunicación de baja: ' . $e->getMessage(),
            ], 500);
        }
    }
}