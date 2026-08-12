<?php

namespace App\Http\Controllers\Cajas;

use App\Events\ModelChanged;
use App\Http\Controllers\Controller;
use App\Services\Interfaces\TrasladoBovedaServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrasladoBovedaController extends Controller
{
    protected TrasladoBovedaServiceInterface $trasladoService;

    public function __construct(TrasladoBovedaServiceInterface $trasladoService)
    {
        $this->trasladoService = $trasladoService;
    }

    /**
     * Registrar un nuevo traslado a bóveda
     */
    public function registrar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'apertura_cierre_caja_id' => 'required|string',
            'sub_caja_id' => 'required|string|exists:sub_cajas,id',
            'despliegue_pago_id' => 'required|string|exists:desplieguedepago,id',
            'vendedor_id' => 'required|string|exists:user,id',
            'supervisor_id' => 'nullable|string|exists:user,id',
            'supervisor_password' => 'nullable|string',
            'monto' => 'required|numeric|min:0.01',
            'justificacion' => 'nullable|string|max:500',
        ]);

        try {
            $traslado = $this->trasladoService->registrarTraslado($validated);

            return response()->json([
                'success' => true,
                'message' => 'Traslado a bóveda registrado exitosamente',
                'data' => $traslado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener traslados de una caja específica
     */
    public function obtenerPorCaja(string $aperturaCierreId): JsonResponse
    {
        try {
            $traslados = $this->trasladoService->obtenerTrasladosPorCaja($aperturaCierreId);

            return response()->json([
                'success' => true,
                'data' => $traslados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener traslados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los traslados (incluyendo anulados) para historial
     */
    /**
     * Historial de traslados del usuario autenticado, sin depender de que tenga
     * caja abierta. Alimenta la pestaña "Traslado a Bóveda" de Movimientos de Caja.
     */
    public function historial(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->trasladoService->obtenerHistorialDelUsuario(auth()->id()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de traslados: ' . $e->getMessage()
            ], 500);
        }
    }

    public function obtenerTodosPorCaja(string $aperturaCierreId): JsonResponse
    {
        try {
            $traslados = $this->trasladoService->obtenerTodosLosTrasladosPorCaja($aperturaCierreId);

            return response()->json([
                'success' => true,
                'data' => $traslados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener traslados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener total trasladado de una caja
     */
    public function obtenerTotal(string $aperturaCierreId): JsonResponse
    {
        try {
            $total = $this->trasladoService->obtenerTotalTrasladado($aperturaCierreId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_trasladado' => $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular total: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar supervisor
     */
    public function validarSupervisor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supervisor_id' => 'required|exists:user,id',
            'password' => 'required|string',
        ]);

        try {
            $esValido = $this->trasladoService->validarSupervisor(
                $validated['supervisor_id'],
                $validated['password']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'es_valido' => $esValido
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar supervisor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular un traslado
     */
    public function anular(string $trasladoId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supervisor_id' => 'nullable|exists:user,id',
            'supervisor_password' => 'nullable|string',
        ]);

        try {
            $this->trasladoService->anularTraslado(
                $trasladoId,
                $validated['supervisor_id'] ?? null,
                $validated['supervisor_password'] ?? null
            );

            ModelChanged::dispatch('traslados-boveda', 'anulado', $trasladoId);

            return response()->json([
                'success' => true,
                'message' => 'Traslado anulado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
