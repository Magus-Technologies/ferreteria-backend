<?php

namespace App\Http\Controllers;

use App\Http\Resources\EgresoDineroResource;
use App\Services\Interfaces\EgresoDineroServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EgresoDineroController extends Controller
{
    public function __construct(
        private EgresoDineroServiceInterface $egresoDineroService
    ) {}

    /**
     * Obtener reporte de gastos con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'fechaDesde' => 'sometimes|nullable|date',
            'fechaHasta' => 'sometimes|nullable|date',
            'motivoGasto' => 'sometimes|string',
            'cajeroRegistra' => 'sometimes|string',
            'sucursal' => 'sometimes|string',
            'busqueda' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only([
            'almacen_id', 'fechaDesde', 'fechaHasta', 'motivoGasto', 
            'cajeroRegistra', 'sucursal', 'busqueda'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->egresoDineroService->obtenerReporteGastos($filtros, $perPage, $page);

        return response()->json([
            'data' => EgresoDineroResource::collection($resultado['data']),
            'resumen' => $resultado['resumen'],
            'pagination' => $resultado['pagination']
        ]);
    }

    /**
     * Obtener resumen de gastos (para las cards)
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'fechaDesde' => 'sometimes|nullable|date',
            'fechaHasta' => 'sometimes|nullable|date',
            'motivoGasto' => 'sometimes|string',
            'cajeroRegistra' => 'sometimes|string',
            'sucursal' => 'sometimes|string',
            'busqueda' => 'sometimes|string',
        ]);

        $filtros = $request->only([
            'almacen_id', 'fechaDesde', 'fechaHasta', 'motivoGasto',
            'cajeroRegistra', 'sucursal', 'busqueda'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $resumen = $this->egresoDineroService->obtenerResumenGastos($filtros);

        return response()->json([
            'data' => $resumen
        ]);
    }

    /**
     * Crear nuevo gasto
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:255',
            'destino' => 'sometimes|string|max:255',
            'comprobante' => 'sometimes|string|max:100',
            'vuelto' => 'sometimes|numeric|min:0',
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'autoriza' => 'sometimes|string|max:255',
        ]);

        $data = $request->only([
            'monto', 'motivo', 'destino', 'comprobante', 'vuelto',
            'despliegue_de_pago_id', 'autoriza'
        ]);

        $gasto = $this->egresoDineroService->crearGasto($data);

        return response()->json([
            'message' => 'Gasto creado exitosamente',
            'data' => $gasto
        ], 201);
    }

    /**
     * Mostrar un gasto específico
     */
    public function show(string $id): JsonResponse
    {
        $gasto = \App\Models\EgresoDinero::with(['user', 'despliegueDePago.metodoDePago'])
            ->findOrFail($id);

        return response()->json([
            'data' => new EgresoDineroResource($gasto)
        ]);
    }

    /**
     * Actualizar gasto existente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:255',
            'destino' => 'sometimes|string|max:255',
            'comprobante' => 'sometimes|string|max:100',
            'vuelto' => 'sometimes|numeric|min:0',
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'autoriza' => 'sometimes|string|max:255',
        ]);

        $data = $request->only([
            'monto', 'motivo', 'destino', 'comprobante', 'vuelto',
            'despliegue_de_pago_id', 'autoriza'
        ]);

        $gasto = $this->egresoDineroService->actualizarGasto($id, $data);

        return response()->json([
            'message' => 'Gasto actualizado exitosamente',
            'data' => $gasto
        ]);
    }

    /**
     * Anular gasto
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:255',
        ]);

        $motivo = $request->get('motivo');
        
        $this->egresoDineroService->anularGasto($id, $motivo);

        return response()->json([
            'message' => 'Gasto anulado exitosamente'
        ]);
    }

    /**
     * Exportar reporte de gastos
     */
    public function exportar(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'fechaDesde' => 'sometimes|nullable|date',
            'fechaHasta' => 'sometimes|nullable|date',
            'motivoGasto' => 'sometimes|string',
            'cajeroRegistra' => 'sometimes|string',
            'sucursal' => 'sometimes|string',
            'busqueda' => 'sometimes|string',
            'formato' => 'required|in:excel,pdf',
        ]);

        $filtros = $request->only([
            'almacen_id', 'fechaDesde', 'fechaHasta', 'motivoGasto',
            'cajeroRegistra', 'sucursal', 'busqueda'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $formato = $request->get('formato', 'excel');

        $archivo = $this->egresoDineroService->exportarReporte($filtros, $formato);

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
            'fechaDesde' => 'sometimes|nullable|date',
            'fechaHasta' => 'sometimes|nullable|date',
            'motivoGasto' => 'sometimes|string',
            'cajeroRegistra' => 'sometimes|string',
            'sucursal' => 'sometimes|string',
            'busqueda' => 'sometimes|string',
        ]);

        $email = $request->get('email');
        $filtros = $request->only([
            'almacen_id', 'fechaDesde', 'fechaHasta', 'motivoGasto',
            'cajeroRegistra', 'sucursal', 'busqueda'
        ]);

        // Filtrar valores null o vacíos
        $filtros = array_filter($filtros, function($value) {
            return $value !== null && $value !== '';
        });

        $this->egresoDineroService->enviarReportePorCorreo($email, $filtros);

        return response()->json([
            'message' => 'Reporte enviado por correo exitosamente'
        ]);
    }
}