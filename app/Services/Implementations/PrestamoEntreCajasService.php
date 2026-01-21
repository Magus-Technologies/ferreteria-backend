<?php

namespace App\Services\Implementations;

use App\DTOs\Prestamo\AprobarPrestamoDTO;
use App\DTOs\Prestamo\CrearPrestamoDTO;
use App\DTOs\Prestamo\RechazarPrestamoDTO;
use App\Exceptions\AperturaNoActivaException;
use App\Exceptions\PermisoPrestamoException;
use App\Exceptions\PrestamoYaProcesadoException;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\PrestamoEntreCajas;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use App\Repositories\Interfaces\PrestamoEntreCajasRepositoryInterface;
use App\Services\Interfaces\PrestamoEntreCajasServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrestamoEntreCajasService implements PrestamoEntreCajasServiceInterface
{
    public function __construct(
        private PrestamoEntreCajasRepositoryInterface $prestamoRepository
    ) {}

    public function listarPrestamos(int $perPage = 15): LengthAwarePaginator
    {
        return $this->prestamoRepository->getPaginated($perPage);
    }

    public function listarPendientes(string $userId): Collection
    {
        return $this->prestamoRepository->getPendientesByUserId($userId);
    }

    public function crearSolicitud(CrearPrestamoDTO $dto): PrestamoEntreCajas
    {
        return DB::transaction(function () use ($dto) {
            // Verificar que la caja principal origen existe
            $cajaPrincipal = DB::table('cajas_principales')
                ->where('id', $dto->cajaPrincipalOrigenId)
                ->first();
                
            if (!$cajaPrincipal) {
                throw new \Exception('Caja principal origen no encontrada');
            }

            // Buscar usuario con apertura activa en caja destino
            $aperturaDestino = $this->buscarAperturaActiva($dto->subCajaDestinoId);
            if (!$aperturaDestino) {
                throw new AperturaNoActivaException('caja destino');
            }

            // Obtener el user_id del dueño de la caja principal origen
            $userPrestaId = DB::table('cajas_principales')
                ->where('id', $dto->cajaPrincipalOrigenId)
                ->value('user_id');

            // Crear solicitud de préstamo (sin sub-caja origen aún)
            return $this->prestamoRepository->create([
                'id' => (string) Str::ulid(),
                'sub_caja_origen_id' => null, // Se seleccionará al aprobar
                'sub_caja_destino_id' => $dto->subCajaDestinoId,
                'caja_principal_origen_id' => $dto->cajaPrincipalOrigenId,
                'monto' => $dto->monto,
                'despliegue_de_pago_id' => $dto->desplieguePagoId,
                'estado' => 'pendiente',
                'estado_aprobacion' => 'pendiente_aprobacion',
                'motivo' => $dto->motivo,
                'user_presta_id' => $userPrestaId,
                'user_recibe_id' => $aperturaDestino->user_id,
                'fecha_prestamo' => now(),
            ]);
        });
    }

    public function aprobar(AprobarPrestamoDTO $dto): PrestamoEntreCajas
    {
        return DB::transaction(function () use ($dto) {
            $prestamo = $this->prestamoRepository->findById($dto->prestamoId);
            
            if (!$prestamo) {
                throw new \Exception('Préstamo no encontrado');
            }

            // Verificar permisos
            if ($prestamo->user_presta_id !== $dto->aprobadorId) {
                throw new PermisoPrestamoException('aprobar');
            }

            // Verificar estado
            if ($prestamo->estado_aprobacion !== 'pendiente_aprobacion') {
                throw new PrestamoYaProcesadoException();
            }

            // Verificar que no haya expirado (1 hora)
            $unaHoraAtras = now()->subHour();
            if ($prestamo->fecha_prestamo < $unaHoraAtras) {
                // Auto-rechazar por expiración
                $this->prestamoRepository->update($dto->prestamoId, [
                    'estado' => 'cancelado',
                    'estado_aprobacion' => 'rechazado',
                    'aprobado_por_id' => $dto->aprobadorId,
                    'fecha_aprobacion' => now(),
                    'motivo_rechazo' => 'Solicitud expirada (más de 1 hora)',
                ]);
                throw new \Exception('La solicitud de préstamo ha expirado (más de 1 hora)');
            }

            // Verificar que la sub-caja origen seleccionada tenga saldo
            $subCajaOrigen = SubCaja::findOrFail($dto->subCajaOrigenId);
            if ($subCajaOrigen->saldo_actual < $prestamo->monto) {
                throw new SaldoInsuficienteException(
                    $subCajaOrigen->saldo_actual,
                    (float) $prestamo->monto
                );
            }

            // Actualizar el préstamo con la sub-caja origen seleccionada
            $prestamo->sub_caja_origen_id = $dto->subCajaOrigenId;
            $prestamo->save();
            $prestamo->refresh(['subCajaOrigen']);

            // Mover dinero
            $this->moverDineroAprobacion($prestamo, $dto->aprobadorId);

            // Actualizar préstamo
            return $this->prestamoRepository->update($dto->prestamoId, [
                'sub_caja_origen_id' => $dto->subCajaOrigenId,
                'estado_aprobacion' => 'aprobado',
                'aprobado_por_id' => $dto->aprobadorId,
                'fecha_aprobacion' => now(),
            ]);
        });
    }

    public function rechazar(RechazarPrestamoDTO $dto): PrestamoEntreCajas
    {
        return DB::transaction(function () use ($dto) {
            $prestamo = $this->prestamoRepository->findById($dto->prestamoId);
            
            if (!$prestamo) {
                throw new \Exception('Préstamo no encontrado');
            }

            // Verificar permisos
            if ($prestamo->user_presta_id !== $dto->rechazadorId) {
                throw new PermisoPrestamoException('rechazar');
            }

            // Verificar estado
            if ($prestamo->estado_aprobacion !== 'pendiente_aprobacion') {
                throw new PrestamoYaProcesadoException();
            }

            // Actualizar préstamo
            return $this->prestamoRepository->update($dto->prestamoId, [
                'estado' => 'cancelado',
                'estado_aprobacion' => 'rechazado',
                'aprobado_por_id' => $dto->rechazadorId,
                'fecha_aprobacion' => now(),
                'motivo_rechazo' => $dto->motivoRechazo ?? 'Sin motivo especificado',
            ]);
        });
    }

    public function devolver(string $prestamoId, string $userId): PrestamoEntreCajas
    {
        return DB::transaction(function () use ($prestamoId, $userId) {
            $prestamo = $this->prestamoRepository->findById($prestamoId);
            
            if (!$prestamo) {
                throw new \Exception('Préstamo no encontrado');
            }

            if ($prestamo->estado !== 'pendiente') {
                throw new \Exception('Este préstamo ya fue devuelto o cancelado');
            }

            // Verificar saldo suficiente en destino
            if ($prestamo->subCajaDestino->saldo_actual < $prestamo->monto) {
                throw new SaldoInsuficienteException(
                    $prestamo->subCajaDestino->saldo_actual,
                    (float) $prestamo->monto
                );
            }

            // Revertir movimiento de dinero
            $this->moverDineroDevolucion($prestamo, $userId);

            // Actualizar préstamo
            return $this->prestamoRepository->update($prestamoId, [
                'estado' => 'devuelto',
                'fecha_devolucion' => now(),
            ]);
        });
    }

    // Métodos privados auxiliares

    private function buscarAperturaActiva(int $subCajaId): ?object
    {
        return DB::table('apertura_cierre_caja')
            ->where('sub_caja_id', $subCajaId)
            ->where('estado', 'abierta')
            ->orderBy('fecha_apertura', 'desc')
            ->first();
    }

    private function moverDineroAprobacion(PrestamoEntreCajas $prestamo, string $aprobadorId): void
    {
        // Actualizar saldo origen
        $saldoAnteriorOrigen = $prestamo->subCajaOrigen->saldo_actual;
        $prestamo->subCajaOrigen->saldo_actual -= $prestamo->monto;
        $prestamo->subCajaOrigen->save();

        // Actualizar saldo destino
        $saldoAnteriorDestino = $prestamo->subCajaDestino->saldo_actual;
        $prestamo->subCajaDestino->saldo_actual += $prestamo->monto;
        $prestamo->subCajaDestino->save();

        // Registrar transacciones
        $this->registrarTransaccionPrestamo(
            $prestamo->subCajaOrigen->id,
            'prestamo_enviado',
            $prestamo->monto,
            $saldoAnteriorOrigen,
            $prestamo->subCajaOrigen->saldo_actual,
            "Préstamo aprobado y enviado a {$prestamo->subCajaDestino->nombre}",
            $prestamo->id,
            $aprobadorId
        );

        $this->registrarTransaccionPrestamo(
            $prestamo->subCajaDestino->id,
            'prestamo_recibido',
            $prestamo->monto,
            $saldoAnteriorDestino,
            $prestamo->subCajaDestino->saldo_actual,
            "Préstamo recibido de {$prestamo->subCajaOrigen->nombre}",
            $prestamo->id,
            $prestamo->user_recibe_id
        );
    }

    private function moverDineroDevolucion(PrestamoEntreCajas $prestamo, string $userId): void
    {
        // Actualizar saldo destino (restar)
        $saldoAnteriorDestino = $prestamo->subCajaDestino->saldo_actual;
        $prestamo->subCajaDestino->saldo_actual -= $prestamo->monto;
        $prestamo->subCajaDestino->save();

        // Actualizar saldo origen (sumar)
        $saldoAnteriorOrigen = $prestamo->subCajaOrigen->saldo_actual;
        $prestamo->subCajaOrigen->saldo_actual += $prestamo->monto;
        $prestamo->subCajaOrigen->save();

        // Registrar transacciones
        $this->registrarTransaccionPrestamo(
            $prestamo->subCajaDestino->id,
            'egreso',
            $prestamo->monto,
            $saldoAnteriorDestino,
            $prestamo->subCajaDestino->saldo_actual,
            "Devolución de préstamo a {$prestamo->subCajaOrigen->nombre}",
            $prestamo->id,
            $userId,
            'devolucion_prestamo'
        );

        $this->registrarTransaccionPrestamo(
            $prestamo->subCajaOrigen->id,
            'ingreso',
            $prestamo->monto,
            $saldoAnteriorOrigen,
            $prestamo->subCajaOrigen->saldo_actual,
            "Devolución de préstamo recibida de {$prestamo->subCajaDestino->nombre}",
            $prestamo->id,
            $userId,
            'devolucion_prestamo'
        );
    }

    private function registrarTransaccionPrestamo(
        int $subCajaId,
        string $tipoTransaccion,
        float $monto,
        float $saldoAnterior,
        float $saldoNuevo,
        string $descripcion,
        string $referenciaId,
        string $userId,
        string $referenciaTipo = 'prestamo'
    ): void {
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaId,
            'tipo_transaccion' => $tipoTransaccion,
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'descripcion' => $descripcion,
            'referencia_id' => $referenciaId,
            'referencia_tipo' => $referenciaTipo,
            'user_id' => $userId,
            'fecha' => now(),
        ]);
    }
}
