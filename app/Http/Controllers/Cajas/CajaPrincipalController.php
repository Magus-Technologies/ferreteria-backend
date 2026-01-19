<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearCajaPrincipalRequest as HttpCrearCajaPrincipalRequest;
use App\Http\Resources\Cajas\CajaPrincipalResource;
use App\UseCases\CrearCajaPrincipal\CrearCajaPrincipalRequest;
use App\UseCases\CrearCajaPrincipal\CrearCajaPrincipalUseCase;
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CajaPrincipalController extends Controller
{
    public function __construct(
        private CrearCajaPrincipalUseCase $crearCajaPrincipalUseCase,
        private CajaPrincipalRepositoryInterface $cajaPrincipalRepository
    ) {}

    /**
     * Listar todas las cajas principales
     */
    public function index(): JsonResponse
    {
        $cajas = $this->cajaPrincipalRepository->getAll();
        
        return response()->json([
            'success' => true,
            'data' => CajaPrincipalResource::collection($cajas),
        ]);
    }

    /**
     * Crear una nueva caja principal
     */
    public function store(HttpCrearCajaPrincipalRequest $request): JsonResponse
    {
        try {
            $useCaseRequest = new CrearCajaPrincipalRequest(
                userId: $request->validated('user_id'),
                nombre: $request->validated('nombre')
            );

            $response = $this->crearCajaPrincipalUseCase->execute($useCaseRequest);

            return response()->json([
                'success' => $response->success,
                'message' => $response->message,
                'data' => new CajaPrincipalResource($response->cajaPrincipal),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener una caja principal por ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $caja = $this->cajaPrincipalRepository->findById($id);

            if (!$caja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Caja principal no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CajaPrincipalResource($caja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener caja principal por usuario
     */
    public function getByUser(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id', auth()->id());
            $caja = $this->cajaPrincipalRepository->findByUserId($userId);

            if (!$caja) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una caja asignada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CajaPrincipalResource($caja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una caja principal
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->cajaPrincipalRepository->delete($id);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'Caja principal eliminada exitosamente' : 'No se pudo eliminar la caja',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
