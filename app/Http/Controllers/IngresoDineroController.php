<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngresoDineroResource;
use App\Services\Interfaces\IngresoDineroServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IngresoDineroController extends Controller
{
    public function __construct(
        private IngresoDineroServiceInterface $ingresoDineroService
    ) {}

    /**
     * Obtener reporte de ingresos con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|nullable|date',
            'hasta' => 'sometimes|nullable|date',
            'user_id' => 'sometimes|string',
            'concepto' => 'sometimes|string',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'user_id', 'concepto', 'search'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->ingresoDineroService->obtenerReporteIngresos($filtros, $perPage, $page);

        return response()->json([
            'data' => IngresoDineroResource::collection($resultado['data']),
            'resumen' => $resultado['resumen'],
            'pagination' => $resultado['pagination']
        ]);
    }

    /**
     * Obtener resumen de ingresos (para las cards)
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|nullable|date',
            'hasta' => 'sometimes|nullable|date',
            'user_id' => 'sometimes|string',
            'concepto' => 'sometimes|string',
            'search' => 'sometimes|string',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'user_id', 'concepto', 'search'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $resumen = $this->ingresoDineroService->obtenerResumenIngresos($filtros);

        return response()->json([
            'data' => $resumen
        ]);
    }

    /**
     * Crear nuevo ingreso
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:255',
            'comentario' => 'sometimes|string|max:500',
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'autoriza' => 'sometimes|string|max:255',
        ]);

        $data = $request->only([
            'monto', 'concepto', 'comentario', 'despliegue_de_pago_id', 'autoriza'
        ]);

        $ingreso = $this->ingresoDineroService->crearIngreso($data);

        return response()->json([
            'message' => 'Ingreso creado exitosamente',
            'data' => $ingreso
        ], 201);
    }

    /**
     * Mostrar un ingreso específico
     */
    public function show(string $id): JsonResponse
    {
        $ingreso = \App\Models\IngresoDinero::with(['user', 'despliegueDePago.metodoDePago'])
            ->findOrFail($id);

        return response()->json([
            'data' => new IngresoDineroResource($ingreso)
        ]);
    }

    /**
     * Actualizar ingreso existente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:255',
            'comentario' => 'sometimes|string|max:500',
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'autoriza' => 'sometimes|string|max:255',
        ]);

        $data = $request->only([
            'monto', 'concepto', 'comentario', 'despliegue_de_pago_id', 'autoriza'
        ]);

        $ingreso = $this->ingresoDineroService->actualizarIngreso($id, $data);

        return response()->json([
            'message' => 'Ingreso actualizado exitosamente',
            'data' => $ingreso
        ]);
    }

    /**
     * Anular ingreso
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:255',
        ]);

        $motivo = $request->get('motivo');
        
        $this->ingresoDineroService->anularIngreso($id, $motivo);

        return response()->json([
            'message' => 'Ingreso anulado exitosamente'
        ]);
    }

    /**
     * Exportar reporte de ingresos
     */
    public function exportar(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|nullable|date',
            'hasta' => 'sometimes|nullable|date',
            'user_id' => 'sometimes|string',
            'concepto' => 'sometimes|string',
            'search' => 'sometimes|string',
            'formato' => 'required|in:excel,pdf',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'user_id', 'concepto', 'search'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $formato = $request->get('formato', 'excel');

        $archivo = $this->ingresoDineroService->exportarReporte($filtros, $formato);

        return response()->json([
            'data' => [
                'url' => $archivo['url'],
                'nombre' => $archivo['nombre']
            ]
        ]);
    }

    /**
     * Enviar reporte por correo
     */
    public function enviarCorreo(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|nullable|date',
            'hasta' => 'sometimes|nullable|date',
            'user_id' => 'sometimes|string',
            'concepto' => 'sometimes|string',
            'search' => 'sometimes|string',
        ]);

        $email = $request->get('email');
        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'user_id', 'concepto', 'search'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $this->ingresoDineroService->enviarReportePorCorreo($email, $filtros);

        return response()->json([
            'message' => 'Reporte enviado por correo exitosamente'
        ]);
    }
}