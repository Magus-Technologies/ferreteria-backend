<?php

namespace App\Http\Controllers;

use App\Models\DespliegueDePago;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DespliegueDePagoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DespliegueDePago::query();

        // Filter by mostrar if provided
        if ($request->has('mostrar')) {
            $query->where('mostrar', $request->boolean('mostrar'));
        }

        $items = $query->get();

        return response()->json([
            'data' => $items
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = DespliegueDePago::findOrFail($id);
        
        return response()->json([
            'data' => $item
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:desplieguedepago,name',
            'metodo_de_pago_id' => 'nullable|string|exists:metododepago,id',
            'requiere_numero_serie' => 'sometimes|boolean',
            'sobrecargo_porcentaje' => 'sometimes|numeric|min:0|max:100',
            'tipo_sobrecargo' => 'sometimes|in:porcentaje,monto_fijo,ninguno',
            'adicional' => 'sometimes|numeric|min:0',
            'mostrar' => 'sometimes|boolean',
        ]);

        // Generar ID único
        $validated['id'] = (string) \Illuminate\Support\Str::ulid();
        
        // Si no se proporciona metodo_de_pago_id, usar uno por defecto o el primero disponible
        if (empty($validated['metodo_de_pago_id'])) {
            $metodoDefault = \App\Models\MetodoDePago::first();
            if ($metodoDefault) {
                $validated['metodo_de_pago_id'] = $metodoDefault->id;
            }
        }

        $item = DespliegueDePago::create($validated);

        return response()->json([
            'data' => $item,
            'message' => 'Método de pago creado exitosamente'
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'requiere_numero_serie' => 'sometimes|boolean',
            'sobrecargo_porcentaje' => 'sometimes|numeric|min:0|max:100',
            'tipo_sobrecargo' => 'sometimes|in:porcentaje,monto_fijo,ninguno',
            'adicional' => 'sometimes|numeric|min:0',
            'mostrar' => 'sometimes|boolean',
        ]);

        $item = DespliegueDePago::findOrFail($id);
        $item->update($validated);

        return response()->json([
            'data' => $item,
            'message' => 'Método de pago actualizado exitosamente'
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $item = DespliegueDePago::findOrFail($id);
            $item->delete();

            return response()->json([
                'message' => 'Método de pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se puede eliminar este método de pago porque está siendo usado en ventas o sub-cajas'
            ], 400);
        }
    }
}
