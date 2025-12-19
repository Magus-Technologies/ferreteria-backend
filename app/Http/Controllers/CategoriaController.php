<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Obtener todas las categorías
     */
    public function index(Request $request): JsonResponse
    {
        $query = Categoria::query();

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

        $categorias = $query->get(['id', 'name', 'estado']);

        return response()->json(['data' => $categorias]);
    }

    /**
     * Obtener una categoría por ID
     */
    public function show($id): JsonResponse
    {
        $categoria = Categoria::findOrFail($id);
        return response()->json(['data' => $categoria]);
    }

    /**
     * Crear una nueva categoría
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:categoria,name',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? true;

        $categoria = Categoria::create($validated);

        return response()->json([
            'data' => $categoria,
            'message' => 'Categoría creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar una categoría
     */
    public function update(Request $request, $id): JsonResponse
    {
        $categoria = Categoria::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:categoria,name,' . $id,
            'estado' => 'nullable|boolean',
        ]);

        $categoria->update($validated);

        return response()->json([
            'data' => $categoria,
            'message' => 'Categoría actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar (desactivar) una categoría
     */
    public function destroy($id): JsonResponse
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->update(['estado' => false]);

        return response()->json([
            'message' => 'Categoría desactivada exitosamente',
        ]);
    }
}
