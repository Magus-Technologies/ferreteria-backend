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
use App\Models\User;
use App\Services\Cajas\EfectivoDisponibleService;
use App\Services\Interfaces\MovimientoInternoServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovimientoInternoService implements MovimientoInternoServiceInterface
{
    public function __construct(
        private EfectivoDisponibleService $efectivoDisponibleService
    ) {}

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

            // Validar que el usuario exista. El control de quién puede registrar un
            // movimiento interno lo maneja el sistema de permisos (guard de la ruta),
            // no una comparación de dueño de la caja principal: "Caja General" es
            // compartida entre varios vendedores (por apertura/distribución) y ninguno
            // de ellos es el "user_id" dueño del registro de caja_principal, así que esa
            // validación bloqueaba a TODO vendedor no-admin sin importar qué eligiera.
            if (!\App\Models\User::where('id', $userId)->exists()) {
                throw new \Exception('Usuario no encontrado');
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

            // Snapshot del saldo MOVIBLE (Saldo Cerrado) de ambas sub-cajas ANTES de
            // mover el dinero — mismo patrón que el kardex de stock (stock_anterior/
            // stock_actual): se guarda en el propio registro para que el historial
            // pueda mostrar "antes → después" sin tener que recalcular en vivo hacia
            // atrás en el tiempo (el saldo movible cambia con cada movimiento nuevo).
            $saldoOrigenAnteriorMovible = $saldoMovible;
            $saldoDestinoAnteriorMovible = ($subCajaDestino->id === $subCajaOrigen->id)
                ? $saldoOrigenAnteriorMovible
                : $this->calcularSaldoMovible($subCajaDestino);

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
                // TRASLADO DE EFECTIVO: marca que el dinero fue a la SESIÓN ABIERTA
                // de este usuario, no a otro cajón. Es lo que hace que el monto deje
                // de contar como saldo movible (ver calcularSaldoMovible()).
                'destino_user_id' => $dto->destinoUserId,
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

            // Snapshot DESPUÉS de mover el dinero. Se recalcula en vivo (no se suma/
            // resta el monto a mano) para reflejar exactamente lo que quedó registrado
            // por registrarTransacciones(), incluida cualquier auto-cancelación si
            // origen y destino son la misma sub-caja.
            $subCajaOrigen->refresh();
            $subCajaDestino->refresh();
            $saldoOrigenActualMovible = $this->calcularSaldoMovible($subCajaOrigen);
            $saldoDestinoActualMovible = ($subCajaDestino->id === $subCajaOrigen->id)
                ? $saldoOrigenActualMovible
                : $this->calcularSaldoMovible($subCajaDestino);

            $movimiento->update([
                'saldo_origen_anterior' => $saldoOrigenAnteriorMovible,
                'saldo_origen_actual' => $saldoOrigenActualMovible,
                'saldo_destino_anterior' => $saldoDestinoAnteriorMovible,
                'saldo_destino_actual' => $saldoDestinoActualMovible,
            ]);

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

    /**
     * Historial completo de movimientos internos (tab "Traslado de Efectivo").
     *
     * Devuelve los de TODOS los usuarios: la tabla del front filtra en el cliente
     * (arranca precargada con el usuario logueado salvo roles administrativos, ver
     * useVeTodosLosMovimientos). Antes filtraba acá por `user_id`, así que ni un
     * admin podía ver los traslados de otro vendedor y el selector "Usuario:
     * Todos" de esa pantalla no tenía ningún efecto. Mismo criterio que
     * listarMovimientosPorCajaPrincipal(), que tampoco filtra por usuario.
     *
     * $userId se conserva por compatibilidad de la interfaz (ya no se usa para
     * acotar el resultado).
     */
    public function listarMovimientos(string|int $userId): array
    {
        $movimientos = MovimientoInterno::with([
            'subCajaOrigen',
            'subCajaDestino',
            'desplieguePagoOrigen.metodoDePago',
            'desplieguePagoDestino.metodoDePago',
            'user'
        ])
        ->orderBy('fecha', 'desc')
        ->get();

        return $this->mapearFilas($movimientos);
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

        return $this->mapearFilas($movimientos);
    }

    /**
     * Forma PLANA de fila que consumen las tablas de historial (sub_caja_origen es
     * el NOMBRE, no un objeto anidado). Compartida por listarMovimientos() y
     * listarMovimientosPorCajaPrincipal() para que ambas pantallas muestren
     * exactamente los mismos campos.
     */
    private function mapearFilas(Collection $movimientos): array
    {
        // Nombre del usuario AL QUE se le acreditó el dinero (solo en TRASLADO DE
        // EFECTIVO). Se resuelve en un solo query desde `destino_user_id`; antes
        // había que deducirlo con un join a la transacción de ingreso porque la
        // columna no existía.
        $destinoIds = $movimientos->pluck('destino_user_id')->filter()->unique();
        $nombresDestino = $destinoIds->isEmpty()
            ? collect()
            : User::whereIn('id', $destinoIds)->pluck('name', 'id');

        return $movimientos->map(function ($mov) use ($nombresDestino) {
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
                // Quién REALIZÓ el traslado.
                'vendedor' => $mov->user->name,
                'user_id' => $mov->user_id,
                // A quién se le acreditó el dinero: en un movimiento cajón → cajón
                // (sin destino_user_id) es el mismo que lo realizó.
                'usuario_destino' => $nombresDestino->get($mov->destino_user_id) ?? $mov->user->name,
                'destino_user_id' => $mov->destino_user_id,
                'estado' => $mov->estado,
            ];
        })->toArray();
    }

    public function anularMovimiento(string $movimientoId, string|int $userId): void
    {
        DB::transaction(function () use ($movimientoId, $userId) {
            $movimiento = MovimientoInterno::with(['subCajaOrigen', 'subCajaDestino'])
                ->lockForUpdate()
                ->findOrFail($movimientoId);

            if ($movimiento->estaAnulado()) {
                throw new \Exception('Este movimiento ya fue anulado anteriormente.');
            }

            // Sin restricción de dueño: "Caja General" es compartida entre vendedores,
            // así que cualquier usuario con la caja abierta puede anular un movimiento
            // interno, no solo quien lo creó.
            $subCajaOrigen = $movimiento->subCajaOrigen;
            $subCajaDestino = $movimiento->subCajaDestino;
            $monto = (float) $movimiento->monto;

            // Resolver la MISMA apertura que registrarTransacciones() usó al crear el
            // movimiento — por eso se busca por el usuario CREADOR (movimiento->user_id),
            // no por quien está anulando ahora: los MovimientoCaja se identifican más
            // abajo por apertura_cierre_id, así que si se resolviera con la apertura del
            // usuario que anula (cuando es distinto del creador) no encontraría las filas
            // correctas y las dejaría huérfanas sin eliminar.
            $apertura = AperturaCierreCaja::where('user_id', $movimiento->user_id)
                ->where('estado', 'abierta')
                ->first();
            if (!$apertura) {
                $apertura = AperturaCierreCaja::where('caja_principal_id', $subCajaOrigen->caja_principal_id)
                    ->where('estado', 'abierta')
                    ->first();
            }
            if (!$apertura) {
                throw new \Exception('No se puede anular un movimiento de una caja ya cerrada.');
            }

            // 1. Eliminar transacciones de caja asociadas.
            TransaccionCaja::where('referencia_id', $movimiento->id)
                ->where('referencia_tipo', 'movimiento_interno')
                ->delete();

            // 2. Eliminar movimientos de caja asociados. MovimientoCaja no guarda
            // referencia_id/referencia_tipo, así que se identifican por apertura +
            // sub_caja + monto + concepto, igual que TrasladoBovedaService/
            // PrestamoVendedorService.
            MovimientoCaja::where('apertura_cierre_id', $apertura->id)
                ->where('sub_caja_id', $subCajaOrigen->id)
                ->where('salida', $monto)
                ->where('tipo_movimiento', 'transferencia')
                ->where('concepto', 'LIKE', 'Movimiento interno:%')
                ->delete();

            MovimientoCaja::where('apertura_cierre_id', $apertura->id)
                ->where('sub_caja_id', $subCajaDestino->id)
                ->where('ingreso', $monto)
                ->where('tipo_movimiento', 'transferencia')
                ->where('concepto', 'LIKE', 'Movimiento interno:%')
                ->delete();

            // 3. Restaurar saldos — inverso exacto de registrarTransacciones() (que
            // resta del origen y suma al destino). Uso increment/decrement (SQL directo)
            // para que sea seguro incluso si origen y destino son la MISMA sub-caja.
            $subCajaOrigen->increment('saldo_actual', $monto);
            $subCajaDestino->decrement('saldo_actual', $monto);

            // 4. Marcar como anulado (no se elimina, queda como registro histórico).
            $movimiento->update([
                'estado' => 'anulado',
                'fecha_anulacion' => now(),
            ]);
        });
    }

    /**
     * Listar movimientos internos para la pestaña "Movimiento entre Cajas" — antes
     * solo mostraba depósitos efectivo→banco, ahora muestra TODO lo registrado
     * desde el modal "Mover Dinero entre Sub-Cajas" (cualquier combinación de
     * métodos de pago origen/destino), del usuario indicado.
     */
    public function listarDepositosSeguridad(string|int $userId): array
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
                'vendedor' => $mov->user->name,
                'vendedor_id' => $mov->user_id,
                'sub_caja_origen' => $mov->subCajaOrigen->nombre,
                'sub_caja_destino' => $mov->subCajaDestino->nombre,
                // Null-safe: movimientos registrados con CONCEPTO (sin despliegue de
                // pago) no tienen desplieguePagoOrigen/Destino — mismo patrón que
                // listarMovimientos().
                'metodo_origen' => $mov->desplieguePagoOrigen?->name ?? $mov->concepto ?? '-',
                'banco_origen' => $mov->desplieguePagoOrigen?->metodoDePago?->name ?? '-',
                'metodo_destino' => $mov->desplieguePagoDestino?->name ?? $mov->concepto ?? '-',
                'banco_destino' => $mov->desplieguePagoDestino?->metodoDePago?->name ?? '-',
                'titular' => $mov->desplieguePagoDestino?->metodoDePago?->nombre_titular,
                'monto' => $mov->monto,
                // Saldo MOVIBLE (Saldo Cerrado) de cada sub-caja antes/después de este
                // movimiento — null en movimientos creados antes de este campo existir.
                'saldo_origen_anterior' => $mov->saldo_origen_anterior,
                'saldo_origen_actual' => $mov->saldo_origen_actual,
                'saldo_destino_anterior' => $mov->saldo_destino_anterior,
                'saldo_destino_actual' => $mov->saldo_destino_actual,
                'motivo' => $mov->justificacion,
                'fecha' => $mov->fecha,
                'estado' => $mov->estado,
                'tipo' => 'movimiento_entre_cajas',
            ];
        })->toArray();
    }

    /**
     * Saldo REAL de una sub-caja: recalculado en vivo desde todo el historial de
     * `transacciones_caja`, en vez de leer `sub_cajas.saldo_actual`.
     *
     * OJO: `saldo_actual` NO se usa a propósito — un Traslado a Bóveda registra su
     * egreso en `transacciones_caja` pero deliberadamente NO descuenta
     * `saldo_actual` (ver TrasladoBovedaService::registrarTraslado, "sigue siendo
     * dinero bajo responsabilidad de la Caja Chica"). Si se leyera la columna acá,
     * cada bóveda hecha en la sesión quedaría "fantasma": el saldo movible
     * mostraría dinero que físicamente ya no está.
     */
    private function calcularSaldoRealSubCaja(SubCaja $subCaja): float
    {
        return (float) TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_transaccion = 'ingreso' THEN monto ELSE -monto END), 0) as total")
            ->value('total');
    }

    /**
     * Saldo MOVIBLE de una sub-caja: el TOTAL desde que se aperturó (dinero de
     * sesiones ya cerradas + el monto con el que se aperturó hoy), sin contar
     * lo que entró/salió DURANTE la sesión actual (ventas, gastos, etc. — eso
     * recién se puede mover al cerrar caja). Ej: tenía 1000 cerrado, aperturé
     * con 500 → movible 1500. Si además vendo 200 hoy, esos 200 NO se pueden
     * mover todavía (siguen siendo "de la sesión"), pero el 1500 sí.
     */
    private function calcularSaldoMovible(SubCaja $subCaja): float
    {
        $saldoActual = $this->calcularSaldoRealSubCaja($subCaja);

        // El corte NO puede ser una sola fecha global de la caja principal: una
        // caja es un cajón COMPARTIDO con varias sesiones abiertas a la vez (una
        // por vendedor) que empiezan en momentos distintos. Con un corte único:
        //   - si se tomaba la apertura más reciente, la actividad de los que
        //     aperturaron antes caía por debajo y se contaba como CERRADO;
        //   - si se toma la más antigua, se cuenta como NO CERRADO el dinero de
        //     sesiones YA CERRADAS de otros usuarios (ej. un Traslado de Efectivo
        //     recibido en una sesión anterior seguía figurando como "de sesión"
        //     para siempre, aunque al cerrar esa caja ya se consolidó).
        //
        // Corte POR USUARIO: cada apertura abierta define desde cuándo cuenta la
        // actividad de SU dueño. Una transacción es "dinero de sesión" solo si su
        // `user_id` tiene sesión abierta y ocurrió a partir de la apertura de esa
        // sesión. Lo de un usuario sin sesión abierta ya está cerrado → movible.
        $aperturas = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('estado', 'abierta')
            ->get();

        if ($aperturas->isEmpty()) {
            return $saldoActual;
        }

        $cortePorUsuario = [];
        foreach ($aperturas as $ap) {
            $previo = $cortePorUsuario[$ap->user_id] ?? null;
            if ($previo === null || $ap->fecha_apertura < $previo) {
                $cortePorUsuario[$ap->user_id] = $ap->fecha_apertura;
            }
        }

        // La más antigua solo acota la query; el filtro fino es por usuario.
        $corteMasAntiguo = min($cortePorUsuario);

        // Transacciones de la sesión, EXCLUYENDO la propia fila de "apertura" —
        // ese monto ya es movible por definición (no es dinero "nuevo" de la
        // sesión, es lo que el vendedor cargó al aperturar), así que no debe
        // contarse como actividad de sesión a descontar. Antes SÍ se contaba
        // acá (como ingreso) Y se restaba OTRA VEZ más abajo con
        // `monto_apertura` — un doble descuento que dejaba el movible muy por
        // debajo de lo real.
        $transacciones = TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->where('fecha', '>=', $corteMasAntiguo)
            ->where(function ($q) {
                $q->whereNull('referencia_tipo')
                    ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get()
            // Cada transacción se mide contra la apertura de SU PROPIO usuario.
            ->filter(function ($t) use ($cortePorUsuario) {
                $corte = $cortePorUsuario[$t->user_id] ?? null;

                return $corte !== null && $t->fecha >= $corte;
            });

        // Dinero de la SESIÓN presente en la sub-caja: todos los ingresos de la
        // sesión menos sus egresos, EXCLUYENDO los de movimiento_interno en AMBOS
        // lados. Un "Mover Dinero entre Sub-Cajas" no es actividad nueva de la
        // sesión (ventas, gastos) — es dinero YA movible que solo cambió de
        // sub-caja, así que debe seguir siendo movible de inmediato en las dos
        // puntas: el egreso reduce el movible del origen ya (no se puede mover
        // dos veces) y el ingreso aumenta el movible del destino ya (no espera
        // al cierre). Antes solo se excluía el egreso — el ingreso SÍ se contaba
        // como "dinero de sesión" y se autocancelaba con el aumento del saldo
        // real, dejando el movible del destino sin cambiar tras recibir dinero.
        //
        // EXCEPCIÓN: el TRASLADO DE EFECTIVO (movimiento con destino_user_id) no
        // es cajón → cajón sino CERRADO → SESIÓN ABIERTA de un usuario. Ese
        // ingreso SÍ es dinero de sesión y debe salir del movible; si no, el
        // monto sigue figurando como disponible y se puede trasladar las veces
        // que se quiera (además, cuando origen y destino son la misma sub-caja
        // —el caso normal: el efectivo de un usuario vive en la misma sub-caja—
        // egreso e ingreso se autocancelaban y nada cambiaba).
        $idsTrasladoASesion = $this->efectivoDisponibleService->idsTrasladoASesion($transacciones);

        $ingresosSesion = (float) $transacciones
            ->where('tipo_transaccion', 'ingreso')
            ->filter(fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
                || in_array($t->referencia_id, $idsTrasladoASesion, true))
            ->sum('monto');
        $egresosSesion = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        $dineroSesion = $ingresosSesion - $egresosSesion;

        return $saldoActual - max($dineroSesion, 0);
    }

    public function saldosDisponibles(): array
    {
        return SubCaja::with('cajaPrincipal:id,nombre')
            ->where('estado', true)
            ->get()
            ->map(function (SubCaja $subCaja) {
                $saldoReal = $this->calcularSaldoRealSubCaja($subCaja);
                $cerrado = round(max($this->calcularSaldoMovible($subCaja), 0), 2);

                // NO CERRADO = todo lo demás del saldo: dinero de la sesión
                // abierta + monto de apertura (el movible ya los descuenta).
                // Así siempre se cumple: Cerrado + No Cerrado = Saldo total real
                // (recalculado en vivo, no la columna `saldo_actual` — ver
                // calcularSaldoRealSubCaja()).
                $noCerrado = round(max($saldoReal - $cerrado, 0), 2);

                return [
                    'sub_caja_id' => $subCaja->id,
                    'nombre' => $subCaja->nombre,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_actual' => round($saldoReal, 2),
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
     * Calcular el saldo MOVIBLE de la sub-caja (cajón compartido, no por
     * vendedor) para un método de pago específico (métodos NO efectivo) —
     * delegado a EfectivoDisponibleService::calcularMovibleDesdeApertura(),
     * la MISMA calculadora que alimenta lo que el modal "Mover Dinero entre
     * Sub-Cajas" le muestra (cerrado + apertura de hoy, de CUALQUIER
     * vendedor, sin la actividad de la sesión). La apertura se resuelve
     * igual que calcularSaldoMovible() (la apertura abierta MÁS ANTIGUA de esa
     * caja principal, no solo la de este usuario) para que ambas
     * validaciones queden consistentes entre sí.
     */
    private function calcularSaldoVendedorEnSubCaja(int $subCajaId, string|int $userId, string $desplieguePagoId): float
    {
        $subCaja = SubCaja::findOrFail($subCajaId);

        $apertura = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('estado', 'abierta')
            ->orderBy('fecha_apertura', 'asc')
            ->first();

        return $this->efectivoDisponibleService->calcularMovibleDesdeApertura($subCaja, $desplieguePagoId, $apertura);
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
