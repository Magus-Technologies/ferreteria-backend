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
     * Si el usuario es encargado de caja: retorna su caja completa
     * Si el usuario es vendedor: retorna un resumen de sus movimientos
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
            
            // Intentar obtener caja como encargado
            try {
                \Log::info('Intentando obtener caja como encargado');
                $cajaActiva = $this->cierreCajaService->obtenerCajaActivaConResumen($userId);
                \Log::info('Caja activa obtenida como encargado', ['apertura_id' => $cajaActiva->apertura->id ?? 'null']);

                $data = (new AperturaCierreCajaResource($cajaActiva->apertura))->toArray(request());
                $data['resumen'] = $cajaActiva->resumen->toArray();
                $data['tipo_usuario'] = 'encargado';
                
                \Log::info('=== FIN obtenerCajaActiva SUCCESS (Encargado) ===');

                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
            } catch (AperturaNoEncontradaException $e) {
                // No es encargado, intentar como vendedor
                \Log::info('No es encargado, intentando como vendedor');
                
                // Buscar distribuciones activas del vendedor
                $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('user_id', $userId)
                    ->whereHas('aperturaCierreCaja', function ($query) {
                        $query->whereNull('fecha_cierre');
                    })
                    ->with('aperturaCierreCaja')
                    ->get();
                
                if ($distribuciones->isEmpty()) {
                    throw new AperturaNoEncontradaException();
                }
                
                // Tomar la primera distribución (normalmente solo hay una activa)
                $distribucion = $distribuciones->first();
                $apertura = $distribucion->aperturaCierreCaja;
                
                // Calcular resumen del vendedor
                $resumenVendedor = $this->calcularResumenVendedor($userId, $apertura);
                
                \Log::info('=== FIN obtenerCajaActiva SUCCESS (Vendedor) ===');
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $apertura->id,
                        'tipo_usuario' => 'vendedor',
                        'fecha_apertura' => $apertura->fecha_apertura,
                        'resumen' => $resumenVendedor,
                    ],
                ]);
            }
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
     * Calcular resumen de movimientos del vendedor
     */
    private function calcularResumenVendedor(string $userId, $apertura): array
    {
        // Monto inicial (distribución)
        $montoInicial = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
            ->where('user_id', $userId)
            ->sum('monto');
        
        // Obtener Cajas Chicas
        $cajasChicas = \App\Models\SubCaja::where('caja_principal_id', $apertura->caja_principal_id)
            ->where('tipo_caja', 'CC')
            ->pluck('id');
        
        // Transacciones del vendedor
        $transacciones = \App\Models\TransaccionCaja::whereIn('sub_caja_id', $cajasChicas)
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        // Préstamos dados y recibidos
        $prestamosDados = \App\Models\TransferenciaEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
            ->where('vendedor_origen_id', $userId)
            ->sum('monto');
        
        $prestamosRecibidos = \App\Models\TransferenciaEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
            ->where('vendedor_destino_id', $userId)
            ->sum('monto');
        
        $montoEsperado = $montoInicial + $ingresos - $egresos;
        
        return [
            'monto_apertura' => (float) $montoInicial,
            'total_ingresos' => (float) $ingresos,
            'total_egresos' => (float) $egresos,
            'prestamos_dados' => (float) $prestamosDados,
            'prestamos_recibidos' => (float) $prestamosRecibidos,
            'monto_esperado' => (float) $montoEsperado,
            'monto_cierre' => null,
            'diferencia' => null,
            'detalle_metodos_pago' => [], // TODO: Implementar si es necesario
        ];
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
