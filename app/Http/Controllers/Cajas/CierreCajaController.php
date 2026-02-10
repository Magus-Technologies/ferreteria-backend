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
use Illuminate\Http\Request;

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
            'efectivo_inicial' => (float) $montoInicial,
            'monto_apertura' => (float) $montoInicial,
            'total_ingresos' => (float) $ingresos,
            'total_egresos' => (float) $egresos,
            'total_ventas' => (float) $ingresos, // Para vendedores, los ingresos son principalmente ventas
            'prestamos_dados' => (float) $prestamosDados,
            'prestamos_recibidos' => (float) $prestamosRecibidos,
            'total_prestamos_dados' => (float) $prestamosDados,
            'total_prestamos_recibidos' => (float) $prestamosRecibidos,
            'monto_esperado' => (float) $montoEsperado,
            'monto_cierre' => null,
            'diferencia' => null,
            'detalle_metodos_pago' => [], // TODO: Implementar si es necesario
            'detalle_ventas' => [],
            'detalle_ingresos' => [],
            'detalle_egresos' => [],
        ];
    }

    /**
     * Cerrar caja
     */
    public function cerrarCaja(string $id, CerrarCajaRequest $request): JsonResponse
    {
        try {
            $resultado = $this->cierreCajaService->cerrarCajaConResumen(
                $id,
                $request->validated()
            );

            // Refrescar el modelo para obtener el estado actualizado
            $resultado->apertura->refresh();

            // Crear el resumen DTO desde el array
            $resumenDTO = new \App\DTOs\CierreCaja\ResumenCajaDTO(
                efectivoInicial: $resultado->resumen['efectivo_inicial'] ?? 0,
                montoApertura: $resultado->resumen['monto_apertura'] ?? 0,
                totalIngresos: $resultado->resumen['total_ingresos'] ?? 0,
                totalEgresos: $resultado->resumen['total_egresos'] ?? 0,
                totalVentas: $resultado->resumen['total_ventas'] ?? 0,
                montoEsperado: $resultado->resumen['monto_esperado'] ?? 0,
                montoCierre: $resultado->resumen['monto_cierre'] ?? null,
                diferencia: $resultado->resumen['diferencia'] ?? null,
                detalleIngresos: collect($resultado->resumen['detalle_ingresos'] ?? []),
                detalleEgresos: collect($resultado->resumen['detalle_egresos'] ?? []),
                detalleVentas: collect($resultado->resumen['detalle_ventas'] ?? []),
                detalleMetodosPago: collect($resultado->resumen['detalle_metodos_pago'] ?? []),
                prestamosRecibidos: collect($resultado->resumen['prestamos_recibidos'] ?? []),
                totalPrestamosRecibidos: $resultado->resumen['total_prestamos_recibidos'] ?? 0,
                prestamosDados: collect($resultado->resumen['prestamos_dados'] ?? []),
                totalPrestamosDados: $resultado->resumen['total_prestamos_dados'] ?? 0,
                movimientosInternos: collect($resultado->resumen['movimientos_internos'] ?? []),
                prestamos: collect($resultado->resumen['prestamos'] ?? []),
                prestamosVendedores: collect($resultado->resumen['prestamos_vendedores'] ?? [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Caja cerrada exitosamente',
                'data' => (new CierreCajaResource($resultado->apertura, $resumenDTO))->toArray(request()),
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

    /**
     * Recalcular cierre de caja (solo para administradores)
     */
    public function recalcularCierre(string $id): JsonResponse
    {
        try {
            // TEMPORALMENTE DESHABILITADO PARA PRUEBAS
            // Verificar que el usuario sea administrador
            // $user = auth()->user();
            // $isAdmin = $user->roles()->whereIn('name', ['admin', 'administrador'])->exists();
            
            // if (!$isAdmin) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'No tienes permisos para realizar esta acción',
            //     ], 403);
            // }

            // Obtener la apertura
            $apertura = $this->cierreCajaService->obtenerApertura($id);
            
            if (!$apertura) {
                throw new AperturaNoEncontradaException();
            }

            // Verificar que la caja esté cerrada
            if ($apertura->estado !== 'cerrada') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden recalcular cierres de cajas cerradas',
                ], 400);
            }

            // Recalcular el resumen
            $resultado = $this->cierreCajaService->recalcularCierre($id);

            return response()->json([
                'success' => true,
                'message' => 'Cierre recalculado exitosamente',
                'data' => $resultado,
            ]);
        } catch (AperturaNoEncontradaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Error al recalcular cierre', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular cierre: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un cierre específico con su resumen completo
     */
    public function obtenerCierre(string $id): JsonResponse
    {
        try {
            // Obtener la apertura
            $apertura = $this->cierreCajaService->obtenerApertura($id);
            
            if (!$apertura) {
                throw new AperturaNoEncontradaException();
            }

            // Verificar que la caja esté cerrada
            if ($apertura->estado !== 'cerrada') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden consultar cierres de cajas cerradas',
                ], 400);
            }

            // Calcular el resumen completo usando el calculador
            $calculador = app(\App\Services\CierreCaja\CalculadorResumenCaja::class);
            $resumen = $calculador->calcular($apertura);

            // Preparar la respuesta con el formato esperado
            $data = (new \App\Http\Resources\Cajas\AperturaCierreCajaResource($apertura))->toArray(request());
            $data['resumen'] = $resumen->toArray();
            $data['tipo_usuario'] = 'encargado';

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (AperturaNoEncontradaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Error al obtener cierre', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cierre: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un cierre existente (solo monto_cierre y comentarios)
     */
    public function actualizarCierre(string $id, Request $request): JsonResponse
    {
        try {
            // Obtener la apertura
            $apertura = $this->cierreCajaService->obtenerApertura($id);
            
            if (!$apertura) {
                throw new AperturaNoEncontradaException();
            }

            // Verificar que la caja esté cerrada
            if ($apertura->estado !== 'cerrada') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden actualizar cierres de cajas cerradas',
                ], 400);
            }

            // Actualizar solo el monto_cierre y comentarios
            if ($request->has('monto_cierre')) {
                $apertura->monto_cierre = $request->input('monto_cierre');
            }
            
            if ($request->has('comentarios')) {
                $apertura->comentarios = $request->input('comentarios');
            }
            
            $apertura->save();

            return response()->json([
                'success' => true,
                'message' => 'Cierre actualizado exitosamente',
                'data' => $apertura,
            ]);
        } catch (AperturaNoEncontradaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Error al actualizar cierre', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cierre: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Diagnóstico de distribuciones de efectivo del usuario
     * Endpoint temporal para debugging
     */
    public function diagnosticoDistribuciones(): JsonResponse
    {
        $userId = auth()->id();
        
        // Obtener todas las distribuciones del usuario
        $todasDistribuciones = \App\Models\DistribucionEfectivoVendedor::where('user_id', $userId)
            ->with(['aperturaCierreCaja', 'vendedor'])
            ->get();
        
        // Obtener distribuciones activas (con apertura sin cerrar)
        $distribucionesActivas = \App\Models\DistribucionEfectivoVendedor::where('user_id', $userId)
            ->whereHas('aperturaCierreCaja', function ($query) {
                $query->whereNull('fecha_cierre');
            })
            ->with(['aperturaCierreCaja', 'vendedor'])
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'user_id_type' => gettype($userId),
                'total_distribuciones' => $todasDistribuciones->count(),
                'distribuciones_activas' => $distribucionesActivas->count(),
                'todas_distribuciones' => $todasDistribuciones->map(function ($dist) {
                    return [
                        'id' => $dist->id,
                        'user_id' => $dist->user_id,
                        'user_id_type' => gettype($dist->user_id),
                        'monto' => $dist->monto,
                        'apertura_id' => $dist->apertura_cierre_caja_id,
                        'fecha_apertura' => $dist->aperturaCierreCaja->fecha_apertura ?? null,
                        'fecha_cierre' => $dist->aperturaCierreCaja->fecha_cierre ?? null,
                        'vendedor_name' => $dist->vendedor->name ?? null,
                    ];
                }),
                'distribuciones_activas_detalle' => $distribucionesActivas->map(function ($dist) {
                    return [
                        'id' => $dist->id,
                        'user_id' => $dist->user_id,
                        'monto' => $dist->monto,
                        'apertura_id' => $dist->apertura_cierre_caja_id,
                        'fecha_apertura' => $dist->aperturaCierreCaja->fecha_apertura ?? null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Enviar ticket de cierre por correo electrónico
     */
    public function enviarTicketEmail(string $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'pdf' => 'required|file|mimes:pdf|max:10240', // Max 10MB
            ]);

            // Obtener la apertura
            $apertura = \App\Models\AperturaCierreCaja::with('user')->findOrFail($id);
            
            // Verificar permisos
            if ($apertura->user_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'administrador'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para enviar este ticket',
                ], 403);
            }

            // Determinar el asunto según el estado
            $subject = $apertura->estado === 'cerrada' 
                ? 'Ticket de Cierre de Caja - ' . \Carbon\Carbon::parse($apertura->fecha_cierre)->format('d/m/Y')
                : 'Ticket de Caja Abierta - ' . \Carbon\Carbon::parse($apertura->fecha_apertura)->format('d/m/Y');

            // Obtener el archivo PDF
            $pdfFile = $request->file('pdf');
            $pdfPath = $pdfFile->getRealPath();
            $pdfName = 'ticket-cierre-' . $id . '.pdf';

            // Renderizar la vista HTML
            $htmlContent = view('emails.ticket-cierre-simple', [
                'apertura' => $apertura,
                'subject' => $subject
            ])->render();

            // Enviar el correo con el PDF adjunto
            \Mail::html($htmlContent, function ($message) use ($request, $subject, $pdfPath, $pdfName) {
                $message->to($request->email)
                    ->subject($subject)
                    ->attach($pdfPath, [
                        'as' => $pdfName,
                        'mime' => 'application/pdf',
                    ]);
            });

            // Registrar el envío
            \Log::info("Ticket de cierre enviado por correo", [
                'cierre_id' => $id,
                'email' => $request->email,
                'user_id' => auth()->id(),
                'estado' => $apertura->estado
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket enviado exitosamente por correo electrónico',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al enviar ticket por correo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el ticket: ' . $e->getMessage(),
            ], 500);
        }
    }
}
