<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producto::with(['categoria', 'marca', 'unidadMedida']);

        // Filtros opcionales
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cod_producto', 'like', "%{$search}%")
                  ->orWhere('cod_barra', 'like', "%{$search}%");
            });
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->has('marca_id')) {
            $query->where('marca_id', $request->marca_id);
        }

        $perPage = $request->get('per_page', 15);
        $productos = $query->latest()->paginate($perPage);

        return response()->json($productos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cod_producto' => 'required|string|unique:producto',
            'cod_barra' => 'nullable|string|unique:producto',
            'name' => 'required|string|unique:producto',
            'name_ticket' => 'required|string',
            'categoria_id' => 'required|exists:categoria,id',
            'marca_id' => 'required|exists:marca,id',
            'unidad_medida_id' => 'required|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'img' => 'nullable|string',
            'ficha_tecnica' => 'nullable|string',
            'stock_min' => 'required|numeric|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'unidades_contenidas' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'permitido' => 'boolean',
        ]);

        $producto = Producto::create($validated);
        $producto->load(['categoria', 'marca', 'unidadMedida']);

        return response()->json($producto, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $producto = Producto::with([
            'categoria',
            'marca',
            'unidadMedida',
            'productoEnAlmacenes.almacen',
            'productoEnAlmacenes.ubicacion',
            'productoEnAlmacenes.unidadesDerivadas',
        ])->findOrFail($id);

        return response()->json($producto);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $producto = Producto::findOrFail($id);

        $validated = $request->validate([
            'cod_producto' => 'sometimes|string|unique:producto,cod_producto,' . $id,
            'cod_barra' => 'nullable|string|unique:producto,cod_barra,' . $id,
            'name' => 'sometimes|string|unique:producto,name,' . $id,
            'name_ticket' => 'sometimes|string',
            'categoria_id' => 'sometimes|exists:categoria,id',
            'marca_id' => 'sometimes|exists:marca,id',
            'unidad_medida_id' => 'sometimes|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'img' => 'nullable|string',
            'ficha_tecnica' => 'nullable|string',
            'stock_min' => 'sometimes|numeric|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'unidades_contenidas' => 'sometimes|numeric|min:0',
            'estado' => 'boolean',
            'permitido' => 'boolean',
        ]);

        $producto->update($validated);
        $producto->load(['categoria', 'marca', 'unidadMedida']);

        return response()->json($producto);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $producto = Producto::findOrFail($id);

        // Soft delete (cambiar estado)
        $producto->update(['estado' => false]);

        return response()->json([
            'message' => 'Producto desactivado exitosamente',
        ]);
    }
}
