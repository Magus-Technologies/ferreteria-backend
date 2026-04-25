<?php

namespace App\Http\Controllers;

use App\Models\ProveedorCalificacion;
use App\Enums\EstadoProveedorCalificacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProveedorCalificacionController extends Controller
{
    /**
     * Listar calificaciones de un proveedor
     */
    public function index(int $proveedorId): JsonResponse
    {
        $calificaciones = ProveedorCalificacion::where('proveedor_id', $proveedorId)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $calificaciones
        ]);
    }

    /**
     * Crear calificación para un proveedor
     */
    public function store(Request $request, int $proveedorId): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:excelente,bueno,regular,problematico'],
            'razon' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $calificacion = ProveedorCalificacion::create([
            'proveedor_id' => $proveedorId,
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
        $calificacion = ProveedorCalificacion::findOrFail($calificacionId);

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
        $calificacion = ProveedorCalificacion::findOrFail($calificacionId);
        $calificacion->delete();

        return response()->json([
            'message' => 'Calificación eliminada exitosamente'
        ]);
    }

    /**
     * Obtener última calificación de un proveedor
     */
    public function ultimaCalificacion(int $proveedorId): JsonResponse
    {
        $calificacion = ProveedorCalificacion::where('proveedor_id', $proveedorId)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'data' => $calificacion
        ]);
    }

    /**
     * Obtener estados disponibles
     */
    public function estados(): JsonResponse
    {
        $estados = array_map(function (EstadoProveedorCalificacion $estado) {
            return [
                'value' => $estado->value,
                'label' => $estado->label(),
                'color' => $estado->color(),
            ];
        }, EstadoProveedorCalificacion::cases());

        return response()->json([
            'data' => $estados
        ]);
    }
}
