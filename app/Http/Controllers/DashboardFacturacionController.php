<?php

namespace App\Http\Controllers;

use App\Services\Interfaces\DashboardFacturacionServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardFacturacionController extends Controller
{
    private DashboardFacturacionServiceInterface $service;

    public function __construct(DashboardFacturacionServiceInterface $service)
    {
        $this->service = $service;
    }

    /** Reglas de validación comunes a todos los endpoints del dashboard. */
    private function filtros(Request $request): array
    {
        $request->validate([
            'almacen_id' => 'sometimes|nullable|integer',
            'desde'      => 'sometimes|nullable|date',
            'hasta'      => 'sometimes|nullable|date',
        ]);

        return $request->only(['almacen_id', 'desde', 'hasta']);
    }

    public function resumen(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->obtenerResumen($this->filtros($request))]);
    }

    public function ventasPorCategoria(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ventasPorCategoria($this->filtros($request))]);
    }

    public function ventasPorMarca(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ventasPorMarca($this->filtros($request))]);
    }

    public function ventasPorMetodoPago(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ventasPorMetodoPago($this->filtros($request))]);
    }

    public function productosMasVendidos(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->productosMasVendidos($this->filtros($request))]);
    }

    public function ventasPorTipoDocumento(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ventasPorTipoDocumento($this->filtros($request))]);
    }

    public function ingresosPorCanal(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ingresosPorCanal($this->filtros($request))]);
    }
}
