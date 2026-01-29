<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    /**
     * Obtener todas las marcas
     */
    public function index(Request $request): JsonResponse
    {
        $query = Marca::query();

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

        $marcas = $query->get(['id', 'name', 'estado']);

        return response()->json(['data' => $marcas]);
    }

    /**
     * Obtener una marca por ID
     */
    public function show($id): JsonResponse
    {
        $marca = Marca::findOrFail($id);
        return response()->json(['data' => $marca]);
    }

    /**
     * Crear una nueva marca
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:marca,name',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? true;

        $marca = Marca::create($validated);

        return response()->json([
            'data' => $marca,
            'message' => 'Marca creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar una marca
     */
    public function update(Request $request, $id): JsonResponse
    {
        $marca = Marca::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:marca,name,' . $id,
            'estado' => 'nullable|boolean',
        ]);

        $marca->update($validated);

        return response()->json([
            'data' => $marca,
            'message' => 'Marca actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar (desactivar) una marca
     */
    public function destroy($id): JsonResponse
    {
        $marca = Marca::findOrFail($id);
        $marca->update(['estado' => false]);

        return response()->json([
            'message' => 'Marca desactivada exitosamente',
        ]);
    }
}
