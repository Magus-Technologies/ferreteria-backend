<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    /**
     * Display a listing of almacenes.
     *
     * Required permission: ALMACEN_LISTADO
     */
    public function index(Request $request): JsonResponse
    {
        $query = Almacen::orderBy('name', 'asc');

        // Por defecto solo activos, pero si piden todos (para gestión)
        if (!$request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        $almacenes = $query->get();

        return response()->json([
            'data' => $almacenes,
        ]);
    }

    /**
     * Toggle activo status of an almacen.
     */
    public function toggleActivo(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);
        $almacen->update(['activo' => !$almacen->activo]);

        return response()->json([
            'data' => $almacen,
        ]);
    }

    /**
     * Store a newly created almacen.
     *
     * Required permission: ALMACEN_CREATE
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:almacen',
            'direccion' => 'nullable|string|max:500',
        ]);

        try {
            $almacen = Almacen::create($validated);

            return response()->json([
                'data' => $almacen,
            ], 201);
        } catch (QueryException $e) {
            // Manejo de error de duplicado (código 23000 para MySQL)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Ya existe un almacén con ese nombre',
                    'errors' => [
                        'name' => ['El nombre ya está en uso'],
                    ],
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        return response()->json([
            'data' => $almacen,
        ]);
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:almacen,name,' . $id,
            'direccion' => 'nullable|string|max:500',
        ]);

        try {
            $almacen->update($validated);

            return response()->json([
                'data' => $almacen,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Ya existe un almacén con ese nombre',
                    'errors' => [
                        'name' => ['El nombre ya está en uso'],
                    ],
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        // Verificar si tiene productos asociados
        if ($almacen->productosEnAlmacen()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el almacén porque tiene productos asociados',
            ], 422);
        }

        $almacen->delete();

        return response()->json([
            'message' => 'Almacén eliminado exitosamente',
        ]);
    }
}
