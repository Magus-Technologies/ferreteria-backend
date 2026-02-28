<?php

namespace App\Http\Controllers;

use App\Models\DespliegueDePago;
use App\Http\Resources\Cajas\DesplieguePagoResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DespliegueDePagoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DespliegueDePago::with('metodoDePago');

        // Filtrar solo activos por defecto
        if (!$request->has('incluir_inactivos')) {
            $query->where('activo', true);
        }

        // Filter by mostrar if provided
        if ($request->has('mostrar')) {
            $query->where('mostrar', $request->boolean('mostrar'));
        }

        // Filter by metodo_de_pago_id if provided
        if ($request->has('metodo_de_pago_id')) {
            $query->where('metodo_de_pago_id', $request->input('metodo_de_pago_id'));
        }

        // Excluir métodos ya usados por sub-cajas de una caja principal específica
        if ($request->has('exclude_used_by_caja_principal_id')) {
            $cajaPrincipalId = $request->input('exclude_used_by_caja_principal_id');

            // Obtener todos los despliegue_pago_ids ya asignados a sub-cajas de esta caja principal
            $usedConfigs = \App\Models\SubCaja::where('caja_principal_id', $cajaPrincipalId)
                ->where('estado', true)
                ->get()
                ->pluck('despliegues_pago_ids');

            $usedIds = $usedConfigs->flatten()->filter()->unique()->toArray();
            $hasWildcard = $usedConfigs->map(fn($ids) => in_array('*', (array)$ids))->contains(true);

            // Validar exclusividad de métodos de pago
            if ($hasWildcard) {
                // Si ya hay una caja que acepta TODO (*), no permitir ningún método adicional
                $query->whereRaw('1 = 0');
            } elseif (!empty($usedIds)) {
                // Excluir todos los métodos ya usados, incluyendo efectivo (sin excepciones)
                $query->whereNotIn('id', $usedIds);
            }
        }

        $items = $query->get();

        return response()->json([
            'data' => DesplieguePagoResource::collection($items)
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = DespliegueDePago::with('metodoDePago')->findOrFail($id);

        return response()->json([
            'data' => new DesplieguePagoResource($item)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
            ],
            'metodo_de_pago_id' => 'nullable|string|exists:metododepago,id',
            'requiere_numero_serie' => 'sometimes|boolean',
            'sobrecargo_porcentaje' => 'sometimes|numeric|min:0|max:100',
            'tipo_sobrecargo' => 'sometimes|in:porcentaje,monto_fijo,ninguno',
            'adicional' => 'sometimes|numeric|min:0',
            'mostrar' => 'sometimes|boolean',
            'numero_celular' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/', // Solo números, +, -, espacios y paréntesis
                \Illuminate\Validation\Rule::unique('desplieguedepago', 'numero_celular')
                    ->whereNotNull('numero_celular'),
            ],
        ], [
            'numero_celular.unique' => 'Este número de celular ya está registrado en otro método de pago',
            'numero_celular.regex' => 'El número de celular solo puede contener números y símbolos +, -, ( )',
        ]);

        // Si no se proporciona metodo_de_pago_id, crear uno genérico
        if (empty($validated['metodo_de_pago_id'])) {
            // Buscar o crear método de pago genérico "Sin Banco"
            $metodoGenerico = \App\Models\MetodoDePago::firstOrCreate(
                ['name' => 'Sin Banco'],
                [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'monto' => 0,
                    'activo' => true,
                ]
            );
            $validated['metodo_de_pago_id'] = $metodoGenerico->id;
        }

        // Validar que no exista el mismo nombre para el mismo banco
        $existeDuplicado = DespliegueDePago::where('name', $validated['name'])
            ->where('metodo_de_pago_id', $validated['metodo_de_pago_id'])
            ->exists();

        if ($existeDuplicado) {
            return response()->json([
                'message' => 'Ya existe un método con este nombre para este banco',
                'errors' => [
                    'name' => ['Ya existe un método con este nombre para este banco']
                ]
            ], 422);
        }

        // Generar ID único
        $validated['id'] = (string) \Illuminate\Support\Str::ulid();
        $validated['activo'] = true;

        $item = DespliegueDePago::create($validated);

        return response()->json([
            'data' => $item,
            'message' => 'Método de pago creado exitosamente'
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $item = DespliegueDePago::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191|unique:desplieguedepago,name,' . $id,
            'requiere_numero_serie' => 'sometimes|boolean',
            'sobrecargo_porcentaje' => 'sometimes|numeric|min:0|max:100',
            'tipo_sobrecargo' => 'sometimes|in:porcentaje,monto_fijo,ninguno',
            'adicional' => 'sometimes|numeric|min:0',
            'mostrar' => 'sometimes|boolean',
            'numero_celular' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                \Illuminate\Validation\Rule::unique('desplieguedepago', 'numero_celular')
                    ->ignore($id)
                    ->whereNotNull('numero_celular'),
            ],
        ], [
            'numero_celular.unique' => 'Este número de celular ya está registrado en otro método de pago',
            'numero_celular.regex' => 'El número de celular solo puede contener números y símbolos +, -, ( )',
        ]);

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

            // Verificar si está siendo usado en ventas o transacciones
            $enUso = DB::table('desplieguedepagoventa')
                ->where('despliegue_de_pago_id', $id)
                ->exists();

            if ($enUso) {
                // Solo desactivar
                $item->update(['activo' => false]);

                return response()->json([
                    'message' => 'Método desactivado (está siendo usado en ventas)'
                ]);
            }

            // Si no está en uso, se puede eliminar
            $item->delete();

            return response()->json([
                'message' => 'Método de pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se puede eliminar este método de pago'
            ], 400);
        }
    }
}
