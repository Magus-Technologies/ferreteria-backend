<?php

namespace App\Http\Controllers\Analisis;

use App\Http\Controllers\Controller;
use App\Services\Analisis\PepsAnalisisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalisisPepsController extends Controller
{
    public function __construct(
        private PepsAnalisisService $pepsService
    ) {}

    /**
     * GET /api/analisis-peps
     * Parámetros: almacen_id?, desde?, hasta?, producto_id?
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['almacen_id', 'desde', 'hasta', 'producto_id']);

            $resultado = $this->pepsService->obtenerAnalisisPeps($filtros);

            return response()->json([
                'success' => true,
                'data'    => $resultado,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en análisis PEPS (index): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el análisis PEPS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/analisis-peps/calcular
     */
    public function calcular(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lotes'                     => 'required|array|min:1',
                'lotes.*.id'                => 'required',
                'lotes.*.label'             => 'required|string',
                'lotes.*.costo_usd'         => 'required|numeric|min:0',
                'lotes.*.stock'             => 'required|numeric|min:0',
                'lotes.*.tc_compra'         => 'required|numeric|min:0.0001',
                'lotes.*.tc_pago'           => 'required|numeric|min:0.0001',
                'ventas'                    => 'required|array|min:1',
                'ventas.*.id'               => 'required',
                'ventas.*.cantidad'         => 'required|numeric|min:0.0001',
                'ventas.*.precio'           => 'required|numeric|min:0',
            ]);

            $resultado = $this->pepsService->calcularPeps(
                $validated['lotes'],
                $validated['ventas']
            );

            return response()->json([
                'success' => true,
                'data'    => $resultado,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Error en análisis PEPS: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al calcular el análisis PEPS: ' . $e->getMessage(),
            ], 500);
        }
    }
}
