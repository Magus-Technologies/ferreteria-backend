<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CerrarCajaRequest;
use App\Http\Requests\Cajas\ValidarSupervisorRequest;
use App\Http\Resources\Cajas\AperturaCierreCajaResource;
use App\Http\Resources\Cajas\CierreCajaResource;
use App\Services\Interfaces\CierreCajaServiceInterface;
use App\Exceptions\AperturaNoEncontradaException;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\SupervisorRequeridoException;
use App\Exceptions\SupervisorInvalidoException;
use Illuminate\Http\JsonResponse;

class CierreCajaController extends Controller
{
    public function __construct(
        private CierreCajaServiceInterface $cierreCajaService
    ) {}

    /**
     * Obtener la caja activa del vendedor actual
     */
    public function obtenerCajaActiva(): JsonResponse
    {
        try {
            \Log::info('=== INICIO obtenerCajaActiva ===');
            
            $userId = auth()->id();
            \Log::info('User ID obtenido', ['userId' => $userId, 'type' => gettype($userId)]);
            
            if (!$userId) {
                \Log::warning('Usuario no autenticado');
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }
            
            \Log::info('Llamando a obtenerCajaActivaConResumen');
            $cajaActiva = $this->cierreCajaService->obtenerCajaActivaConResumen($userId);
            \Log::info('Caja activa obtenida', ['apertura_id' => $cajaActiva->apertura->id ?? 'null']);

            $data = (new AperturaCierreCajaResource($cajaActiva->apertura))->toArray(request());
            $data['resumen'] = $cajaActiva->resumen->toArray();
            
            \Log::info('=== FIN obtenerCajaActiva SUCCESS ===');

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (AperturaNoEncontradaException $e) {
            \Log::warning('AperturaNoEncontradaException', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Error en obtenerCajaActiva', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener caja activa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cerrar caja
     */
    public function cerrarCaja(string $id, CerrarCajaRequest $request): JsonResponse
    {
        try {
            $resumen = $this->cierreCajaService->cerrarCajaConResumen(
                $id,
                $request->validated()
            );

            // Obtener la apertura actualizada
            $apertura = $this->cierreCajaService->obtenerApertura($id);

            return response()->json([
                'success' => true,
                'message' => 'Caja cerrada exitosamente',
                'data' => (new CierreCajaResource($apertura, $resumen))->toArray(request()),
            ]);
        } catch (AperturaNoEncontradaException | CajaYaCerradaException | SupervisorRequeridoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar caja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle completo de movimientos de la caja
     */
    public function obtenerDetalleMovimientos(string $id): JsonResponse
    {
        try {
            $detalle = $this->cierreCajaService->obtenerDetalleMovimientos($id);

            return response()->json([
                'success' => true,
                'data' => $detalle,
            ]);
        } catch (AperturaNoEncontradaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar supervisor
     */
    public function validarSupervisor(ValidarSupervisorRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $supervisor = $this->cierreCajaService->validarSupervisor(
                $validated['email'],
                $validated['password']
            );

            if (!$supervisor) {
                throw new SupervisorInvalidoException();
            }

            return response()->json([
                'success' => true,
                'data' => $supervisor,
            ]);
        } catch (SupervisorInvalidoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar supervisor: ' . $e->getMessage(),
            ], 500);
        }
    }
}
