<?php

namespace App\Http\Controllers;

use App\Models\AperturaYCierreCaja;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CajaController extends Controller
{
    /**
     * GET /api/cajas/consulta-apertura
     * Consulta si el usuario ya tiene una caja abierta
     */
    public function consultaApertura(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'message' => 'No tienes sesión activa',
                ],
            ], 401);
        }

        $existeApertura = AperturaYCierreCaja::where('user_id', $user->id)
            ->whereNull('fecha_cierre')
            ->exists();

        if ($existeApertura) {
            return response()->json([
                'error' => [
                    'message' => 'Ya existe una apertura de caja para este usuario',
                ],
            ], 422);
        }

        return response()->json([
            'data' => 'ok',
        ]);
    }

    /**
     * POST /api/cajas/aperturar
     * Crea una nueva apertura de caja
     */
    public function aperturar(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'message' => 'No tienes sesión activa',
                ],
            ], 401);
        }

        $validated = $request->validate([
            'monto_apertura' => 'required|numeric|min:0',
        ]);

        // Verificar que no exista una caja abierta
        $existeApertura = AperturaYCierreCaja::where('user_id', $user->id)
            ->whereNull('fecha_cierre')
            ->first();

        if ($existeApertura) {
            return response()->json([
                'error' => [
                    'message' => 'Ya existe una apertura de caja para este usuario',
                ],
            ], 422);
        }

        // Crear apertura de caja con CUID
        $apertura = AperturaYCierreCaja::create([
            'id' => (string) Str::uuid(), // Generar CUID/UUID
            'user_id' => $user->id,
            'monto_apertura' => $validated['monto_apertura'],
            'fecha_apertura' => now(),
            'fecha_cierre' => null,
            'monto_cierre' => null,
        ]);

        return response()->json([
            'data' => $apertura->load('user'),
            'message' => 'Caja aperturada exitosamente',
        ], 201);
    }

    /**
     * POST /api/cajas/{id}/cerrar
     * Cierra una caja abierta
     */
    public function cerrar(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'message' => 'No tienes sesión activa',
                ],
            ], 401);
        }

        $validated = $request->validate([
            'monto_cierre' => 'required|numeric|min:0',
        ]);

        $apertura = AperturaYCierreCaja::where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('fecha_cierre')
            ->first();

        if (!$apertura) {
            return response()->json([
                'error' => [
                    'message' => 'No se encontró una caja abierta para cerrar',
                ],
            ], 404);
        }

        $apertura->update([
            'fecha_cierre' => now(),
            'monto_cierre' => $validated['monto_cierre'],
        ]);

        return response()->json([
            'data' => $apertura->load('user'),
            'message' => 'Caja cerrada exitosamente',
        ]);
    }

    /**
     * GET /api/cajas/activa
     * Obtiene la caja activa del usuario autenticado
     */
    public function cajaActiva(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'message' => 'No tienes sesión activa',
                ],
            ], 401);
        }

        $cajaActiva = AperturaYCierreCaja::where('user_id', $user->id)
            ->whereNull('fecha_cierre')
            ->with('user')
            ->first();

        if (!$cajaActiva) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $cajaActiva,
        ]);
    }

    /**
     * GET /api/cajas/historial
     * Lista el historial de aperturas/cierres de caja
     */
    public function historial(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'message' => 'No tienes sesión activa',
                ],
            ], 401);
        }

        $query = AperturaYCierreCaja::where('user_id', $user->id)
            ->with('user')
            ->orderBy('fecha_apertura', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $cajas = $query->paginate($perPage);

        return response()->json($cajas);
    }
}
