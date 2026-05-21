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

        $vehiculos = $query->get(['id', 'name', 'tipo', 'marca_modelo', 'placa', 'estado']);

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
            'marca_modelo' => 'nullable|string|max:100',
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
            'marca_modelo' => 'nullable|string|max:100',
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

    /**
     * Verificar disponibilidad del vehículo en una fecha
     */
    public function verificarDisponibilidad(Request $request, $id): JsonResponse
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        $vehiculo = Vehiculo::findOrFail($id);
        $fecha = $request->input('fecha');

        // Verificar si hay mantenimiento programado para esa fecha
        $mantenimiento = \App\Models\VehiculoMantenimiento::where('vehiculo_id', $id)
            ->where('estado', 'aprobado')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->first();

        if ($mantenimiento) {
            return response()->json([
                'disponible' => false,
                'razon' => "El vehículo está en mantenimiento desde " . 
                    \Carbon\Carbon::parse($mantenimiento->fecha_inicio)->format('d/m/Y') . 
                    " hasta " . 
                    \Carbon\Carbon::parse($mantenimiento->fecha_fin)->format('d/m/Y'),
            ]);
        }

        return response()->json([
            'disponible' => true,
        ]);
    }
}
