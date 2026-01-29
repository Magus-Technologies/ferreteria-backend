<?php

namespace App\Services\Implementations;

use App\DTOs\PrestamoVendedor\CrearSolicitudEfectivoDTO;
use App\DTOs\PrestamoVendedor\RechazarSolicitudDTO;
use App\Exceptions\EfectivoInsuficienteException;
use App\Exceptions\PermisoPrestamoException;
use App\Exceptions\SolicitudYaProcesadaException;
use App\Models\DistribucionEfectivoVendedor;
use App\Models\SolicitudEfectivoVendedor;
use App\Models\TransferenciaEfectivoVendedor;
use App\Models\TransaccionCaja;
use App\Models\MovimientoCaja;
use App\Models\DespliegueDePago;
use App\Services\Interfaces\PrestamoVendedorServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrestamoVendedorService implements PrestamoVendedorServiceInterface
{
    public function crearSolicitud(CrearSolicitudEfectivoDTO $dto, int|string $vendedorSolicitanteId): array
    {
        // Verificar efectivo disponible del prestamista
        $efectivoDisponible = $this->calcularEfectivoDisponible($dto->aperturaId, $dto->vendedorPrestamistaId);

        if ($efectivoDisponible < $dto->montoSolicitado) {
            throw new EfectivoInsuficienteException($efectivoDisponible, $dto->montoSolicitado);
        }

        $solicitud = SolicitudEfectivoVendedor::create([
            'apertura_cierre_caja_id' => $dto->aperturaId,
            'vendedor_solicitante_id' => $vendedorSolicitanteId,
            'vendedor_prestamista_id' => $dto->vendedorPrestamistaId,
            'monto_solicitado' => $dto->montoSolicitado,
            'motivo' => $dto->motivo,
            'estado' => 'pendiente',
        ]);

        $solicitud->load(['vendedorSolicitante', 'vendedorPrestamista']);

        return [
            'id' => $solicitud->id,
            'monto' => number_format($solicitud->monto_solicitado, 2, '.', ''),
            'motivo' => $solicitud->motivo,
            'estado' => $solicitud->estado,
            'solicitante' => $solicitud->vendedorSolicitante->name,
            'prestamista' => $solicitud->vendedorPrestamista->name,
            'fecha' => $solicitud->fecha_solicitud->toIso8601String(),
        ];
    }

    public function aprobarSolicitud(string $solicitudId, int|string $vendedorPrestamistaId, int $subCajaOrigenId, ?float $montoAprobado = null): array
    {
        return DB::transaction(function () use ($solicitudId, $vendedorPrestamistaId, $subCajaOrigenId, $montoAprobado) {
            $solicitud = SolicitudEfectivoVendedor::with([
                'vendedorSolicitante',
                'vendedorPrestamista',
                'aperturaCierreCaja'
            ])->lockForUpdate()->findOrFail($solicitudId);

            // Validaciones
            if (!$solicitud->estaPendiente()) {
                throw new SolicitudYaProcesadaException();
            }

            if ($vendedorPrestamistaId !== $solicitud->vendedor_prestamista_id) {
                throw new PermisoPrestamoException('No tienes permiso para aprobar esta solicitud');
            }

            // Validar que la sub-caja pertenezca al prestamista
            $subCajaOrigen = \App\Models\SubCaja::findOrFail($subCajaOrigenId);
            $apertura = $solicitud->aperturaCierreCaja;
            
            if ($subCajaOrigen->caja_principal_id !== $apertura->caja_principal_id) {
                throw new \Exception('La sub-caja seleccionada no pertenece a tu caja principal');
            }

            // Determinar monto a transferir
            $montoATransferir = $montoAprobado ?? $solicitud->monto_solicitado;
            
            if ($montoATransferir > $solicitud->monto_solicitado) {
                throw new \Exception('El monto aprobado no puede ser mayor al solicitado');
            }

            // Calcular efectivo disponible en la sub-caja origen
            $efectivoDisponible = $this->calcularEfectivoEnSubCaja($subCajaOrigenId, $vendedorPrestamistaId);

            if ($efectivoDisponible < $montoATransferir) {
                throw new EfectivoInsuficienteException($efectivoDisponible, $montoATransferir);
            }

            // Obtener Caja Chica del solicitante (destino)
            $cajaChica = \App\Models\SubCaja::where('caja_principal_id', $apertura->caja_principal_id)
                ->where('tipo_caja', 'CC')
                ->firstOrFail();

            // Actualizar solicitud
            $solicitud->update([
                'estado' => 'aprobada',
                'fecha_respuesta' => now(),
                'sub_caja_origen_id' => $subCajaOrigenId,
                'sub_caja_destino_id' => $cajaChica->id,
            ]);

            // Crear transferencia
            $transferencia = TransferenciaEfectivoVendedor::create([
                'solicitud_id' => $solicitud->id,
                'apertura_cierre_caja_id' => $solicitud->apertura_cierre_caja_id,
                'vendedor_origen_id' => $solicitud->vendedor_prestamista_id,
                'sub_caja_origen_id' => $subCajaOrigenId,
                'vendedor_destino_id' => $solicitud->vendedor_solicitante_id,
                'sub_caja_destino_id' => $cajaChica->id,
                'monto' => $montoATransferir,
                'fecha_transferencia' => now(),
            ]);

            // Registrar transacciones y movimientos
            $this->registrarTransaccionesYMovimientos($solicitud, $transferencia, $subCajaOrigen, $cajaChica);

            return [
                'transferencia_id' => $transferencia->id,
                'monto' => number_format($transferencia->monto, 2, '.', ''),
                'origen' => $solicitud->vendedorPrestamista->name,
                'destino' => $solicitud->vendedorSolicitante->name,
                'sub_caja_origen' => $subCajaOrigen->nombre,
                'sub_caja_destino' => $cajaChica->nombre,
            ];
        });
    }

    public function rechazarSolicitud(string $solicitudId, RechazarSolicitudDTO $dto, int|string $vendedorPrestamistaId): void
    {
        $solicitud = SolicitudEfectivoVendedor::findOrFail($solicitudId);

        if (!$solicitud->estaPendiente()) {
            throw new SolicitudYaProcesadaException();
        }

        if ($vendedorPrestamistaId !== $solicitud->vendedor_prestamista_id) {
            throw new PermisoPrestamoException('No tienes permiso para rechazar esta solicitud');
        }

        $solicitud->update([
            'estado' => 'rechazada',
            'fecha_respuesta' => now(),
            'comentario_respuesta' => $dto->comentario,
        ]);
    }

    public function listarSolicitudesPendientes(int|string $vendedorId): array
    {
        $solicitudes = SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'aperturaCierreCaja'])
            ->where('vendedor_prestamista_id', $vendedorId)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        return $solicitudes->map(function ($solicitud) {
            return [
                'id' => $solicitud->id,
                'vendedor_solicitante' => [
                    'id' => $solicitud->vendedor_solicitante_id,
                    'name' => $solicitud->vendedorSolicitante->name,
                ],
                'monto_solicitado' => (float) $solicitud->monto_solicitado,
                'motivo' => $solicitud->motivo,
                'estado' => $solicitud->estado,
                'created_at' => $solicitud->fecha_solicitud->toIso8601String(),
            ];
        })->toArray();
    }

    public function listarTodasLasSolicitudes(int|string $vendedorId): array
    {
        $solicitudes = SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'vendedorPrestamista'])
            ->where(function ($query) use ($vendedorId) {
                $query->where('vendedor_solicitante_id', $vendedorId)
                    ->orWhere('vendedor_prestamista_id', $vendedorId);
            })
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        return $solicitudes->map(function ($solicitud) {
            return [
                'id' => $solicitud->id,
                'vendedor_solicitante' => [
                    'id' => $solicitud->vendedor_solicitante_id,
                    'name' => $solicitud->vendedorSolicitante->name,
                ],
                'vendedor_prestamista' => [
                    'id' => $solicitud->vendedor_prestamista_id,
                    'name' => $solicitud->vendedorPrestamista->name,
                ],
                'monto_solicitado' => $solicitud->monto_solicitado,
                'estado' => $solicitud->estado,
                'motivo' => $solicitud->motivo,
                'created_at' => $solicitud->fecha_solicitud->toIso8601String(),
            ];
        })->toArray();
    }

    public function obtenerVendedoresConEfectivo(string $aperturaId, int|string $vendedorActualId): array
    {
        Log::info('🔍 Obteniendo vendedores con efectivo', [
            'apertura_id' => $aperturaId,
            'vendedor_actual_id' => $vendedorActualId,
        ]);

        try {
            // Intentar obtener la apertura de la tabla nueva primero
            $apertura = \App\Models\AperturaCierreCaja::find($aperturaId);
            
            // Si no existe, buscar en la tabla legacy
            if (!$apertura) {
                $aperturaLegacy = \App\Models\AperturaYCierreCaja::find($aperturaId);
                if (!$aperturaLegacy) {
                    throw new \Exception('Apertura de caja no encontrada');
                }
                
                // Para la tabla legacy, necesitamos obtener la caja principal del usuario
                $cajaPrincipal = \App\Models\CajaPrincipal::where('user_id', $aperturaLegacy->user_id)->first();
                if (!$cajaPrincipal) {
                    throw new \Exception('No se encontró caja principal para el usuario');
                }
                
                $cajaPrincipalId = $cajaPrincipal->id;
            } else {
                $cajaPrincipalId = $apertura->caja_principal_id;
            }
            
            Log::info('✅ Apertura encontrada', [
                'caja_principal_id' => $cajaPrincipalId,
            ]);
            
            // Obtener todos los vendedores con distribución de efectivo (excepto el actual)
            $distribuciones = DistribucionEfectivoVendedor::with('vendedor')
                ->where('apertura_cierre_caja_id', $aperturaId)
                ->where('user_id', '!=', $vendedorActualId)
                ->get();

            Log::info('📊 Distribuciones encontradas', [
                'count' => $distribuciones->count(),
            ]);

            $vendedoresConEfectivo = [];

            foreach ($distribuciones as $dist) {
                Log::info('🔍 Calculando efectivo para vendedor', [
                    'vendedor_id' => $dist->user_id,
                    'vendedor_nombre' => $dist->vendedor->name,
                ]);

                // Calcular efectivo disponible en Caja Chica del vendedor
                $efectivoDisponible = $this->calcularEfectivoEnCajaChica($cajaPrincipalId, $dist->user_id);

                Log::info('💰 Efectivo calculado', [
                    'vendedor_id' => $dist->user_id,
                    'efectivo_disponible' => $efectivoDisponible,
                ]);

                if ($efectivoDisponible > 0) {
                    $vendedoresConEfectivo[] = [
                        'vendedor_id' => $dist->user_id,
                        'vendedor_nombre' => $dist->vendedor->name,
                        'efectivo_inicial' => number_format($dist->monto, 2, '.', ''),
                        'efectivo_disponible' => number_format($efectivoDisponible, 2, '.', ''),
                    ];
                }
            }

            Log::info('✅ Vendedores con efectivo', [
                'count' => count($vendedoresConEfectivo),
                'vendedores' => $vendedoresConEfectivo,
            ]);

            return $vendedoresConEfectivo;
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener vendedores con efectivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Calcular efectivo disponible en una sub-caja específica del vendedor
     */
    private function calcularEfectivoEnSubCaja(int $subCajaId, int|string $vendedorId): float
    {
        $subCaja = \App\Models\SubCaja::findOrFail($subCajaId);

        // Si es Caja Chica, calcular efectivo del vendedor
        if ($subCaja->tipo_caja === 'CC') {
            // Obtener la apertura activa
            $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
                ->whereNull('fecha_cierre')
                ->first();

            if (!$aperturaActiva) {
                return 0;
            }

            // Monto inicial de distribución
            $montoInicial = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                ->where('user_id', $vendedorId)
                ->sum('monto');

            // Obtener IDs de despliegues de pago tipo EFECTIVO de la Caja Chica
            $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];
            
            $desplieguePagoEfectivoIds = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
                ->whereHas('metodoDePago', function ($query) {
                    $query->whereNull('cuenta_bancaria')
                          ->where(function ($q) {
                              $q->where('name', 'like', '%efectivo%')
                                ->orWhere('name', 'like', '%Efectivo%');
                          });
                })
                ->pluck('id')
                ->toArray();

            if (empty($desplieguePagoEfectivoIds)) {
                return $montoInicial;
            }

            // Calcular transacciones de efectivo (excluyendo aperturas)
            $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCaja->id)
                ->where('user_id', $vendedorId)
                ->where(function ($query) use ($desplieguePagoEfectivoIds) {
                    $query->whereIn('despliegue_pago_id', $desplieguePagoEfectivoIds)
                          ->orWhere(function ($q) {
                              $q->whereNull('despliegue_pago_id')
                                ->where('referencia_tipo', 'venta');
                          });
                })
                ->where(function ($query) {
                    $query->whereNull('referencia_tipo')
                          ->orWhere('referencia_tipo', '!=', 'apertura');
                })
                ->get();

            $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
            $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

            return $montoInicial + $ingresos - $egresos;
        }

        // Para otras sub-cajas, calcular el saldo total
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $vendedorId)
            ->get();

        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

        return $ingresos - $egresos;
    }

    /**
     * Calcular efectivo disponible en Caja Chica del vendedor
     */
    private function calcularEfectivoEnCajaChica(int $cajaPrincipalId, int|string $vendedorId): float
    {
        // Buscar la Caja Chica
        $cajaChica = \App\Models\SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('tipo_caja', 'CC')
            ->first();

        if (!$cajaChica) {
            return 0;
        }

        // Obtener la apertura activa
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
            ->whereNull('fecha_cierre')
            ->first();

        if (!$aperturaActiva) {
            return 0;
        }

        // Monto inicial de distribución
        $montoInicial = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
            ->where('user_id', $vendedorId)
            ->sum('monto');

        // Obtener IDs de despliegues de pago tipo EFECTIVO de la Caja Chica
        $desplieguePagoIds = $cajaChica->despliegues_pago_ids ?? [];
        
        $desplieguePagoEfectivoIds = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->whereHas('metodoDePago', function ($query) {
                $query->whereNull('cuenta_bancaria')
                      ->where(function ($q) {
                          $q->where('name', 'like', '%efectivo%')
                            ->orWhere('name', 'like', '%Efectivo%');
                      });
            })
            ->pluck('id')
            ->toArray();

        if (empty($desplieguePagoEfectivoIds)) {
            return $montoInicial;
        }

        // Calcular transacciones de efectivo (excluyendo aperturas)
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $cajaChica->id)
            ->where('user_id', $vendedorId)
            ->where(function ($query) use ($desplieguePagoEfectivoIds) {
                $query->whereIn('despliegue_pago_id', $desplieguePagoEfectivoIds)
                      ->orWhere(function ($q) {
                          $q->whereNull('despliegue_pago_id')
                            ->where('referencia_tipo', 'venta');
                      });
            })
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get();

        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

        return $montoInicial + $ingresos - $egresos;
    }

    public function calcularEfectivoDisponible(string $aperturaId, int|string $vendedorId): float
    {
        $distribucion = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaId)
            ->where('user_id', $vendedorId)
            ->first();

        if (!$distribucion) {
            return 0;
        }

        $efectivoInicial = $distribucion->monto;

        $prestamosDados = TransferenciaEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaId)
            ->where('vendedor_origen_id', $vendedorId)
            ->sum('monto');

        $prestamosRecibidos = TransferenciaEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaId)
            ->where('vendedor_destino_id', $vendedorId)
            ->sum('monto');

        return $efectivoInicial - $prestamosDados + $prestamosRecibidos;
    }

    public function listarTransferencias(int|string $vendedorId): array
    {
        $transferencias = TransferenciaEfectivoVendedor::with([
            'vendedorOrigen',
            'vendedorDestino',
            'solicitud'
        ])
            ->where(function ($query) use ($vendedorId) {
                $query->where('vendedor_origen_id', $vendedorId)
                    ->orWhere('vendedor_destino_id', $vendedorId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $transferencias->map(function ($transferencia) {
            return [
                'id' => $transferencia->id,
                'vendedor_origen' => [
                    'id' => $transferencia->vendedor_origen_id,
                    'name' => $transferencia->vendedorOrigen->name,
                ],
                'vendedor_destino' => [
                    'id' => $transferencia->vendedor_destino_id,
                    'name' => $transferencia->vendedorDestino->name,
                ],
                'monto' => $transferencia->monto,
                'tipo' => 'prestamo', // Por ahora todas son préstamos
                'created_at' => $transferencia->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    private function registrarTransaccionesYMovimientos(
        SolicitudEfectivoVendedor $solicitud,
        TransferenciaEfectivoVendedor $transferencia,
        \App\Models\SubCaja $subCajaOrigen,
        \App\Models\SubCaja $subCajaDestino
    ): void {
        // Buscar método de pago de efectivo (intentar varios nombres comunes)
        $desplieguePagoEfectivo = DespliegueDePago::where('activo', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%Efectivo%')
                      ->orWhere('name', 'like', '%efectivo%')
                      ->orWhere('name', 'like', '%EFECTIVO%');
            })
            ->first();

        // Si no se encuentra, buscar el primer método de pago activo de efectivo
        if (!$desplieguePagoEfectivo) {
            $desplieguePagoEfectivo = DespliegueDePago::where('activo', true)
                ->whereHas('metodoDePago', function ($query) {
                    $query->whereNull('cuenta_bancaria');
                })
                ->first();
        }

        $saldoAnteriorOrigen = $subCajaOrigen->saldo_actual;
        $saldoAnteriorDestino = $subCajaDestino->saldo_actual;

        // Transacción de salida en sub-caja origen (prestamista)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaOrigen->id,
            'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $transferencia->monto,
            'saldo_anterior' => $saldoAnteriorOrigen,
            'saldo_nuevo' => $saldoAnteriorOrigen - $transferencia->monto,
            'descripcion' => "Préstamo de efectivo a {$solicitud->vendedorSolicitante->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_prestamista_id,
            'fecha' => now(),
        ]);

        // Transacción de entrada en Caja Chica (solicitante)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaDestino->id,
            'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $transferencia->monto,
            'saldo_anterior' => $saldoAnteriorDestino,
            'saldo_nuevo' => $saldoAnteriorDestino + $transferencia->monto,
            'descripcion' => "Préstamo de efectivo recibido de {$solicitud->vendedorPrestamista->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_solicitante_id,
            'fecha' => now(),
        ]);

        // Actualizar saldos de las sub-cajas
        $subCajaOrigen->update(['saldo_actual' => $saldoAnteriorOrigen - $transferencia->monto]);
        $subCajaDestino->update(['saldo_actual' => $saldoAnteriorDestino + $transferencia->monto]);

        // Movimiento de salida (prestamista)
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCajaOrigen->caja_principal_id,
            'sub_caja_id' => $subCajaOrigen->id,
            'cajero_id' => $solicitud->vendedor_prestamista_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Préstamo de S/. {$transferencia->monto} a {$solicitud->vendedorSolicitante->name}",
            'saldo_inicial' => $saldoAnteriorOrigen,
            'ingreso' => 0,
            'salida' => $transferencia->monto,
            'saldo_final' => $saldoAnteriorOrigen - $transferencia->monto,
            'estado_caja' => 'abierta',
        ]);

        // Movimiento de entrada (solicitante)
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCajaDestino->caja_principal_id,
            'sub_caja_id' => $subCajaDestino->id,
            'cajero_id' => $solicitud->vendedor_solicitante_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Préstamo de S/. {$transferencia->monto} recibido de {$solicitud->vendedorPrestamista->name}",
            'saldo_inicial' => $saldoAnteriorDestino,
            'ingreso' => $transferencia->monto,
            'salida' => 0,
            'saldo_final' => $saldoAnteriorDestino + $transferencia->monto,
            'estado_caja' => 'abierta',
        ]);
    }
}
