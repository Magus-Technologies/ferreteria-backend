<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Models\DeudaPersonal;
use App\Models\User;
use App\Services\Interfaces\AbonoDeudaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeudaPersonalController extends Controller
{
    protected $abonoService;

    public function __construct(AbonoDeudaServiceInterface $abonoService)
    {
        $this->abonoService = $abonoService;
    }

    /**
     * Listar deudas de personal
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DeudaPersonal::with([
                'user',
                'arqueoDiario.aperturaCierreCaja.cajaPrincipal',
                'arqueoDiario.aperturaCierreCaja.vendedor',
                'abonos'
            ]);

            if ($request->has('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->query('estado'));
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereHas('arqueoDiario.aperturaCierreCaja', function ($q) use ($request) {
                    $q->whereBetween('fecha_cierre', [
                        $request->query('fecha_desde'),
                        $request->query('fecha_hasta')
                    ]);
                });
            }

            $deudas = $query->orderBy('created_at', 'desc')->paginate($request->query('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $deudas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener deudas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de deudas de un usuario
     */
    public function resumen(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id', auth()->id());

            // Validar permisos
            if ($userId != auth()->id() && !auth()->user()->hasAnyRole(['supervisor', 'admin', 'administrador'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }

            $resumen = $this->abonoService->obtenerResumenDeudas($userId);

            return response()->json([
                'success' => true,
                'data' => $resumen
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar un abono a una deuda
     */
    public function registrarAbono(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deuda_personal_id' => 'required|exists:deuda_personals,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago_id' => 'nullable|exists:metododepago,id',
            'numero_operacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $abono = $this->abonoService->registrarAbono($validated);

            return response()->json([
                'success' => true,
                'message' => 'Abono registrado exitosamente',
                'data' => $abono
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener historial de abonos de una deuda
     */
    public function historialAbonos(string $deudaId): JsonResponse
    {
        try {
            $historial = $this->abonoService->obtenerHistorialAbonos($deudaId);

            return response()->json([
                'success' => true,
                'data' => $historial
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar una deuda como pagada (mantener para compatibilidad)
     */
    public function pagar(string $id, Request $request): JsonResponse
    {
        try {
            $deuda = DeudaPersonal::find($id);

            if (!$deuda) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deuda no encontrada'
                ], 404);
            }

            if ($deuda->estado === 'pagada') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta deuda ya ha sido pagada'
                ], 400);
            }

            $deuda->estado = 'pagada';
            $observacionAdicional = "\nPagado el " . now()->format('Y-m-d H:i:s');
            if ($request->filled('observaciones')) {
                $observacionAdicional .= " - Motivo: " . $request->input('observaciones');
            }
            $deuda->observaciones = $deuda->observaciones . $observacionAdicional;
            $deuda->save();

            $deuda->load(['user', 'arqueoDiario.aperturaCierreCaja.cajaPrincipal']);

            return response()->json([
                'success' => true,
                'message' => 'Deuda marcada como pagada exitosamente',
                'data' => $deuda
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar deuda como pagada: ' . $e->getMessage()
            ], 500);
        }
    }
}
