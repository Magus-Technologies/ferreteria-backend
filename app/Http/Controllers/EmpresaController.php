<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class EmpresaController extends Controller
{
    /**
     * Obtener la empresa (normalmente solo hay una)
     */
    public function index(): JsonResponse
    {
        $empresa = Empresa::with([
            'almacenPredeterminado',
            'marcaPredeterminada',
            'ubigeo'
        ])->first();

        if (!$empresa) {
            return response()->json([
                'message' => 'No se encontró información de la empresa',
            ], 404);
        }

        return response()->json(['data' => $empresa]);
    }

    /**
     * Obtener la empresa por ID
     */
    public function show($id): JsonResponse
    {
        $empresa = Empresa::with([
            'almacenPredeterminado',
            'marcaPredeterminada',
            'ubigeo'
        ])->findOrFail($id);
        // Agregar URL completa del logo si existe
        if ($empresa->logo) {
            $empresa->logo_url = asset('storage/' . $empresa->logo);
        }

        return response()->json(['data' => $empresa]);
    }

    /**
     * Crear una nueva empresa
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'marca_id' => 'required|exists:marca,id',
            'serie_ingreso' => 'nullable|integer',
            'serie_salida' => 'nullable|integer',
            'serie_recepcion_almacen' => 'nullable|integer',
            'tipo_identificacion' => 'nullable|string|max:20',
            'ruc' => 'required|string|max:191',
            'razon_social' => 'required|string|max:191',
            'nombre_comercial' => 'nullable|string|max:191',
            'direccion' => 'required|string|max:191',
            'ubigeo_id' => 'nullable|exists:ubigeo_inei,id_ubigeo',
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'regimen' => 'nullable|string|max:100',
            'actividad_economica' => 'nullable|string|max:191',
            'telefono' => 'required|string|max:191',
            'celular' => 'nullable|string|max:50',
            'email' => 'required|email|max:191',
            // Logo
            'logo' => 'nullable|string|max:500',
            // Gerente o Administrador
            'gerente_nombre' => 'nullable|string|max:191',
            'gerente_email' => 'nullable|email|max:191',
            'gerente_celular' => 'nullable|string|max:50',
            // Facturación
            'facturacion_nombre' => 'nullable|string|max:191',
            'facturacion_email' => 'nullable|email|max:191',
            'facturacion_celular' => 'nullable|string|max:50',
            // Contabilidad
            'contabilidad_nombre' => 'nullable|string|max:191',
            'contabilidad_email' => 'nullable|email|max:191',
            'contabilidad_celular' => 'nullable|string|max:50',
        ]);

        // Asignar valores por defecto para las series
        $validated['serie_ingreso'] = $validated['serie_ingreso'] ?? 1;
        $validated['serie_salida'] = $validated['serie_salida'] ?? 1;
        $validated['serie_recepcion_almacen'] = $validated['serie_recepcion_almacen'] ?? 1;
        $validated['tipo_identificacion'] = $validated['tipo_identificacion'] ?? 'RUC';

        $empresa = Empresa::create($validated);

        return response()->json([
            'data' => $empresa->load(['almacenPredeterminado', 'marcaPredeterminada', 'ubigeo']),
            'message' => 'Empresa creada exitosamente',
        ], 201);
    }

    /**
     * Actualizar la empresa
     */
    public function update(Request $request, $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);

        $validated = $request->validate([
            'almacen_id' => 'sometimes|required|exists:almacenes,id',
            'marca_id' => 'sometimes|required|exists:marca,id',
            'serie_ingreso' => 'nullable|integer',
            'serie_salida' => 'nullable|integer',
            'serie_recepcion_almacen' => 'nullable|integer',
            'tipo_identificacion' => 'nullable|string|max:20',
            'ruc' => 'sometimes|required|string|max:191',
            'razon_social' => 'sometimes|required|string|max:191',
            'nombre_comercial' => 'nullable|string|max:191',
            'direccion' => 'sometimes|required|string|max:191',
            'ubigeo_id' => 'nullable|exists:ubigeo_inei,id_ubigeo',
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'regimen' => 'nullable|string|max:100',
            'actividad_economica' => 'nullable|string|max:191',
            'telefono' => 'sometimes|required|string|max:191',
            'celular' => 'nullable|string|max:50',
            'email' => 'sometimes|required|email|max:191',
            // Logo
            'logo' => 'nullable|file|image|max:2048',
            // Gerente o Administrador
            'gerente_nombre' => 'nullable|string|max:191',
            'gerente_email' => 'nullable|email|max:191',
            'gerente_celular' => 'nullable|string|max:50',
            // Facturación
            'facturacion_nombre' => 'nullable|string|max:191',
            'facturacion_email' => 'nullable|email|max:191',
            'facturacion_celular' => 'nullable|string|max:50',
            // Contabilidad
            'contabilidad_nombre' => 'nullable|string|max:191',
            'contabilidad_email' => 'nullable|email|max:191',
            'contabilidad_celular' => 'nullable|string|max:50',
        ]);
        // manejar subida del logo 
        if ($request->hasFile('logo')) {
            // Eliminar el logo anterior si existe
            if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
                Storage::disk('public')->delete($empresa->logo);
            }

            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $logoPath;
        }

        $empresa->update($validated);
        // Agregar URL completa del logo
        if ($empresa->logo) {
            $empresa->logo_url = asset('storage/' . $empresa->logo);
        }

        return response()->json([
            'data' => $empresa->load(['almacenPredeterminado', 'marcaPredeterminada', 'ubigeo']),
            'message' => 'Empresa actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar la empresa
     */
    public function destroy($id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return response()->json([
            'message' => 'Empresa eliminada exitosamente',
        ]);
    }
}
