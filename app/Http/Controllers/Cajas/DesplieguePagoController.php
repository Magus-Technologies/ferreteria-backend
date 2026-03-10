<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cajas\DesplieguePagoResource;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesplieguePagoController extends Controller
{
    public function __construct(
        private DesplieguePagoRepositoryInterface $desplieguePagoRepository
    ) {}

    /**
     * Listar todos los métodos de pago.
     * Acepta ?caja_principal_id=X para excluir despliegues ya usados por otras cajas.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cajaPrincipalId = $request->query('caja_principal_id') ? (int) $request->query('caja_principal_id') : null;
            $metodosPago = $this->desplieguePagoRepository->getAll($cajaPrincipalId);

            return response()->json([
                'success' => true,
                'data' => DesplieguePagoResource::collection($metodosPago),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar métodos de pago visibles (mostrar = 1).
     * Acepta ?caja_principal_id=X para excluir despliegues ya usados por otras cajas.
     */
    public function mostrar(Request $request): JsonResponse
    {
        try {
            $cajaPrincipalId = $request->query('caja_principal_id') ? (int) $request->query('caja_principal_id') : null;
            $metodosPago = $this->desplieguePagoRepository->getAllMostrar($cajaPrincipalId);

            return response()->json([
                'success' => true,
                'data' => DesplieguePagoResource::collection($metodosPago),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un método de pago por ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $metodoPago = $this->desplieguePagoRepository->findById($id);

            if (!$metodoPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new DesplieguePagoResource($metodoPago),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo método de pago
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'metodo_de_pago_id' => 'nullable|string|exists:metododepago,id',
                'adicional' => 'nullable|numeric|min:0',
                'mostrar' => 'nullable|boolean',
                'activo' => 'nullable|boolean',
                'requiere_numero_serie' => 'nullable|boolean',
                'sobrecargo_porcentaje' => 'nullable|numeric|min:0',
                'tipo_sobrecargo' => 'nullable|string|in:porcentaje,fijo',
                'numero_celular' => 'nullable|string|max:20',
                'cuenta_bancaria' => 'nullable|string|max:191',
                'nombre_titular' => 'nullable|string|max:191',
                'monto_inicial' => 'nullable|numeric|min:0',
                'subcaja_id' => 'nullable|string|exists:subcaja,id',
            ]);

            // Validar que el número de celular sea único si se proporciona
            if (!empty($validated['numero_celular'])) {
                $existeCelular = \App\Models\DespliegueDePago::where('numero_celular', $validated['numero_celular'])
                    ->exists();
                
                if ($existeCelular) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este número de celular ya está registrado en otro método de pago',
                        'errors' => [
                            'numero_celular' => ['Este número de celular ya está registrado']
                        ]
                    ], 422);
                }
            }

            $metodoPago = $this->desplieguePagoRepository->create($validated);

            return response()->json([
                'success' => true,
                'data' => new DesplieguePagoResource($metodoPago),
                'message' => 'Método de pago creado exitosamente',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un método de pago
     */
    public function update(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validated = $request->validate([
                'name' => 'nullable|string|max:191',
                'metodo_de_pago_id' => 'nullable|string|exists:metododepago,id',
                'adicional' => 'nullable|numeric|min:0',
                'mostrar' => 'nullable|boolean',
                'activo' => 'nullable|boolean',
                'requiere_numero_serie' => 'nullable|boolean',
                'sobrecargo_porcentaje' => 'nullable|numeric|min:0',
                'tipo_sobrecargo' => 'nullable|string|in:porcentaje,fijo',
                'numero_celular' => 'nullable|string|max:20',
            ]);

            // Validar que el número de celular sea único si se proporciona (excluyendo el actual)
            if (!empty($validated['numero_celular'])) {
                $existeCelular = \App\Models\DespliegueDePago::where('numero_celular', $validated['numero_celular'])
                    ->where('id', '!=', $id)
                    ->exists();
                
                if ($existeCelular) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este número de celular ya está registrado en otro método de pago',
                        'errors' => [
                            'numero_celular' => ['Este número de celular ya está registrado']
                        ]
                    ], 422);
                }
            }

            $metodoPago = $this->desplieguePagoRepository->update($id, $validated);

            if (!$metodoPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new DesplieguePagoResource($metodoPago),
                'message' => 'Método de pago actualizado exitosamente',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un método de pago
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $deleted = $this->desplieguePagoRepository->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Método de pago eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar métodos de pago agrupados por banco
     */
    public function agrupadosPorBanco(): JsonResponse
    {
        try {
            $metodosPago = $this->desplieguePagoRepository->getAll();

            // Agrupar por banco (name)
            $agrupados = $metodosPago->groupBy('name')->map(function ($items, $bancoNombre) {
                $primerItem = $items->first();
                return [
                    'banco_id' => $primerItem->id,
                    'banco_nombre' => $bancoNombre,
                    'cuenta_bancaria' => $primerItem->cuenta_bancaria,
                    'tipos_pago' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'nombre' => $item->name,
                            'adicional' => $item->adicional ?? '',
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
