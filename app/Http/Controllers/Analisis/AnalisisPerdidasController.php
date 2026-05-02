<?php

namespace App\Http\Controllers\Analisis;

use App\Http\Controllers\Controller;
use App\Services\Analisis\AnalisisPerdidasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalisisPerdidasController extends Controller
{
    public function __construct(
        private AnalisisPerdidasService $analisisPerdidasService
    ) {}

    /**
     * Obtener análisis completo de pérdidas
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'almacen_id' => 'nullable|integer',
                'desde' => 'nullable|date',
                'hasta' => 'nullable|date',
                'search' => 'nullable|string',
                'tipo_perdida' => 'nullable|string|in:ventas_bajo_costo,descuentos,comisiones,salidas,notas_credito,todas',
            ]);

            $resultado = $this->analisisPerdidasService->obtenerAnalisisPerdidas($filtros);

            // Filtrar por tipo de pérdida si se especifica
            if (!empty($filtros['tipo_perdida']) && $filtros['tipo_perdida'] !== 'todas') {
                $resultado['detalles'] = array_filter($resultado['detalles'], function($detalle) use ($filtros) {
                    $categoria = strtoupper($detalle['categoria'] ?? '');
                    
                    return match($filtros['tipo_perdida']) {
                        'ventas_bajo_costo' => str_contains($categoria, 'VENTA BAJO COSTO'),
                        'descuentos' => str_contains($categoria, 'DESCUENTO'),
                        'comisiones' => str_contains($categoria, 'COMISIÓN'),
                        'salidas' => !str_contains($categoria, 'VENTA') && 
                                    !str_contains($categoria, 'DESCUENTO') && 
                                    !str_contains($categoria, 'COMISIÓN') &&
                                    !str_contains($categoria, 'NOTA'),
                        'notas_credito' => str_contains($categoria, 'NOTA'),
                        default => true,
                    };
                });
                
                $resultado['detalles'] = array_values($resultado['detalles']);
            }

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => 'Análisis de pérdidas obtenido exitosamente',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Error al obtener análisis de pérdidas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filtros' => $filtros ?? [],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis de pérdidas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener resumen de pérdidas por categoría
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resumen(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'almacen_id' => 'nullable|integer',
                'desde' => 'nullable|date',
                'hasta' => 'nullable|date',
            ]);

            $resultado = $this->analisisPerdidasService->obtenerAnalisisPerdidas($filtros);

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => $resultado['resumen'],
                    'por_categoria' => $resultado['por_categoria'],
                ],
                'message' => 'Resumen de pérdidas obtenido exitosamente',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener resumen de pérdidas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de pérdidas',
            ], 500);
        }
    }
}
