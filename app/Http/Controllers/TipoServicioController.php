<?php

namespace App\Http\Controllers;

use App\Models\TipoServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoServicioController extends Controller
{
    /**
     * Listar tipos de servicio
     * GET /api/tipos-servicio
     */
    public function index(Request $request)
    {
        try {
            $query = TipoServicio::query();

            if ($request->has('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            $tipos = $query->orderBy('nombre')->get();

            return response()->json(['data' => $tipos]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipos de servicio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear tipo de servicio
     * POST /api/tipos-servicio
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'      => 'required|string|max:100|unique:tipos_servicio,nombre',
                'descripcion' => 'nullable|string',
                'activo'      => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $tipo = TipoServicio::create($validator->validated());

            return response()->json([
                'data'    => $tipo,
                'message' => 'Tipo de servicio creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear tipo de servicio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipo de servicio por ID
     * GET /api/tipos-servicio/{id}
     */
    public function show($id)
    {
        try {
            $tipo = TipoServicio::findOrFail($id);
            return response()->json(['data' => $tipo]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'No encontrado'], 404);
        }
    }

    /**
     * Actualizar tipo de servicio
     * PUT /api/tipos-servicio/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $tipo = TipoServicio::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre'      => 'sometimes|string|max:100|unique:tipos_servicio,nombre,' . $id,
                'descripcion' => 'nullable|string',
                'activo'      => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $tipo->update($validator->validated());

            return response()->json([
                'data'    => $tipo,
                'message' => 'Tipo de servicio actualizado'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar'], 500);
        }
    }

    /**
     * Eliminar tipo de servicio
     * DELETE /api/tipos-servicio/{id}
     */
    public function destroy($id)
    {
        try {
            TipoServicio::findOrFail($id)->delete();
            return response()->json(['message' => 'Tipo de servicio eliminado']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar'], 500);
        }
    }
}
