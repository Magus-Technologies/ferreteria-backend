<?php

namespace App\Services\Implementations;

use App\DTOs\MovimientoInterno\CrearMovimientoInternoDTO;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\AperturaCierreCaja;
use App\Models\DespliegueDePago;
use App\Models\MovimientoCaja;
use App\Models\MovimientoInterno;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use App\Services\Interfaces\MovimientoInternoServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovimientoInternoService implements MovimientoInternoServiceInterface
{
    public function crearMovimiento(CrearMovimientoInternoDTO $dto, string|int $userId): array
    {
        return DB::transaction(function () use ($dto, $userId) {
            // Validar que el userId sea válido
            if (empty($userId)) {
                throw new \Exception('Usuario no autenticado');
            }
            
            // Obtener sub-cajas
            $subCajaOrigen = SubCaja::with('cajaPrincipal.user')->findOrFail($dto->subCajaOrigenId);
            $subCajaDestino = SubCaja::with('cajaPrincipal.user')->findOrFail($dto->subCajaDestinoId);

            // Obtener despliegues de pago
            $desplieguePagoOrigen = DespliegueDePago::with('metodoDePago')
                ->findOrFail($dto->despliegueDePagoOrigenId);
            $desplieguePagoDestino = DespliegueDePago::with('metodoDePago')
                ->findOrFail($dto->despliegueDePagoDestinoId);

            // Obtener usuario actual
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }
            
            // Validar permisos:
            // - Si es admin, puede mover dinero entre cualquier sub-caja
            // - Si no es admin, solo puede mover entre sus propias sub-cajas
            $esAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');
            
            if (!$esAdmin) {
                if ($subCajaOrigen->cajaPrincipal->user_id !== $userId || 
                    $subCajaDestino->cajaPrincipal->user_id !== $userId) {
                    throw new \Exception('Solo puedes mover dinero entre tus propias sub-cajas');
                }
            }

            // Validar saldo suficiente del vendedor en la sub-caja origen
            $saldoDisponibleVendedor = $this->calcularSaldoVendedorEnSubCaja(
                $subCajaOrigen->id,
                $userId,
                $dto->despliegueDePagoOrigenId
            );
            
            if ($saldoDisponibleVendedor < $dto->monto) {
                throw new SaldoInsuficienteException(
                    $saldoDisponibleVendedor, 
                    $dto->monto,
                    "Saldo insuficiente. Tu saldo disponible en {$subCajaOrigen->nombre} - {$desplieguePagoOrigen->name}: S/ {$saldoDisponibleVendedor}"
                );
            }

            // Crear el movimiento interno
            $movimiento = MovimientoInterno::create([
                'id' => (string) Str::ulid(),
                'sub_caja_origen_id' => $dto->subCajaOrigenId,
                'sub_caja_destino_id' => $dto->subCajaDestinoId,
                'monto' => $dto->monto,
                'despliegue_de_pago_origen_id' => $dto->despliegueDePagoOrigenId,
                'despliegue_de_pago_destino_id' => $dto->despliegueDePagoDestinoId,
                'justificacion' => $dto->justificacion,
                'comprobante' => $dto->comprobante,
                'numero_operacion' => $dto->numeroOperacion,
                'user_id' => $userId,
                'fecha' => now(),
            ]);

            // Registrar transacciones
            $this->registrarTransacciones(
                $movimiento,
                $desplieguePagoOrigen,
                $desplieguePagoDestino,
                $subCajaOrigen,
                $subCajaDestino,
                $dto->monto,
                $userId
            );

            return [
                'id' => $movimiento->id,
                'sub_caja_origen' => $subCajaOrigen->nombre,
                'sub_caja_destino' => $subCajaDestino->nombre,
                'metodo_pago_origen' => $desplieguePagoOrigen->name,
                'metodo_pago_destino' => $desplieguePagoDestino->name,
                'monto' => number_format($dto->monto, 2, '.', ''),
                'justificacion' => $dto->justificacion,
                'fecha' => $movimiento->fecha ? $movimiento->fecha->toIso8601String() : now()->toIso8601String(),
            ];
        });
    }

    public function listarMovimientos(string|int $userId): array
    {
        $movimientos = MovimientoInterno::with([
            'subCajaOrigen',
            'subCajaDestino',
            'desplieguePagoOrigen.metodoDePago',
            'desplieguePagoDestino.metodoDePago',
            'user'
        ])
        ->where('user_id', $userId)
        ->orderBy('fecha', 'desc')
        ->get();

        return $movimientos->map(function ($mov) {
            return [
                'id' => $mov->id,
                'sub_caja_origen' => $mov->subCajaOrigen->nombre,
                'sub_caja_destino' => $mov->subCajaDestino->nombre,
                'metodo_origen' => $mov->desplieguePagoOrigen->name,
                'banco_origen' => $mov->desplieguePagoOrigen->metodoDePago->name,
                'metodo_destino' => $mov->desplieguePagoDestino->name,
                'banco_destino' => $mov->desplieguePagoDestino->metodoDePago->name,
                'monto' => $mov->monto,
                'justificacion' => $mov->justificacion,
                'fecha' => $mov->fecha,
                'vendedor' => $mov->user->name,
            ];
        })->toArray();
    }

    public function listarDepositosSeguridad(string|int $userId): array
    {
        // Filtrar solo movimientos donde origen es efectivo y destino es banco/billetera
        $depositos = MovimientoInterno::with([
            'subCajaOrigen',
            'subCajaDestino',
            'desplieguePagoOrigen.metodoDePago',
            'desplieguePagoDestino.metodoDePago',
            'user'
        ])
        ->where('user_id', $userId)
        ->whereHas('desplieguePagoOrigen.metodoDePago', function ($query) {
            $query->where('name', 'LIKE', '%EFECTIVO%');
        })
        ->whereHas('desplieguePagoDestino.metodoDePago', function ($query) {
            $query->where('name', 'NOT LIKE', '%EFECTIVO%');
        })
        ->orderBy('fecha', 'desc')
        ->get();

        return $depositos->map(function ($dep) {
            return [
                'id' => $dep->id,
                'vendedor' => $dep->user->name,
                'sub_caja_origen' => $dep->subCajaOrigen->nombre,
                'sub_caja_destino' => $dep->subCajaDestino->nombre,
                'metodo_destino' => $dep->desplieguePagoDestino->name,
                'banco_destino' => $dep->desplieguePagoDestino->metodoDePago->name,
                'titular' => $dep->desplieguePagoDestino->metodoDePago->nombre_titular,
                'monto' => $dep->monto,
                'motivo' => $dep->justificacion,
                'fecha' => $dep->fecha,
                'tipo' => 'deposito_seguridad',
            ];
        })->toArray();
    }

    private function obtenerSaldoDespliegue(string $desplieguePagoId): float
    {
        $transacciones = TransaccionCaja::where('despliegue_pago_id', $desplieguePagoId)->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        return $ingresos - $egresos;
    }

    /**
     * Calcular el saldo disponible de un vendedor específico en una sub-caja y despliegue de pago
     */
    private function calcularSaldoVendedorEnSubCaja(int $subCajaId, string|int $userId, string $desplieguePagoId): float
    {
        $transacciones = TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where('user_id', $userId)
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        return $ingresos - $egresos;
    }

    private function registrarTransacciones(
        MovimientoInterno $movimiento,
        DespliegueDePago $desplieguePagoOrigen,
        DespliegueDePago $desplieguePagoDestino,
        SubCaja $subCajaOrigen,
        SubCaja $subCajaDestino,
        float $monto,
        string|int $userId
    ): void {
        // Obtener apertura activa del usuario
        $apertura = AperturaCierreCaja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->first();

        if (!$apertura) {
            throw new \Exception('No tienes una caja abierta para realizar movimientos');
        }

        // Transacción de EGRESO (origen)
        $saldoOrigenAnterior = $this->obtenerSaldoDespliegue($desplieguePagoOrigen->id);
        
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaOrigen->id,
            'despliegue_pago_id' => $desplieguePagoOrigen->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoOrigenAnterior,
            'saldo_nuevo' => $saldoOrigenAnterior - $monto,
            'descripcion' => "Movimiento interno: {$desplieguePagoOrigen->name} → {$desplieguePagoDestino->name} (a {$subCajaDestino->nombre})",
            'referencia_id' => $movimiento->id,
            'referencia_tipo' => 'movimiento_interno',
            'user_id' => $userId,
            'fecha' => now(),
        ]);

        // Transacción de INGRESO (destino)
        $saldoDestinoAnterior = $this->obtenerSaldoDespliegue($desplieguePagoDestino->id);
        
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaDestino->id,
            'despliegue_pago_id' => $desplieguePagoDestino->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoDestinoAnterior,
            'saldo_nuevo' => $saldoDestinoAnterior + $monto,
            'descripcion' => "Movimiento interno: {$desplieguePagoOrigen->name} → {$desplieguePagoDestino->name} (desde {$subCajaOrigen->nombre})",
            'referencia_id' => $movimiento->id,
            'referencia_tipo' => 'movimiento_interno',
            'user_id' => $userId,
            'fecha' => now(),
        ]);

        // Actualizar saldos de las sub-cajas
        $subCajaOrigen->saldo_actual -= $monto;
        $subCajaOrigen->save();
        
        $subCajaDestino->saldo_actual += $monto;
        $subCajaDestino->save();

        // Registrar movimientos de caja
        $this->registrarMovimientosCaja(
            $apertura,
            $subCajaOrigen,
            $subCajaDestino,
            $monto,
            $desplieguePagoOrigen->name,
            $desplieguePagoDestino->name,
            $userId
        );
    }

    private function registrarMovimientosCaja(
        AperturaCierreCaja $apertura,
        SubCaja $subCajaOrigen,
        SubCaja $subCajaDestino,
        float $monto,
        string $metodoPagoOrigen,
        string $metodoPagoDestino,
        string|int $userId
    ): void {
        // Movimiento de salida (origen) - usar 'transferencia' en lugar de 'salida'
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $apertura->id,
            'caja_principal_id' => $subCajaOrigen->caja_principal_id,
            'sub_caja_id' => $subCajaOrigen->id,
            'cajero_id' => $userId,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Movimiento interno: {$metodoPagoOrigen} → {$metodoPagoDestino} (a {$subCajaDestino->nombre})",
            'saldo_inicial' => $subCajaOrigen->saldo_actual + $monto,
            'ingreso' => 0,
            'salida' => $monto,
            'saldo_final' => $subCajaOrigen->saldo_actual,
            'estado_caja' => 'abierta',
        ]);

        // Movimiento de entrada (destino) - usar 'transferencia' en lugar de 'ingreso'
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $apertura->id,
            'caja_principal_id' => $subCajaDestino->caja_principal_id,
            'sub_caja_id' => $subCajaDestino->id,
            'cajero_id' => $userId,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Movimiento interno: {$metodoPagoOrigen} → {$metodoPagoDestino} (desde {$subCajaOrigen->nombre})",
            'saldo_inicial' => $subCajaDestino->saldo_actual - $monto,
            'ingreso' => $monto,
            'salida' => 0,
            'saldo_final' => $subCajaDestino->saldo_actual,
            'estado_caja' => 'abierta',
        ]);
    }
}
