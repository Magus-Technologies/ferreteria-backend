<?php

namespace App\Http\Controllers;

use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComunicacionBajaController extends Controller
{
    public function __construct(
        private SunatApiServiceInterface $sunatApiService
    ) {}

    /**
     * Generar XML de Comunicación de Baja (sin enviar a SUNAT).
     */
    public function generarXml(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'detalles' => 'required|array|min:1',
                'detalles.*.tipo_doc' => 'required|in:01,03',
                'detalles.*.serie' => 'required|string',
                'detalles.*.correlativo' => 'required|string',
                'detalles.*.motivo' => 'required|string',
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

            $result = $this->sunatApiService->generarYEnviarComunicacionBaja($validated);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['mensaje_sunat'] ?? ($result['success'] ? 'Comunicación de baja enviada' : 'Error'),
                'codigo_sunat' => $result['codigo_sunat'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar comunicación de baja: ' . $e->getMessage(),
            ], 500);
        }
    }
}