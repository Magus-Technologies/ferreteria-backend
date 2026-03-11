<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'activo' => 'sometimes|string|in:true,false,1,0',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Servicio::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->has('activo')) {
            $activo = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $activo);
        }

        $query->orderBy('nombre', 'asc');
        $perPage = $request->get('per_page', 50);
        $servicios = $query->paginate($perPage);

        return response()->json([
            'data' => $servicios->items(),
            'total' => $servicios->total(),
            'current_page' => $servicios->currentPage(),
            'per_page' => $servicios->perPage(),
            'last_page' => $servicios->lastPage(),
        ]);
    }

    public function show($id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'error' => ['message' => 'Servicio no encontrado'],
            ], 404);
        }

        return response()->json(['data' => $servicio]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:250',
            'precio' => 'required|numeric|min:0',
            'codigo_sunat' => 'nullable|string|max:50',
        ]);

        $servicio = Servicio::create($validated);

        return response()->json([
            'data' => $servicio,
            'message' => 'Servicio creado exitosamente',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'error' => ['message' => 'Servicio no encontrado'],
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:250',
            'precio' => 'sometimes|numeric|min:0',
            'codigo_sunat' => 'nullable|string|max:50',
            'activo' => 'sometimes|boolean',
        ]);

        $servicio->update($validated);

        return response()->json([
            'data' => $servicio,
            'message' => 'Servicio actualizado exitosamente',
        ]);
    }

    public function destroy($id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'error' => ['message' => 'Servicio no encontrado'],
            ], 404);
        }

        $servicio->delete();

        return response()->json([
            'message' => 'Servicio eliminado exitosamente',
        ]);
    }
}
