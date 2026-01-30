<?php

namespace App\Http\Controllers;

use App\Models\MotivoTraslado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MotivoTrasladoController extends Controller
{
    /**
     * Listar todos los motivos de traslado
     * GET /api/motivos-traslado
     */
    public function index(Request $request)
    {
        try {
            $query = MotivoTraslado::query();

            // Filtrar solo activos si se especifica
            if ($request->has('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            // Búsqueda por código o descripción
            if ($request->has('search') && $request->search) {
                $query->search($request->search);
            }

            // Ordenar por código
            $query->orderByRaw('CAST(codigo AS UNSIGNED)');

            $motivos = $query->get();

            return response()->json([
                'success' => true,
                'data' => $motivos,
                'message' => 'Motivos de traslado obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener motivos de traslado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo motivo de traslado
     * POST /api/motivos-traslado
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo' => 'required|string|max:2|unique:motivos_traslado,codigo',
                'descripcion' => 'required|string|max:255',
                'activo' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $motivo = MotivoTraslado::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $motivo,
                'message' => 'Motivo de traslado creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear motivo de traslado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un motivo de traslado específico
     * GET /api/motivos-traslado/{id}
     */
    public function show($id)
    {
        try {
            $motivo = MotivoTraslado::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $motivo,
                'message' => 'Motivo de traslado obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Motivo de traslado no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar un motivo de traslado
     * PUT/PATCH /api/motivos-traslado/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $motivo = MotivoTraslado::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'codigo' => 'sometimes|string|max:2|unique:motivos_traslado,codigo,' . $id,
                'descripcion' => 'sometimes|string|max:255',
                'activo' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $motivo->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $motivo,
                'message' => 'Motivo de traslado actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar motivo de traslado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un motivo de traslado
     * DELETE /api/motivos-traslado/{id}
     */
    public function destroy($id)
    {
        try {
            $motivo = MotivoTraslado::findOrFail($id);
            $motivo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Motivo de traslado eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar motivo de traslado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

