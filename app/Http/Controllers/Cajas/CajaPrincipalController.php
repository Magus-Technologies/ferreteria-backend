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
            // Verificar que el usuario esté autenticado
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            // Verificar permisos usando el array all_permissions
            $hasPermission = in_array('caja.create', $user->all_permissions ?? []);
            
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para crear cajas principales',
                ], 403);
            }

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
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 100 && $code < 600) ? $code : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
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
                // En lugar de error, retornar null para que el frontend maneje
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'El usuario no tiene una caja asignada',
                ], 200);
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
