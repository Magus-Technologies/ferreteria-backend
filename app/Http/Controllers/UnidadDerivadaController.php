<?php

namespace App\Http\Controllers;

use App\Models\UnidadDerivada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadDerivadaController extends Controller
{
    /**
     * Obtener todas las unidades derivadas
     */
    public function index(Request $request): JsonResponse
    {
        $query = UnidadDerivada::query();

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
     * Obtener una unidad derivada por ID
     */
    public function show($id): JsonResponse
    {
        $unidad = UnidadDerivada::findOrFail($id);
        return response()->json(['data' => $unidad]);
    }

    /**
     * Crear una nueva unidad derivada
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:unidadderivada,name',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? true;

        $unidad = UnidadDerivada::create($validated);

        return response()->json([
            'data' => $unidad,
            'message' => 'Unidad derivada creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar una unidad derivada
     */
    public function update(Request $request, $id): JsonResponse
    {
        $unidad = UnidadDerivada::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:unidadderivada,name,' . $id,
            'estado' => 'nullable|boolean',
        ]);

        $unidad->update($validated);

        return response()->json([
            'data' => $unidad,
            'message' => 'Unidad derivada actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar (desactivar) una unidad derivada
     */
    public function destroy($id): JsonResponse
    {
        $unidad = UnidadDerivada::findOrFail($id);
        $unidad->update(['estado' => false]);

        return response()->json([
            'message' => 'Unidad derivada desactivada exitosamente',
        ]);
    }
}
