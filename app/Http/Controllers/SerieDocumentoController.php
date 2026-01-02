<?php

namespace App\Http\Controllers;

use App\Models\SerieDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SerieDocumentoController extends Controller
{
    /**
     * GET /api/series-documentos
     * Lista todas las series de documentos con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = SerieDocumento::query();

        // Filtro por almacén
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        // Filtro por tipo de documento
        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        // Filtro por estado activo/inactivo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $series = $query->with('almacen')
            ->orderBy('tipo_documento')
            ->orderBy('serie')
            ->get();

        return response()->json([
            'data' => $series,
        ]);
    }

    /**
     * POST /api/series-documentos
     * Crea una nueva serie de documento
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_documento' => 'required|string|in:01,03,nv,in,sa,rc',
            'serie' => [
                'required',
                'string',
                'regex:/^[A-Z0-9]{4}$/',
            ],
            'correlativo' => 'integer|min:0',
            'almacen_id' => 'required|integer|exists:almacenes,id',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        // Verificar que no exista la misma combinación
        $existe = SerieDocumento::where('tipo_documento', $request->tipo_documento)
            ->where('serie', $request->serie)
            ->where('almacen_id', $request->almacen_id)
            ->exists();

        if ($existe) {
            return response()->json([
                'error' => [
                    'message' => 'Ya existe una serie con estos datos',
                ],
            ], 422);
        }

        $serie = SerieDocumento::create([
            'tipo_documento' => $request->tipo_documento,
            'serie' => $request->serie,
            'correlativo' => $request->correlativo ?? 0,
            'almacen_id' => $request->almacen_id,
            'activo' => $request->activo ?? true,
        ]);

        return response()->json([
            'data' => $serie->load('almacen'),
            'message' => 'Serie creada exitosamente',
        ], 201);
    }

    /**
     * GET /api/series-documentos/{id}
     * Obtiene una serie específica
     */
    public function show($id)
    {
        $serie = SerieDocumento::with('almacen')->findOrFail($id);

        return response()->json([
            'data' => $serie,
        ]);
    }

    /**
     * PUT /api/series-documentos/{id}
     * Actualiza una serie existente
     */
    public function update(Request $request, $id)
    {
        $serie = SerieDocumento::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo_documento' => 'string|in:01,03,nv,in,sa,rc',
            'serie' => [
                'string',
                'regex:/^[A-Z0-9]{4}$/',
            ],
            'correlativo' => 'integer|min:0',
            'almacen_id' => 'integer|exists:almacenes,id',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $serie->update($request->only([
            'tipo_documento',
            'serie',
            'correlativo',
            'almacen_id',
            'activo',
        ]));

        return response()->json([
            'data' => $serie->load('almacen'),
            'message' => 'Serie actualizada exitosamente',
        ]);
    }

    /**
     * DELETE /api/series-documentos/{id}
     * Elimina una serie
     */
    public function destroy($id)
    {
        $serie = SerieDocumento::findOrFail($id);
        $serie->delete();

        return response()->json([
            'message' => 'Serie eliminada exitosamente',
        ]);
    }

    /**
     * GET /api/series-documentos/siguiente-numero/preview
     * Obtiene el siguiente número sin incrementar el correlativo
     */
    public function siguienteNumero(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_documento' => 'required|string|in:01,03,nv,in,sa,rc',
            'almacen_id' => 'required|integer|exists:almacenes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => 'Parámetros inválidos',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $serie = SerieDocumento::where('tipo_documento', $request->tipo_documento)
            ->where('almacen_id', $request->almacen_id)
            ->where('activo', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$serie) {
            return response()->json([
                'error' => [
                    'message' => 'No se encontró una serie activa para este tipo de documento y almacén',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'serie' => $serie->serie,
                'numero' => $serie->correlativo + 1,
            ],
        ]);
    }
}
