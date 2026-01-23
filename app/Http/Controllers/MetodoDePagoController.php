<?php

namespace App\Http\Controllers;

use App\Models\MetodoDePago;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MetodoDePagoController extends Controller
{
    /**
     * GET /api/metodos-pago/agrupados-por-banco
     * Lista los métodos de pago agrupados por banco
     */
    public function agrupadosPorBanco(): JsonResponse
    {
        try {
            $metodosPago = MetodoDePago::with(['desplieguesDePagos' => function($query) {
                $query->where('activo', true)
                      ->where('mostrar', true);
            }])
            ->where('activo', true)
            ->get();

            $agrupados = $metodosPago->map(function($metodo) {
                return [
                    'banco_id' => $metodo->id,
                    'banco_nombre' => $metodo->name,
                    'cuenta_bancaria' => $metodo->cuenta_bancaria,
                    'tipos_pago' => $metodo->desplieguesDePagos->map(function($despliegue) {
                        return [
                            'id' => $despliegue->id,
                            'nombre' => $despliegue->name,
                            'adicional' => $despliegue->adicional,
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $agrupados,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métodos de pago agrupados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos los bancos/métodos de pago principales
     */
    public function index(Request $request): JsonResponse
    {
        $query = MetodoDePago::with('desplieguesDePagos');

        // Filtrar solo activos por defecto
        if (!$request->has('incluir_inactivos')) {
            $query->where('activo', true);
        }

        $items = $query->get();

        return response()->json([
            'data' => $items
        ]);
    }

    /**
     * Obtener un banco específico
     */
    public function show(string $id): JsonResponse
    {
        $item = MetodoDePago::with('desplieguesDePagos')->findOrFail($id);
        
        return response()->json([
            'data' => $item
        ]);
    }

    /**
     * Crear un nuevo banco/método de pago principal
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:metododepago,name',
            'cuenta_bancaria' => [
                'nullable',
                'string',
                'max:191',
                \Illuminate\Validation\Rule::unique('metododepago', 'cuenta_bancaria')
                    ->whereNotNull('cuenta_bancaria'),
            ],
            'nombre_titular' => 'nullable|string|max:191',
            'subcaja_id' => 'nullable|string|exists:subcaja,id',
        ], [
            'cuenta_bancaria.unique' => 'Este número de cuenta ya está registrado en otro banco',
        ]);

        // Generar ID único
        $validated['id'] = (string) \Illuminate\Support\Str::ulid();
        $validated['monto'] = 0;
        $validated['activo'] = true;

        $item = MetodoDePago::create($validated);

        return response()->json([
            'data' => $item,
            'message' => 'Banco creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar un banco
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:metododepago,name,' . $id,
            'cuenta_bancaria' => [
                'nullable',
                'string',
                'max:191',
                \Illuminate\Validation\Rule::unique('metododepago', 'cuenta_bancaria')
                    ->ignore($id)
                    ->whereNotNull('cuenta_bancaria'),
            ],
            'nombre_titular' => 'nullable|string|max:191',
        ], [
            'cuenta_bancaria.unique' => 'Este número de cuenta ya está registrado en otro banco',
        ]);

        $item = MetodoDePago::findOrFail($id);
        $item->update($validated);

        return response()->json([
            'data' => $item,
            'message' => 'Banco actualizado exitosamente'
        ]);
    }

    /**
     * Desactivar/Eliminar un banco
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $item = MetodoDePago::findOrFail($id);
            
            // Verificar si tiene despliegues de pago activos asociados
            $metodosActivos = $item->desplieguesDePagos()->where('activo', true)->count();
            
            if ($metodosActivos > 0) {
                // Solo desactivar
                $item->update(['activo' => false]);
                
                return response()->json([
                    'message' => 'Banco desactivado (tiene métodos activos asociados)'
                ]);
            }

            // Si no tiene métodos activos, se puede eliminar
            $item->delete();

            return response()->json([
                'message' => 'Banco eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se puede eliminar este banco'
            ], 400);
        }
    }
}
