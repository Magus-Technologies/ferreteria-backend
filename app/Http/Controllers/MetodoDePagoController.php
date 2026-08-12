<?php

namespace App\Http\Controllers;

use App\Models\MetodoDePago;
use App\Queries\ResumenBancoQuery;
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
            'name' => 'required|string|max:191',
            'cuenta_bancaria' => 'nullable|string|max:191',
            'nombre_titular' => 'nullable|string|max:191',
            'monto_inicial' => 'nullable|numeric|min:0',
        ], [
            'monto_inicial.numeric' => 'El monto inicial debe ser un número válido',
            'monto_inicial.min' => 'El monto inicial no puede ser negativo',
        ]);

        // Si no se proporciona cuenta bancaria, usar un valor por defecto
        if (empty($validated['cuenta_bancaria'])) {
            $validated['cuenta_bancaria'] = 'SIN-CUENTA';
        }

        // Validar que la combinación banco + cuenta sea única
        $existe = MetodoDePago::where('name', $validated['name'])
            ->where('cuenta_bancaria', $validated['cuenta_bancaria'])
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe un método de pago con este banco y número de cuenta',
                'errors' => [
                    'cuenta_bancaria' => ['Ya existe un método de pago con este banco y número de cuenta']
                ]
            ], 422);
        }

        // Generar ID único
        $validated['id'] = (string) \Illuminate\Support\Str::ulid();
        
        // Si se proporciona monto inicial, establecer tanto monto como monto_inicial
        $montoInicial = $validated['monto_inicial'] ?? 0;
        $validated['monto'] = $montoInicial;
        $validated['monto_inicial'] = $montoInicial;
        $validated['activo'] = true;

        $item = MetodoDePago::create($validated);

        // NOTA: Los métodos de pago (DespliegueDePago) deben crearse explícitamente
        // a través del DespliegueDePagoController, no automáticamente aquí.
        // Esto permite que el usuario tenga control total sobre qué métodos crear.

        // Asentar el monto inicial en el libro. Si el banco todavía no tiene
        // despliegues ni sub-caja que lo acepte no hace nada, y quedará registrado
        // al crear la sub-caja (ver CajaService::registrarMontoInicialSiAplica).
        app(\App\Services\Implementations\CajaService::class)->sincronizarMontoInicialBanco($item);

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
            'name' => 'required|string|max:191',
            'cuenta_bancaria' => 'nullable|string|max:191',
            'nombre_titular' => 'nullable|string|max:191',
            'monto_inicial' => 'nullable|numeric|min:0',
        ]);

        // Si no se proporciona cuenta bancaria, usar un valor por defecto
        if (empty($validated['cuenta_bancaria'])) {
            $validated['cuenta_bancaria'] = 'SIN-CUENTA';
        }

        // Validar que la combinación banco + cuenta sea única (excluyendo el registro actual)
        $existe = MetodoDePago::where('name', $validated['name'])
            ->where('cuenta_bancaria', $validated['cuenta_bancaria'])
            ->where('id', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe un método de pago con este banco y número de cuenta',
                'errors' => [
                    'cuenta_bancaria' => ['Ya existe un método de pago con este banco y número de cuenta']
                ]
            ], 422);
        }

        $item = MetodoDePago::findOrFail($id);
        
        // Si se actualiza el monto_inicial, también actualizar el monto
        if (isset($validated['monto_inicial'])) {
            $validated['monto'] = $validated['monto_inicial'];
        }
        
        $item->update($validated);

        // Reflejar el cambio en el libro: se asienta la DIFERENCIA contra lo ya
        // registrado, así editarlo varias veces no lo duplica. Sin esto, cambiar el
        // monto inicial solo tocaba las columnas del banco y la sub-caja seguía
        // mostrando el saldo viejo (los saldos se recalculan desde `transacciones_caja`).
        app(\App\Services\Implementations\CajaService::class)->sincronizarMontoInicialBanco($item);

        return response()->json([
            'data' => $item->fresh(),
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

    /**
     * GET /api/metodos-de-pago/{id}/resumen-detallado
     * Obtener resumen detallado de un banco con filtros
     */
    public function getResumenDetallado(string $id, Request $request): JsonResponse
    {
        try {
            $query = new ResumenBancoQuery();
            
            $data = $query->obtenerResumenDetallado(
                $id,
                $request->input('fecha_inicio'),
                $request->input('fecha_fin'),
                $request->input('vendedor_id'),
                $request->input('sub_caja_id'),
                $request->input('despliegue_pago_id')
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen detallado del banco',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
