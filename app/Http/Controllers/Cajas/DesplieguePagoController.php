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
}
