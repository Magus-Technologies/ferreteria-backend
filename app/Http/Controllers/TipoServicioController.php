<?php

namespace App\Http\Controllers;

use App\Models\TipoServicio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TipoServicioController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoServicio::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $tipos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_servicio,nombre',
            'descripcion' => 'nullable|string|max:500',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.unique' => 'Este tipo de servicio ya existe',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres',
        ]);

        $tipo = TipoServicio::create($validated);

        return response()->json([
            'data' => $tipo,
            'message' => 'Tipo de servicio creado exitosamente',
        ], 201);
    }

    public function show(TipoServicio $tipoServicio): JsonResponse
    {
        return response()->json([
            'data' => $tipoServicio,
        ]);
    }

    public function update(Request $request, TipoServicio $tipoServicio): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_servicio,nombre,' . $tipoServicio->id,
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'nullable|boolean',
        ]);

        $tipoServicio->update($validated);

        return response()->json([
            'data' => $tipoServicio,
            'message' => 'Tipo de servicio actualizado exitosamente',
        ]);
    }

    public function destroy(TipoServicio $tipoServicio): JsonResponse
    {
        $tipoServicio->update(['activo' => false]);

        return response()->json([
            'message' => 'Tipo de servicio eliminado exitosamente',
        ]);
    }
}
