<?php

namespace App\Http\Controllers;

use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadMedidaController extends Controller
{
    /**
     * Obtener todas las unidades de medida
     */
    public function index(Request $request): JsonResponse
    {
        $query = UnidadMedida::query();

        // Filtrar por estado si se especifica
        if ($request->has('estado')) {
            $query->where('estado', $request->boolean('estado'));
        }

        // Buscar por nombre
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Ordenar por nombre
        $query->orderBy('name', 'asc');

        $unidades = $query->get(['id', 'name', 'estado']);

        return response()->json(['data' => $unidades]);
    }

    /**
     * Obtener una unidad de medida por ID
     */
    public function show($id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);
        return response()->json(['data' => $unidad]);
    }

    /**
     * Crear una nueva unidad de medida
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:unidadmedida,name',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? true;

        $unidad = UnidadMedida::create($validated);

        return response()->json([
            'data' => $unidad,
            'message' => 'Unidad de medida creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar una unidad de medida
     */
    public function update(Request $request, $id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:unidadmedida,name,' . $id,
            'estado' => 'nullable|boolean',
        ]);

        $unidad->update($validated);

        return response()->json([
            'data' => $unidad,
            'message' => 'Unidad de medida actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar (desactivar) una unidad de medida
     */
    public function destroy($id): JsonResponse
    {
        $unidad = UnidadMedida::findOrFail($id);
        $unidad->update(['estado' => false]);

        return response()->json([
            'message' => 'Unidad de medida desactivada exitosamente',
        ]);
    }
}
