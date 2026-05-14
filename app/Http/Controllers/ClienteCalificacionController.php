<?php

namespace App\Http\Controllers;

use App\Models\ClienteCalificacion;
use App\Enums\EstadoClienteCalificacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClienteCalificacionController extends Controller
{
    /**
     * Listar calificaciones de un cliente
     */
    public function index(int $clienteId): JsonResponse
    {
        $calificaciones = ClienteCalificacion::where('cliente_id', $clienteId)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $calificaciones
        ]);
    }

    /**
     * Crear calificación para un cliente
     */
    public function store(Request $request, int $clienteId): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:excelente,bueno,regular,problematico'],
            'razon' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $calificacion = ClienteCalificacion::create([
            'cliente_id' => $clienteId,
            'estado' => $validated['estado'],
            'razon' => $validated['razon'] ?? null,
            'observacion' => $validated['observacion'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'data' => $calificacion->load('createdBy:id,name'),
            'message' => 'Calificación creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar calificación
     */
    public function update(Request $request, int $calificacionId): JsonResponse
    {
        $calificacion = ClienteCalificacion::findOrFail($calificacionId);

        $validated = $request->validate([
            'estado' => ['sometimes', 'string', 'in:excelente,bueno,regular,problematico'],
            'razon' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $calificacion->update($validated);

        return response()->json([
            'data' => $calificacion->load('createdBy:id,name'),
            'message' => 'Calificación actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar calificación
     */
    public function destroy(int $calificacionId): JsonResponse
    {
        $calificacion = ClienteCalificacion::findOrFail($calificacionId);
        $calificacion->delete();

        return response()->json([
            'message' => 'Calificación eliminada exitosamente'
        ]);
    }

    /**
     * Obtener última calificación de un cliente
     */
    public function ultimaCalificacion(int $clienteId): JsonResponse
    {
        $calificacion = ClienteCalificacion::where('cliente_id', $clienteId)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'data' => $calificacion
        ]);
    }

    /**
     * Obtener resumen global de últimas calificaciones por estado.
     */
    public function resumen(): JsonResponse
    {
        $subUltimas = ClienteCalificacion::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('cliente_id');

        $rows = ClienteCalificacion::query()
            ->from('cliente_calificaciones as cc')
            ->joinSub($subUltimas, 'ultimas', function ($join) {
                $join->on('cc.id', '=', 'ultimas.id');
            })
            ->select('cc.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('cc.estado')
            ->pluck('total', 'estado');

        return response()->json([
            'data' => [
                'excelente' => (int) ($rows['excelente'] ?? 0),
                'bueno' => (int) ($rows['bueno'] ?? 0),
                'regular' => (int) ($rows['regular'] ?? 0),
                'problematico' => (int) ($rows['problematico'] ?? 0),
            ],
        ]);
    }

    /**
     * Obtener estados disponibles
     */
    public function estados(): JsonResponse
    {
        $estados = array_map(function (EstadoClienteCalificacion $estado) {
            return [
                'value' => $estado->value,
                'label' => $estado->label(),
                'color' => $estado->color(),
            ];
        }, EstadoClienteCalificacion::cases());

        return response()->json([
            'data' => $estados
        ]);
    }
}
