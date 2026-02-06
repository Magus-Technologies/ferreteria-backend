<?php

namespace App\Http\Controllers\FacturacionElectronica;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacturacionElectronica\MotivoNotaResource;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotivoNotaController extends Controller
{
    public function __construct(
        private MotivoNotaRepositoryInterface $motivoRepository
    ) {}

    /**
     * Listar todos los motivos activos
     */
    public function index(): JsonResponse
    {
        try {
            $motivos = $this->motivoRepository->getAllActivos();

            return response()->json([
                'success' => true,
                'data' => MotivoNotaResource::collection($motivos),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar motivos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener motivos de débito
     */
    public function motivosDebito(): JsonResponse
    {
        try {
            $motivos = $this->motivoRepository->getMotivosDebito();

            return response()->json([
                'success' => true,
                'data' => MotivoNotaResource::collection($motivos),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener motivos de débito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener motivos de crédito
     */
    public function motivosCredito(): JsonResponse
    {
        try {
            $motivos = $this->motivoRepository->getMotivosCredito();

            return response()->json([
                'success' => true,
                'data' => MotivoNotaResource::collection($motivos),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener motivos de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener motivo por ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $motivo = $this->motivoRepository->findById($id);

            if (!$motivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Motivo no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new MotivoNotaResource($motivo),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener motivo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
