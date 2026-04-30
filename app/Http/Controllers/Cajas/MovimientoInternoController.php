<?php

namespace App\Http\Controllers\Cajas;

use App\DTOs\MovimientoInterno\CrearMovimientoInternoDTO;
use App\Exceptions\SaldoInsuficienteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearMovimientoInternoRequest;
use App\Services\Interfaces\MovimientoInternoServiceInterface;
use Illuminate\Http\JsonResponse;

class MovimientoInternoController extends Controller
{
    public function __construct(
        private MovimientoInternoServiceInterface $movimientoInternoService
    ) {}

    /**
     * Listar movimientos internos
     */
    public function index(): JsonResponse
    {
        try {
            $movimientos = $this->movimientoInternoService->listarMovimientos(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $movimientos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener movimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar depósitos de seguridad (Efectivo → Banco)
     */
    public function depositosSeguridad(): JsonResponse
    {
        try {
            $depositos = $this->movimientoInternoService->listarDepositosSeguridad(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $depositos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener depósitos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear movimiento interno entre sub-cajas del mismo vendedor
     */
    public function store(CrearMovimientoInternoRequest $request): JsonResponse
    {
        try {
            
            $userId = auth()->id();
            if (!$userId) {
                \Log::error('❌ Usuario no autenticado');
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }
            
            $dto = CrearMovimientoInternoDTO::fromRequest($request->validated());
            
            $resultado = $this->movimientoInternoService->crearMovimiento($dto, $userId);
            

            return response()->json([
                'success' => true,
                'message' => 'Movimiento interno registrado exitosamente',
                'data' => $resultado,
            ], 201);
        } catch (SaldoInsuficienteException $e) {
            \Log::error('❌ Saldo insuficiente', [
                'message' => $e->getMessage(),
                'saldo_disponible' => $e->saldoDisponible,
                'monto_solicitado' => $e->montoSolicitado,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'saldo_disponible' => $e->saldoDisponible,
                    'monto_solicitado' => $e->montoSolicitado,
                ],
            ], 400);
        } catch (\Exception $e) {
            \Log::error('❌ Error al crear movimiento interno', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear movimiento interno: ' . $e->getMessage(),
            ], 500);
        }
    }
}
