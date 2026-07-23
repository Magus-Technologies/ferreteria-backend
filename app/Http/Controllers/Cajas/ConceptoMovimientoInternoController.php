<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Models\ConceptoMovimientoInterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD del catálogo de conceptos de movimiento interno (solo nombre).
 */
class ConceptoMovimientoInternoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ConceptoMovimientoInterno::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Normalizar ANTES de validar para que el unique compare contra el
        // valor que realmente se guarda (siempre en MAYÚSCULAS).
        $request->merge(['nombre' => mb_strtoupper(trim((string) $request->input('nombre')))]);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:conceptos_movimiento_interno,nombre'],
        ], [
            'nombre.required' => 'El nombre del concepto es requerido',
            'nombre.unique' => 'Ya existe un concepto con ese nombre',
        ]);

        $concepto = ConceptoMovimientoInterno::create([
            'nombre' => $validated['nombre'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Concepto creado correctamente',
            'data' => $concepto,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $concepto = ConceptoMovimientoInterno::find($id);

        if (!$concepto) {
            return response()->json([
                'success' => false,
                'message' => 'Concepto no encontrado',
            ], 404);
        }

        $concepto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Concepto eliminado correctamente',
        ]);
    }
}
