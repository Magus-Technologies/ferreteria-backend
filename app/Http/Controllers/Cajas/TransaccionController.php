<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\RegistrarTransaccionRequest as HttpRegistrarTransaccionRequest;
use App\Http\Resources\Cajas\TransaccionResource;
use App\UseCases\RegistrarTransaccion\RegistrarTransaccionRequest;
use App\UseCases\RegistrarTransaccion\RegistrarTransaccionUseCase;
use App\Repositories\Interfaces\TransaccionCajaRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{
    public function __construct(
        private RegistrarTransaccionUseCase $registrarTransaccionUseCase,
        private TransaccionCajaRepositoryInterface $transaccionRepository
    ) {}

    /**
     * Listar transacciones de una sub-caja
     */
    public function index(Request $request, int $subCajaId): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            $transacciones = $this->transaccionRepository->getBySubCaja($subCajaId, $perPage);

            return response()->json([
                'success' => true,
                'data' => TransaccionResource::collection($transacciones->items()),
                'pagination' => [
                    'total' => $transacciones->total(),
                    'per_page' => $transacciones->perPage(),
                    'current_page' => $transacciones->currentPage(),
                    'last_page' => $transacciones->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar una nueva transacción
     */
    public function store(HttpRegistrarTransaccionRequest $request): JsonResponse
    {
        try {
            $useCaseRequest = new RegistrarTransaccionRequest(
                subCajaId: $request->validated('sub_caja_id'),
                tipoTransaccion: $request->validated('tipo_transaccion'),
                monto: $request->validated('monto'),
                descripcion: $request->validated('descripcion'),
                referenciaId: $request->validated('referencia_id'),
                referenciaTipo: $request->validated('referencia_tipo')
            );

            $response = $this->registrarTransaccionUseCase->execute($useCaseRequest);

            return response()->json([
                'success' => $response->success,
                'message' => $response->message,
                'data' => new TransaccionResource($response->transaccion),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener una transacción por ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $transaccion = $this->transaccionRepository->findById($id);

            if (!$transaccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transacción no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new TransaccionResource($transaccion),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todas las transacciones de una caja principal (todas sus sub-cajas)
     */
    public function indexByCajaPrincipal(Request $request, int $cajaPrincipalId): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            $transacciones = $this->transaccionRepository->getByCajaPrincipal($cajaPrincipalId, $perPage);

            return response()->json([
                'success' => true,
                'data' => TransaccionResource::collection($transacciones->items()),
                'pagination' => [
                    'total' => $transacciones->total(),
                    'per_page' => $transacciones->perPage(),
                    'current_page' => $transacciones->currentPage(),
                    'last_page' => $transacciones->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
