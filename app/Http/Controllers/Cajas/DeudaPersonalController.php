<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Models\DeudaPersonal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeudaPersonalController extends Controller
{
    /**
     * Listar deudas de personal
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DeudaPersonal::with(['user', 'arqueoDiario.aperturaCierreCaja.cajaPrincipal']);

            if ($request->has('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->query('estado'));
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
     * Marcar una deuda como pagada
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

            if ($deuda->estado === 'pagado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta deuda ya ha sido pagada'
                ], 400);
            }

            $deuda->estado = 'pagado';
            $observacionAdicional = "\nPagado el " . now()->format('Y-m-d H:i:s');
            if ($request->filled('observaciones')) {
                $observacionAdicional .= " - Motivo: " . $request->input('observaciones');
            }
            $deuda->observaciones = $deuda->observaciones . $observacionAdicional;
            $deuda->save();

            // Refrescar para devolver con relaciones si es necesario
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
