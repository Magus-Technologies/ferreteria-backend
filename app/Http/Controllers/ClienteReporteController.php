<?php

namespace App\Http\Controllers;

use App\Services\Interfaces\ClienteReporteServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClienteReporteController extends Controller
{
    private ClienteReporteServiceInterface $reporteService;

    public function __construct(ClienteReporteServiceInterface $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function topClientes(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $limit = $request->get('limit', 10);

        $datos = $this->reporteService->obtenerTopClientes($filtros, $limit);

        return response()->json(['data' => $datos]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
        ]);

        $filtros = $request->only(['almacen_id']);
        $resumen = $this->reporteService->obtenerResumenClientes($filtros);

        return response()->json(['data' => $resumen]);
    }

    public function clientesPorCobrar(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerClientesPorCobrar($filtros, $perPage, $page);

        return response()->json($resultado);
    }

    public function listadoClientes(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_cliente' => 'sometimes|string|in:p,e',
            'estado' => 'sometimes|string|in:activo,inactivo',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['tipo_cliente', 'estado']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerListadoClientes($filtros, $perPage, $page);

        return response()->json($resultado);
    }

    public function clientesFrecuentes(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $limit = $request->get('limit', 10);

        $datos = $this->reporteService->obtenerClientesFrecuentes($filtros, $limit);

        return response()->json(['data' => $datos]);
    }

    public function clientesRecientes(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'dias' => 'sometimes|integer|min:1|max:365',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id', 'dias']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerClientesRecientes($filtros, $perPage, $page);

        return response()->json($resultado);
    }
}
