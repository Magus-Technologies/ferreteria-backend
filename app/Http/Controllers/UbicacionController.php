<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    /**
     * Obtener todas las ubicaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ubicacion::query();

        // Filtrar por almacén (REQUERIDO)
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

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

        $ubicaciones = $query->get(['id', 'name', 'almacen_id', 'estado']);

        return response()->json(['data' => $ubicaciones]);
    }

    /**
     * Obtener una ubicación por ID
     */
    public function show($id): JsonResponse
    {
        $ubicacion = Ubicacion::findOrFail($id);
        return response()->json(['data' => $ubicacion]);
    }

    /**
     * Crear una nueva ubicación
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'almacen_id' => 'required|integer|exists:almacen,id',
            'estado' => 'nullable|boolean',
        ]);

        // Validar unicidad de name dentro del almacén
        $exists = Ubicacion::where('name', $validated['name'])
            ->where('almacen_id', $validated['almacen_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una ubicación con ese nombre en este almacén',
                'errors' => [
                    'name' => ['Ya existe una ubicación con ese nombre en este almacén']
                ]
            ], 422);
        }

        $validated['estado'] = $validated['estado'] ?? true;

        $ubicacion = Ubicacion::create($validated);

        return response()->json([
            'data' => $ubicacion,
            'message' => 'Ubicación creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar una ubicación
     */
    public function update(Request $request, $id): JsonResponse
    {
        $ubicacion = Ubicacion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'almacen_id' => 'required|integer|exists:almacen,id',
            'estado' => 'nullable|boolean',
        ]);

        // Validar unicidad de name dentro del almacén (excluyendo el actual)
        $exists = Ubicacion::where('name', $validated['name'])
            ->where('almacen_id', $validated['almacen_id'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una ubicación con ese nombre en este almacén',
                'errors' => [
                    'name' => ['Ya existe una ubicación con ese nombre en este almacén']
                ]
            ], 422);
        }

        $ubicacion->update($validated);

        return response()->json([
            'data' => $ubicacion,
            'message' => 'Ubicación actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar (desactivar) una ubicación
     */
    public function destroy($id): JsonResponse
    {
        $ubicacion = Ubicacion::findOrFail($id);
        $ubicacion->update(['estado' => false]);

        return response()->json([
            'message' => 'Ubicación desactivada exitosamente',
        ]);
    }
}
