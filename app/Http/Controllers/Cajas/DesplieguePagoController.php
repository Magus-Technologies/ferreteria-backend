<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cajas\DesplieguePagoResource;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use Illuminate\Http\JsonResponse;

class DesplieguePagoController extends Controller
{
    public function __construct(
        private DesplieguePagoRepositoryInterface $desplieguePagoRepository
    ) {}

    /**
     * Listar todos los métodos de pago
     */
    public function index(): JsonResponse
    {
        try {
            $metodosPago = $this->desplieguePagoRepository->getAll();

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
     * Listar métodos de pago visibles (mostrar = 1)
     */
    public function mostrar(): JsonResponse
    {
        try {
            $metodosPago = $this->desplieguePagoRepository->getAllMostrar();

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
            $metodoPago = $this->desplieguePagoRepository->create($request->all());

            return response()->json([
                'success' => true,
                'data' => new DesplieguePagoResource($metodoPago),
                'message' => 'Método de pago creado exitosamente',
            ], 201);

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
            $metodoPago = $this->desplieguePagoRepository->update($id, $request->all());

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
