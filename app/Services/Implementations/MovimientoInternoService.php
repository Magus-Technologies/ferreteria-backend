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
            
            // Obtener sub-cajas. Si origen y destino son la MISMA sub-caja
            // (traslado de dinero cerrado al efectivo de sesión de un usuario),
            // reutilizar la misma instancia para que los ajustes de saldo no se
            // pisen entre sí.
            $subCajaOrigen = SubCaja::with('cajaPrincipal.user')->findOrFail($dto->subCajaOrigenId);
            $subCajaDestino = $dto->subCajaDestinoId === $dto->subCajaOrigenId
                ? $subCajaOrigen
                : SubCaja::with('cajaPrincipal.user')->findOrFail($dto->subCajaDestinoId);

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

            // TRASLADO DE EFECTIVO: mover efectivo entre despliegues de efectivo
            // (o al efectivo de sesión de un usuario, incluso en la misma sub-caja).
            $esTrasladoEfectivo = $desplieguePagoOrigen && $desplieguePagoDestino
                && str_contains(mb_strtoupper((string) ($desplieguePagoOrigen->metodoDePago?->name ?? $desplieguePagoOrigen->name)), 'EFECTIVO')
                && str_contains(mb_strtoupper((string) ($desplieguePagoDestino->metodoDePago?->name ?? $desplieguePagoDestino->name)), 'EFECTIVO');

            // REGLA GENERAL: en el ORIGEN solo se mueve el dinero CERRADO
            // (acumulado de sesiones cerradas). Lo de la sesión abierta —incluido
            // el monto con el que se aperturó— NO suma como origen; recién se
            // podrá mover al cerrar caja.
            $saldoMovible = $this->calcularSaldoMovible($subCajaOrigen);
            if ($dto->monto > $saldoMovible + 0.001) {
                throw new SaldoInsuficienteException(
                    max($saldoMovible, 0),
                    $dto->monto,
                    "Solo puedes mover dinero de caja CERRADA. Disponible en {$subCajaOrigen->nombre}: S/ "
                        . number_format(max($saldoMovible, 0), 2)
                        . ' (lo de la sesión abierta se podrá mover al cerrar caja)'
                );
            }

            // Flujo original con despliegues NO-efectivo: además valida el saldo
            // del vendedor en ese despliegue.
            if (!$esTrasladoEfectivo && $desplieguePagoOrigen) {
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
                $dto->concepto,
                $dto->destinoUserId
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

    public function listarMovimientosPorCajaPrincipal(int $cajaPrincipalId): array
    {
        // Un movimiento pertenece a esta caja principal si su sub-caja de origen O de
        // destino le pertenece (cubre traslados dentro de la misma caja general y los
        // que mueven dinero hacia/desde otra caja principal).
        $subCajaIds = SubCaja::where('caja_principal_id', $cajaPrincipalId)->pluck('id');

        $movimientos = MovimientoInterno::with([
            'subCajaOrigen',
            'subCajaDestino',
            'desplieguePagoOrigen.metodoDePago',
            'desplieguePagoDestino.metodoDePago',
            'user'
        ])
        ->where(function ($q) use ($subCajaIds) {
            $q->whereIn('sub_caja_origen_id', $subCajaIds)
                ->orWhereIn('sub_caja_destino_id', $subCajaIds);
        })
        ->orderBy('fecha', 'desc')
        ->get();

        // El usuario AL QUE se le acreditó el dinero no se guarda en movimientos_internos
        // (esa tabla solo tiene el user_id de quien REALIZÓ el traslado); se resuelve desde
        // la transacción de INGRESO que registrarTransacciones() crea en transacciones_caja
        // (referencia_id = movimiento.id, referencia_tipo = 'movimiento_interno').
        $movimientoIds = $movimientos->pluck('id');
        $usuariosDestinoPorMovimiento = $movimientoIds->isEmpty() ? collect() : DB::table('transacciones_caja as tc')
            ->join('user as u', 'tc.user_id', '=', 'u.id')
            ->whereIn('tc.referencia_id', $movimientoIds)
            ->where('tc.referencia_tipo', 'movimiento_interno')
            ->where('tc.tipo_transaccion', 'ingreso')
            ->select('tc.referencia_id', 'u.name')
            ->get()
            ->keyBy('referencia_id');

        return $movimientos->map(function ($mov) use ($usuariosDestinoPorMovimiento) {
            return [
                'id' => $mov->id,
                'sub_caja_origen' => $mov->subCajaOrigen->nombre,
                'sub_caja_destino' => $mov->subCajaDestino->nombre,
                'metodo_origen' => $mov->desplieguePagoOrigen?->name ?? $mov->concepto ?? '-',
                'banco_origen' => $mov->desplieguePagoOrigen?->metodoDePago?->name ?? '-',
                'metodo_destino' => $mov->desplieguePagoDestino?->name ?? $mov->concepto ?? '-',
                'banco_destino' => $mov->desplieguePagoDestino?->metodoDePago?->name ?? '-',
                'concepto' => $mov->concepto,
                'monto' => $mov->monto,
                'justificacion' => $mov->justificacion,
                'fecha' => $mov->fecha,
                // Quién REALIZÓ el traslado.
                'vendedor' => $mov->user->name,
                // A quién se le acreditó el dinero (si no se resolvió, es el mismo usuario).
                'usuario_destino' => $usuariosDestinoPorMovimiento->get($mov->id)?->name ?? $mov->user->name,
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

        // Dinero de la SESIÓN presente en la sub-caja: todos los ingresos de la
        // sesión menos sus egresos, EXCLUYENDO los egresos por movimiento interno
        // (esos salen del dinero cerrado por regla). Así, un traslado de cerrado
        // → sesión reduce el movible y no se puede mover dos veces.
        $ingresosSesion = (float) $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresosSesion = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        $dineroSesion = $ingresosSesion - $egresosSesion;

        // El monto de APERTURA sale físicamente del dinero cerrado del cajón:
        // también se descuenta del movible (evita aperturar con 80,000 y luego
        // trasladar "otros" 80,000 cerrados que son el mismo dinero).
        $montoApertura = ((int) $apertura->sub_caja_id === (int) $subCaja->id)
            ? (float) $apertura->monto_apertura
            : 0.0;

        return $saldoActual - max($dineroSesion, 0) - $montoApertura;
    }

    public function saldosDisponibles(): array
    {
        return SubCaja::with('cajaPrincipal:id,nombre')
            ->where('estado', true)
            ->get()
            ->map(function (SubCaja $subCaja) {
                $cerrado = round(max($this->calcularSaldoMovible($subCaja), 0), 2);

                // NO CERRADO = todo lo demás del saldo: dinero de la sesión
                // abierta + monto de apertura (el movible ya los descuenta).
                // Así siempre se cumple: Cerrado + No Cerrado = Saldo total.
                $noCerrado = round(max((float) $subCaja->saldo_actual - $cerrado, 0), 2);

                return [
                    'sub_caja_id' => $subCaja->id,
                    'nombre' => $subCaja->nombre,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_actual' => (float) $subCaja->saldo_actual,
                    'saldo_disponible' => $cerrado,
                    'saldo_no_cerrado' => $noCerrado,
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
        ?string $concepto = null,
        ?string $destinoUserId = null
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
            // El ingreso se ACREDITA al usuario destino (su efectivo por
            // vendedor sube); si no se indicó, queda a nombre de quien movió.
            'user_id' => $destinoUserId ?: $userId,
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
