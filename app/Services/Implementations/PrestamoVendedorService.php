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
use Illuminate\Support\Str;

class PrestamoVendedorService implements PrestamoVendedorServiceInterface
{
    public function crearSolicitud(CrearSolicitudEfectivoDTO $dto, int $vendedorSolicitanteId): array
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

    public function aprobarSolicitud(string $solicitudId, int $vendedorPrestamistaId): array
    {
        return DB::transaction(function () use ($solicitudId, $vendedorPrestamistaId) {
            $solicitud = SolicitudEfectivoVendedor::with([
                'vendedorSolicitante',
                'vendedorPrestamista',
                'aperturaCierreCaja.subCaja'
            ])->lockForUpdate()->findOrFail($solicitudId);

            // Validaciones
            if (!$solicitud->estaPendiente()) {
                throw new SolicitudYaProcesadaException();
            }

            if ($vendedorPrestamistaId !== $solicitud->vendedor_prestamista_id) {
                throw new PermisoPrestamoException('No tienes permiso para aprobar esta solicitud');
            }

            // Verificar efectivo disponible
            $efectivoDisponible = $this->calcularEfectivoDisponible(
                $solicitud->apertura_cierre_caja_id,
                $solicitud->vendedor_prestamista_id
            );

            if ($efectivoDisponible < $solicitud->monto_solicitado) {
                throw new EfectivoInsuficienteException($efectivoDisponible, $solicitud->monto_solicitado);
            }

            // Actualizar solicitud
            $solicitud->update([
                'estado' => 'aprobada',
                'fecha_respuesta' => now(),
            ]);

            // Crear transferencia
            $transferencia = TransferenciaEfectivoVendedor::create([
                'solicitud_id' => $solicitud->id,
                'apertura_cierre_caja_id' => $solicitud->apertura_cierre_caja_id,
                'vendedor_origen_id' => $solicitud->vendedor_prestamista_id,
                'vendedor_destino_id' => $solicitud->vendedor_solicitante_id,
                'monto' => $solicitud->monto_solicitado,
            ]);

            // Registrar transacciones y movimientos
            $this->registrarTransaccionesYMovimientos($solicitud, $transferencia);

            return [
                'transferencia_id' => $transferencia->id,
                'monto' => number_format($transferencia->monto, 2, '.', ''),
                'origen' => $solicitud->vendedorPrestamista->name,
                'destino' => $solicitud->vendedorSolicitante->name,
            ];
        });
    }

    public function rechazarSolicitud(string $solicitudId, RechazarSolicitudDTO $dto, int $vendedorPrestamistaId): void
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

    public function listarSolicitudesPendientes(int $vendedorId): array
    {
        $solicitudes = SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'aperturaCierreCaja'])
            ->where('vendedor_prestamista_id', $vendedorId)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        return $solicitudes->map(function ($solicitud) {
            return [
                'id' => $solicitud->id,
                'monto' => number_format($solicitud->monto_solicitado, 2, '.', ''),
                'motivo' => $solicitud->motivo,
                'solicitante' => $solicitud->vendedorSolicitante->name,
                'solicitante_id' => $solicitud->vendedor_solicitante_id,
                'fecha' => $solicitud->fecha_solicitud->toIso8601String(),
            ];
        })->toArray();
    }

    public function listarTodasLasSolicitudes(int $vendedorId): array
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

    public function obtenerVendedoresConEfectivo(string $aperturaId, int $vendedorActualId): array
    {
        $distribuciones = DistribucionEfectivoVendedor::with('vendedor')
            ->where('apertura_cierre_caja_id', $aperturaId)
            ->where('user_id', '!=', $vendedorActualId)
            ->get();

        return $distribuciones->map(function ($dist) use ($aperturaId) {
            $efectivoDisponible = $this->calcularEfectivoDisponible($aperturaId, $dist->user_id);

            return [
                'vendedor_id' => $dist->user_id,
                'vendedor_nombre' => $dist->vendedor->name,
                'efectivo_inicial' => number_format($dist->monto, 2, '.', ''),
                'efectivo_disponible' => number_format($efectivoDisponible, 2, '.', ''),
            ];
        })->filter(function ($vendedor) {
            return floatval($vendedor['efectivo_disponible']) > 0;
        })->values()->toArray();
    }

    public function calcularEfectivoDisponible(string $aperturaId, int $vendedorId): float
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

    public function listarTransferencias(int $vendedorId): array
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
        TransferenciaEfectivoVendedor $transferencia
    ): void {
        $desplieguePagoEfectivo = DespliegueDePago::where('name', 'Efectivo')
            ->where('activo', true)
            ->first();

        $subCaja = $solicitud->aperturaCierreCaja->subCaja;
        $saldoAnterior = $subCaja->saldo_actual;

        // Transacción de salida (prestamista)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCaja->id,
            'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $solicitud->monto_solicitado,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoAnterior,
            'descripcion' => "Préstamo de efectivo a {$solicitud->vendedorSolicitante->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_prestamista_id,
            'fecha' => now(),
        ]);

        // Transacción de entrada (solicitante)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCaja->id,
            'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $solicitud->monto_solicitado,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoAnterior,
            'descripcion' => "Préstamo de efectivo recibido de {$solicitud->vendedorPrestamista->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_solicitante_id,
            'fecha' => now(),
        ]);

        // Movimientos
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCaja->caja_principal_id,
            'sub_caja_id' => $subCaja->id,
            'cajero_id' => $solicitud->vendedor_prestamista_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'salida',
            'concepto' => "Préstamo de S/. {$solicitud->monto_solicitado} a {$solicitud->vendedorSolicitante->name}",
            'saldo_inicial' => $saldoAnterior,
            'ingreso' => 0,
            'salida' => $solicitud->monto_solicitado,
            'saldo_final' => $saldoAnterior,
            'estado_caja' => 'abierta',
        ]);

        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCaja->caja_principal_id,
            'sub_caja_id' => $subCaja->id,
            'cajero_id' => $solicitud->vendedor_solicitante_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'ingreso',
            'concepto' => "Préstamo de S/. {$solicitud->monto_solicitado} recibido de {$solicitud->vendedorPrestamista->name}",
            'saldo_inicial' => $saldoAnterior,
            'ingreso' => $solicitud->monto_solicitado,
            'salida' => 0,
            'saldo_final' => $saldoAnterior,
            'estado_caja' => 'abierta',
        ]);
    }
}
