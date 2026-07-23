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

            // Obtener despliegues de pago (OPCIONALES: el modal simple usa un
            // CONCEPTO de solo nombre en lugar de despliegues reales)
            $desplieguePagoOrigen = $dto->despliegueDePagoOrigenId
                ? DespliegueDePago::with('metodoDePago')->findOrFail($dto->despliegueDePagoOrigenId)
                : null;
            $desplieguePagoDestino = $dto->despliegueDePagoDestinoId
                ? DespliegueDePago::with('metodoDePago')->findOrFail($dto->despliegueDePagoDestinoId)
                : null;

            // Obtener usuario actual
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }
            
            // Validar permisos:
            // - Si es admin, puede mover dinero entre cualquier sub-caja
            // - Si no es admin, solo puede mover entre sus propias sub-cajas
            // El admin puede venir por rol de Spatie O por el campo rol_sistema
            // (así se modela ADMINISTRADOR en esta app; hasRole('admin') daba
            // false y bloqueaba a los administradores reales).
            $esAdmin = $user->hasRole('admin')
                || $user->hasRole('administrador')
                || $user->hasRole('super-admin')
                || in_array(mb_strtoupper((string) ($user->rol_sistema ?? '')), ['ADMIN', 'ADMINISTRADOR', 'SUPER-ADMIN', 'SUPERADMIN'], true);

            if (!$esAdmin) {
                if ($subCajaOrigen->cajaPrincipal->user_id !== $userId ||
                    $subCajaDestino->cajaPrincipal->user_id !== $userId) {
                    throw new \Exception('Solo puedes mover dinero entre tus propias sub-cajas');
                }
            }

            // TRASLADO DE EFECTIVO: mover efectivo físico entre sub-cajas (ej. de
            // Caja Chica a "efectivo negro" para poder pagar una compra). Como es
            // dinero físicamente presente, se permite mover el TOTAL del saldo
            // actual y NO aplica la regla de caja cerrada.
            $esTrasladoEfectivo = $desplieguePagoOrigen && $desplieguePagoDestino
                && str_contains(mb_strtoupper((string) ($desplieguePagoOrigen->metodoDePago?->name ?? $desplieguePagoOrigen->name)), 'EFECTIVO')
                && str_contains(mb_strtoupper((string) ($desplieguePagoDestino->metodoDePago?->name ?? $desplieguePagoDestino->name)), 'EFECTIVO');

            if (!$esTrasladoEfectivo) {
                // REGLA: solo se puede mover dinero de sesiones CERRADAS. Lo generado
                // durante la apertura activa (ventas/ingresos de hoy) recién se puede
                // mover después de cerrar caja.
                $saldoMovible = $this->calcularSaldoMovible($subCajaOrigen);
                if ($dto->monto > $saldoMovible + 0.001) {
                    throw new SaldoInsuficienteException(
                        max($saldoMovible, 0),
                        $dto->monto,
                        "Solo puedes mover dinero de caja CERRADA. Disponible en {$subCajaOrigen->nombre}: S/ "
                            . number_format(max($saldoMovible, 0), 2)
                            . ' (lo generado en la sesión abierta se podrá mover al cerrar caja)'
                    );
                }
            }

            // Validar saldo suficiente en la sub-caja origen:
            // - Traslado de efectivo: contra el saldo ACTUAL de la sub-caja (permite el total)
            // - Con despliegue: saldo del vendedor en ese despliegue (flujo original)
            // - Sin despliegue (concepto): saldo total de la sub-caja
            if ($esTrasladoEfectivo) {
                $saldoSubCaja = (float) $subCajaOrigen->saldo_actual;

                if ($saldoSubCaja < $dto->monto) {
                    throw new SaldoInsuficienteException(
                        $saldoSubCaja,
                        $dto->monto,
                        "Saldo insuficiente en {$subCajaOrigen->nombre}: S/ " . number_format($saldoSubCaja, 2)
                    );
                }
            } elseif ($desplieguePagoOrigen) {
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
            } else {
                $saldoSubCaja = (float) $subCajaOrigen->saldo_actual;

                if ($saldoSubCaja < $dto->monto) {
                    throw new SaldoInsuficienteException(
                        $saldoSubCaja,
                        $dto->monto,
                        "Saldo insuficiente en {$subCajaOrigen->nombre}: S/ " . number_format($saldoSubCaja, 2)
                    );
                }
            }

            // Crear el movimiento interno
            $movimiento = MovimientoInterno::create([
                'id' => (string) Str::ulid(),
                'sub_caja_origen_id' => $dto->subCajaOrigenId,
                'sub_caja_destino_id' => $dto->subCajaDestinoId,
                'monto' => $dto->monto,
                'despliegue_de_pago_origen_id' => $dto->despliegueDePagoOrigenId,
                'despliegue_de_pago_destino_id' => $dto->despliegueDePagoDestinoId,
                'concepto' => $dto->concepto,
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
                $userId,
                $dto->concepto
            );

            return [
                'id' => $movimiento->id,
                'sub_caja_origen' => $subCajaOrigen->nombre,
                'sub_caja_destino' => $subCajaDestino->nombre,
                'metodo_pago_origen' => $desplieguePagoOrigen->name ?? $dto->concepto ?? '-',
                'metodo_pago_destino' => $desplieguePagoDestino->name ?? $dto->concepto ?? '-',
                'concepto' => $dto->concepto,
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
                // Null-safe: los movimientos con CONCEPTO no tienen despliegues
                'metodo_origen' => $mov->desplieguePagoOrigen?->name ?? $mov->concepto ?? '-',
                'banco_origen' => $mov->desplieguePagoOrigen?->metodoDePago?->name ?? '-',
                'metodo_destino' => $mov->desplieguePagoDestino?->name ?? $mov->concepto ?? '-',
                'banco_destino' => $mov->desplieguePagoDestino?->metodoDePago?->name ?? '-',
                'concepto' => $mov->concepto,
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

    /**
     * Saldo MOVIBLE de una sub-caja: solo el dinero de sesiones cerradas.
     * Si la caja principal tiene una apertura ABIERTA, se descuenta el neto
     * (ingresos - egresos) generado desde esa apertura; ese dinero recién se
     * puede mover al cerrar caja. Ej: aperturé con 10, vendí 20 → saldo 30,
     * movible 10.
     */
    private function calcularSaldoMovible(SubCaja $subCaja): float
    {
        $saldoActual = (float) $subCaja->saldo_actual;

        $apertura = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('estado', 'abierta')
            ->orderBy('fecha_apertura', 'desc')
            ->first();

        if (!$apertura) {
            return $saldoActual;
        }

        $transacciones = TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->where('fecha', '>=', $apertura->fecha_apertura)
            ->get();

        $neto = (float) $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto')
            - (float) $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

        // Solo se descuenta lo GANADO en la sesión abierta; si la sesión gastó
        // dinero cerrado (neto negativo), el movible es el saldo actual.
        return $saldoActual - max($neto, 0);
    }

    public function saldosDisponibles(): array
    {
        return SubCaja::with('cajaPrincipal:id,nombre')
            ->where('estado', true)
            ->get()
            ->map(function (SubCaja $subCaja) {
                return [
                    'sub_caja_id' => $subCaja->id,
                    'nombre' => $subCaja->nombre,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_actual' => (float) $subCaja->saldo_actual,
                    'saldo_disponible' => round(max($this->calcularSaldoMovible($subCaja), 0), 2),
                ];
            })
            ->toArray();
    }

    public function usuariosConSaldoDisponible(): array
    {
        $sesiones = AperturaCierreCaja::with('user')
            ->get();

        $result = [];

        foreach ($sesiones as $sesion) {
            $subCaja = SubCaja::find($sesion->sub_caja_id);
            if (!$subCaja) continue;

            $desplieguesPago = $subCaja->getDesplieguePagos();

            foreach ($desplieguesPago as $despliegue) {
                $esEfectivo = str_contains(mb_strtolower($despliegue->metodoDePago?->name ?? $despliegue->name), 'efectivo');
                if (!$esEfectivo) continue;

                $uniqueKey = "{$sesion->user_id}-{$subCaja->id}-{$despliegue->id}";

                if (!isset($result[$uniqueKey])) {
                    $banco = $despliegue->metodoDePago?->name ?? 'Sin Banco';
                    $metodo = $despliegue->name;
                    $titular = $despliegue->metodoDePago?->nombre_titular ?? '';
                    $label = $titular
                        ? "{$subCaja->nombre}/{$banco}/{$metodo}/{$titular}"
                        : "{$subCaja->nombre}/{$banco}/{$metodo}";

                    $result[$uniqueKey] = [
                        'user_id' => $sesion->user_id,
                        'user_name' => $sesion->user?->name ?? 'Usuario',
                        'sub_caja_id' => $subCaja->id,
                        'sub_caja_nombre' => $subCaja->nombre,
                        'despliegue_pago_id' => $despliegue->id,
                        'value' => "{$subCaja->id}-{$despliegue->id}",
                        'label' => $label,
                        'monto_disponible' => 0,
                    ];
                }

                $monto = ($sesion->estado === 'cerrada')
                    ? (float) ($sesion->monto_cierre_efectivo ?? 0)
                    : (float) ($sesion->monto_apertura ?? 0);

                $result[$uniqueKey]['monto_disponible'] += $monto;
            }
        }

        return array_values($result);
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
        ?DespliegueDePago $desplieguePagoOrigen,
        ?DespliegueDePago $desplieguePagoDestino,
        SubCaja $subCajaOrigen,
        SubCaja $subCajaDestino,
        float $monto,
        string|int $userId,
        ?string $concepto = null
    ): void {
        // Etiqueta del movimiento: el CONCEPTO (si se usó el modal simple) o los
        // nombres de los despliegues (flujo original).
        $etiqueta = $concepto
            ?: (($desplieguePagoOrigen?->name ?? '-') . ' → ' . ($desplieguePagoDestino?->name ?? '-'));
        // Obtener apertura activa - primero intentar del usuario, sino de la caja principal
        $apertura = AperturaCierreCaja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->first();

        // Si el usuario no tiene apertura propia (ej: vendedor), buscar apertura de la caja principal
        if (!$apertura) {
            $apertura = AperturaCierreCaja::where('caja_principal_id', $subCajaOrigen->caja_principal_id)
                ->where('estado', 'abierta')
                ->first();
        }

        if (!$apertura) {
            throw new \Exception('No hay una caja abierta para realizar movimientos');
        }

        // Transacción de EGRESO (origen). Sin despliegue, el saldo de referencia
        // es el de la sub-caja.
        $saldoOrigenAnterior = $desplieguePagoOrigen
            ? $this->obtenerSaldoDespliegue($desplieguePagoOrigen->id)
            : (float) $subCajaOrigen->saldo_actual;

        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaOrigen->id,
            'despliegue_pago_id' => $desplieguePagoOrigen?->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoOrigenAnterior,
            'saldo_nuevo' => $saldoOrigenAnterior - $monto,
            'descripcion' => "Movimiento interno: {$etiqueta} (a {$subCajaDestino->nombre})",
            'referencia_id' => $movimiento->id,
            'referencia_tipo' => 'movimiento_interno',
            'user_id' => $userId,
            'fecha' => now(),
        ]);

        // Transacción de INGRESO (destino)
        $saldoDestinoAnterior = $desplieguePagoDestino
            ? $this->obtenerSaldoDespliegue($desplieguePagoDestino->id)
            : (float) $subCajaDestino->saldo_actual;

        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaDestino->id,
            'despliegue_pago_id' => $desplieguePagoDestino?->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoDestinoAnterior,
            'saldo_nuevo' => $saldoDestinoAnterior + $monto,
            'descripcion' => "Movimiento interno: {$etiqueta} (desde {$subCajaOrigen->nombre})",
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
            $etiqueta,
            $userId
        );
    }

    private function registrarMovimientosCaja(
        AperturaCierreCaja $apertura,
        SubCaja $subCajaOrigen,
        SubCaja $subCajaDestino,
        float $monto,
        string $etiqueta,
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
            'concepto' => "Movimiento interno: {$etiqueta} (a {$subCajaDestino->nombre})",
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
            'concepto' => "Movimiento interno: {$etiqueta} (desde {$subCajaOrigen->nombre})",
            'saldo_inicial' => $subCajaDestino->saldo_actual - $monto,
            'ingreso' => $monto,
            'salida' => 0,
            'saldo_final' => $subCajaDestino->saldo_actual,
            'estado_caja' => 'abierta',
        ]);
    }
}
