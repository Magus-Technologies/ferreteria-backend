<?php

namespace App\Http\Controllers;

use App\Services\Interfaces\DashboardContableServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardContableController extends Controller
{
    private DashboardContableServiceInterface $service;

    public function __construct(DashboardContableServiceInterface $service)
    {
        $this->service = $service;
    }

    private function filtros(Request $request): array
    {
        $request->validate([
            'almacen_id' => 'sometimes|nullable|integer',
            'desde'      => 'sometimes|nullable|date',
            'hasta'      => 'sometimes|nullable|date',
        ]);

        return $request->only(['almacen_id', 'desde', 'hasta']);
    }

    public function porcentajeGanancias(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->porcentajeGananciasPorMes($this->filtros($request))]);
    }

    public function cierresConPerdida(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->cierresCajaConPerdidaPorMes($this->filtros($request))]);
    }

    public function clientesMorosos(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->clientesMorosos($this->filtros($request))]);
    }

    public function gananciasPorRecomendacion(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->gananciasPorRecomendador($this->filtros($request))]);
    }

    public function resumenCards(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->resumenCards($this->filtros($request))]);
    }
}
