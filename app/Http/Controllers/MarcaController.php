<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Filtrar marcas que tengan al menos un producto en las categorías dadas
        // (para que el selector de marcas se acote a la categoría elegida en los vales).
        if ($request->filled('categoria_ids')) {
            $categoriaIds = array_filter(array_map('intval', (array) $request->input('categoria_ids')));
            if (!empty($categoriaIds)) {
                $query->whereExists(function ($q) use ($categoriaIds) {
                    $q->select(DB::raw(1))
                        ->from('producto')
                        ->whereColumn('producto.marca_id', 'marca.id')
                        ->whereIn('producto.categoria_id', $categoriaIds);
                });
            }
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
