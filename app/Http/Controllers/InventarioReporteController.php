<?php

namespace App\Http\Controllers;

use App\Services\Interfaces\InventarioReporteServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventarioReporteController extends Controller
{
    private InventarioReporteServiceInterface $reporteService;

    public function __construct(InventarioReporteServiceInterface $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function topProductos(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'tipo' => 'sometimes|string|in:ventas,utilidad,recurrencia',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $tipo = $request->get('tipo', 'ventas');
        $limit = $request->get('limit', 20);

        $datos = $this->reporteService->obtenerTopProductos($filtros, $tipo, $limit);

        return response()->json(['data' => $datos]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'categoria_id' => 'sometimes|integer',
            'marca_id' => 'sometimes|integer',
            'search' => 'sometimes|string|max:255',
            'cs_stock' => 'sometimes|in:con_stock,sin_stock,all',
        ]);

        $filtros = $request->only(['almacen_id', 'categoria_id', 'marca_id', 'search', 'cs_stock']);
        $resumen = $this->reporteService->obtenerResumenInventario($filtros);

        return response()->json(['data' => $resumen]);
    }

    public function stockValorizado(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'categoria_id' => 'sometimes|integer',
            'marca_id' => 'sometimes|integer',
            'con_stock' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id', 'categoria_id', 'marca_id', 'con_stock']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerStockValorizado($filtros, $perPage, $page);

        return response()->json($resultado);
    }

    public function stockBajo(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerProductosStockBajo($filtros, $perPage, $page);

        return response()->json($resultado);
    }

    public function demandaPorCategoria(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $limit = $request->get('limit', 10);

        $datos = $this->reporteService->obtenerDemandaPorCategoria($filtros, $limit);

        return response()->json(['data' => $datos]);
    }

    public function costoAjuste(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);

        return response()->json(['data' => ['costo_ajuste' => $this->reporteService->obtenerCostoAjuste($filtros)]]);
    }

    public function productosRotados(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);

        return response()->json(['data' => $this->reporteService->obtenerProductosRotados($filtros)]);
    }

    public function inventarioPorAnio(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'anio' => 'sometimes|integer|min:2000|max:2100',
        ]);

        $filtros = $request->only(['almacen_id', 'anio']);

        return response()->json(['data' => $this->reporteService->obtenerInventarioPorAnio($filtros)]);
    }

    public function productosSinRotar(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $perPage = $request->get('per_page', 100);
        $page = $request->get('page', 1);

        return response()->json($this->reporteService->obtenerProductosSinRotar($filtros, $perPage, $page));
    }

    public function cantidadesVendidas(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerCantidadesVendidas($filtros, $perPage, $page);

        return response()->json($resultado);
    }
}
