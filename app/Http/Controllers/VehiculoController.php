<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    /**
     * Obtener todos los vehículos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehiculo::query();

        if ($request->has('estado')) {
            $query->where('estado', $request->boolean('estado'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $query->orderBy('name', 'asc');

        $vehiculos = $query->get(['id', 'name', 'tipo', 'placa', 'estado']);

        return response()->json(['data' => $vehiculos]);
    }

    /**
     * Obtener un vehículo por ID
     */
    public function show($id): JsonResponse
    {
        $vehiculo = Vehiculo::findOrFail($id);

        return response()->json(['data' => $vehiculo]);
    }

    /**
     * Crear un nuevo vehículo
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'tipo' => 'required|string|max:50',
            'placa' => 'nullable|string|max:20',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? true;

        $vehiculo = Vehiculo::create($validated);

        return response()->json($vehiculo, 201);
    }

    /**
     * Actualizar un vehículo
     */
    public function update(Request $request, $id): JsonResponse
    {
        $vehiculo = Vehiculo::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'tipo' => 'sometimes|string|max:50',
            'placa' => 'nullable|string|max:20',
            'estado' => 'nullable|boolean',
        ]);

        $vehiculo->update($validated);

        return response()->json($vehiculo);
    }

    /**
     * Eliminar (desactivar) un vehículo
     */
    public function destroy($id): JsonResponse
    {
        $vehiculo = Vehiculo::findOrFail($id);
        $vehiculo->update(['estado' => false]);

        return response()->json([
            'message' => 'Vehículo desactivado exitosamente',
        ]);
    }
}
