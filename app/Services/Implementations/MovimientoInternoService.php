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
     * Suma el libro completo, sin excepciones. Ya no hace falta excluir el
     * TRASLADO A BÓVEDA: dejó de escribirse en `transacciones_caja` porque es solo
     * un registro —el efectivo se queda en la Caja Chica— y su fila vivía únicamente
     * para que después cada calculador tuviera que acordarse de ignorarla. Los que
     * lo hacían daban un saldo y los que no, otro.
     *
     * Con `$desplieguePagoId` acota a un método de pago concreto, para el selector
     * "Método de Pago Origen" del modal Mover Dinero.
     */
    private function calcularSaldoRealSubCaja(SubCaja $subCaja, ?string $desplieguePagoId = null): float
    {
        return (float) TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->when($desplieguePagoId, fn ($q) => $q->where('despliegue_pago_id', $desplieguePagoId))
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_transaccion = 'ingreso' THEN monto ELSE -monto END), 0) as total")
            ->value('total');
    }

    /**
     * Transacciones que pertenecen a alguna SESIÓN ABIERTA de la sub-caja.
     *
     * Devuelve null si no hay ninguna sesión abierta en la caja principal (no es
     * lo mismo que una colección vacía: sin sesiones, todo el saldo es movible).
     *
     * El corte NO puede ser una sola fecha global: una caja es un cajón COMPARTIDO
     * con varias sesiones abiertas a la vez (una por vendedor) que empiezan en
     * momentos distintos. Con un corte único:
     *   - tomando la apertura más reciente, la actividad de los que aperturaron
     *     antes caía por debajo y se contaba como CERRADO;
     *   - tomando la más antigua, se contaba como NO CERRADO el dinero de sesiones
     *     YA CERRADAS de otros usuarios (un Traslado de Efectivo recibido en una
     *     sesión anterior seguía figurando como "de sesión" para siempre).
     *
     * Por eso el corte es POR USUARIO: una transacción es "dinero de sesión" solo
     * si su `user_id` tiene sesión abierta y ocurrió a partir de la apertura de esa
     * sesión. Lo de un usuario sin sesión abierta ya está cerrado → movible.
     *
     * Incluye la fila de "apertura": el monto con el que un vendedor aperturó es
     * dinero de su sesión y no se puede trasladar hasta que cierre, igual que sus
     * ventas o sus cobros.
     *
     * Es la ÚNICA definición de "transacciones de sesión" del servicio: la usan
     * tanto calcularSaldoMovible() (que alimenta las columnas Cerrado / No Cerrado)
     * como detalleNoCerrado() (el desglose por método y usuario), para que el
     * detalle siempre sume exactamente lo que muestra la columna.
     */
    private function transaccionesDeSesion(SubCaja $subCaja, ?string $desplieguePagoId = null): ?Collection
    {
        $aperturas = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('estado', 'abierta')
            ->get();

        if ($aperturas->isEmpty()) {
            return null;
        }

        $cortePorUsuario = [];
        foreach ($aperturas as $ap) {
            $previo = $cortePorUsuario[$ap->user_id] ?? null;
            if ($previo === null || $ap->fecha_apertura < $previo) {
                $cortePorUsuario[$ap->user_id] = $ap->fecha_apertura;
            }
        }

        // La más antigua solo acota la query; el filtro fino es por usuario.
        //
        // Se EXCLUYE `monto_inicial`: es el saldo con el que se configuró un banco, no
        // efectivo que un vendedor tenga en mano. La transacción se graba con el
        // usuario que editó el banco y con la fecha del momento, así que caía dentro de
        // su sesión abierta y aparecía como "No Cerrado" a su nombre — un banco con
        // 50,000 de saldo inicial se le atribuía entero a quien lo configuró. Es dinero
        // consolidado: va a CERRADO. Mismo criterio que ClasificadorMovimientos, que ya
        // lo excluye de los ingresos del cierre.
        return TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->when($desplieguePagoId, fn ($q) => $q->where('despliegue_pago_id', $desplieguePagoId))
            ->where('fecha', '>=', min($cortePorUsuario))
            ->where(function ($q) {
                $q->whereNull('referencia_tipo')
                    ->orWhere('referencia_tipo', '!=', 'monto_inicial');
            })
            ->get()
            ->filter(function ($t) use ($cortePorUsuario) {
                $corte = $cortePorUsuario[$t->user_id] ?? null;

                return $corte !== null && $t->fecha >= $corte;
            });
    }

    /**
     * Saldo MOVIBLE de una sub-caja: el TOTAL desde que se aperturó (dinero de
     * sesiones ya cerradas + el monto con el que se aperturó hoy), sin contar
     * lo que entró/salió DURANTE la sesión actual (ventas, gastos, etc. — eso
     * recién se puede mover al cerrar caja). Ej: tenía 1000 cerrado, aperturé
     * con 500 → movible 1500. Si además vendo 200 hoy, esos 200 NO se pueden
     * mover todavía (siguen siendo "de la sesión"), pero el 1500 sí.
     */
    private function calcularSaldoMovible(SubCaja $subCaja, ?string $desplieguePagoId = null): float
    {
        $saldoActual = $this->calcularSaldoRealSubCaja($subCaja, $desplieguePagoId);

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
        $transacciones = $this->transaccionesDeSesion($subCaja, $desplieguePagoId);

        if ($transacciones === null) {
            return $saldoActual;
        }

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

        // El TRASLADO A BÓVEDA saca el dinero de la sesión del vendedor pero NO de la
        // sub-caja: sigue siendo plata de la empresa, solo guardada. Restarlo acá lo
        // mueve de "No Cerrado" a "Cerrado" sin cambiar el total, que es justo lo que
        // significa mandarlo a la bóveda.
        //
        // Se lee de la tabla `traslados_boveda` porque el traslado dejó de escribir en
        // `transacciones_caja` (el dinero no sale de la caja). Sin esto, el monto se
        // quedaba en No Cerrado para siempre aunque ya estuviera en la bóveda.
        $aperturaIds = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('estado', 'abierta')
            ->pluck('id')
            ->all();

        $bovedaDeSesion = $this->efectivoDisponibleService
            ->trasladosBovedaActivos($subCaja->id, $aperturaIds);

        $dineroSesion = $ingresosSesion - $egresosSesion - $bovedaDeSesion;

        return $saldoActual - max($dineroSesion, 0);
    }

    /**
     * Desglose del "Saldo No Cerrado" de UNA sub-caja: cuánto aporta cada usuario
     * con sesión abierta, en cada despliegue de pago.
     *
     * Usa las MISMAS transacciones y las mismas reglas de ingreso/egreso que
     * calcularSaldoMovible(), así que la suma del detalle cuadra con el monto de
     * la columna — salvo cuando el neto de la sesión es negativo y la columna lo
     * aplana a 0 (ver `total_aplanado` en la respuesta).
     */
    /**
     * Saldo CERRADO (movible) de una sub-caja: lo único que se puede mandar a otra
     * sub-caja. Es el mismo número que muestra la columna "Saldo Cerrado" y el
     * mismo que valida crearMovimiento(), expuesto para que las pantallas no
     * tengan que recalcularlo por su cuenta.
     */
    public function saldoMovibleSubCaja(int $subCajaId, ?string $desplieguePagoId = null): float
    {
        return round(
            max($this->calcularSaldoMovible(SubCaja::findOrFail($subCajaId), $desplieguePagoId), 0),
            2
        );
    }

    /**
     * Saldo REAL de una caja principal: todo el dinero que contiene, sumando el
     * saldo real de sus sub-cajas activas. Equivale al "Total General" del modal
     * (Saldo Cerrado + Saldo No Cerrado).
     *
     * Existe para que `CajaPrincipalResource` no repita la suma por su cuenta. La
     * tenía duplicada y quedó desincronizada: cuando el Traslado a Bóveda dejó de
     * restar del saldo real (ver calcularSaldoRealSubCaja), solo se actualizó el
     * cálculo del modal, así que la columna "Saldo Total" de la tabla seguía
     * descontando cada traslado y se separaba más del detalle con cada uno.
     */
    public function saldoRealCajaPrincipal(int $cajaPrincipalId): float
    {
        return round(
            SubCaja::where('caja_principal_id', $cajaPrincipalId)
                ->where('estado', true)
                ->get()
                ->sum(fn (SubCaja $subCaja) => $this->calcularSaldoRealSubCaja($subCaja)),
            2
        );
    }

    public function detalleNoCerrado(int $subCajaId): array
    {
        $subCaja = SubCaja::findOrFail($subCajaId);

        $transacciones = $this->transaccionesDeSesion($subCaja);

        if ($transacciones === null || $transacciones->isEmpty()) {
            return [
                'sub_caja_id' => $subCaja->id,
                'sub_caja_nombre' => $subCaja->nombre,
                'total' => 0.0,
                'total_aplanado' => false,
                'detalle' => [],
            ];
        }

        $idsTrasladoASesion = $this->efectivoDisponibleService->idsTrasladoASesion($transacciones);

        // Mismo criterio que calcularSaldoMovible: los movimientos internos no son
        // actividad de sesión (solo cambian el dinero de cajón), salvo el traslado
        // de efectivo a la sesión de un usuario, que sí lo es del lado del ingreso.
        $cuentaComoIngreso = fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
            || in_array($t->referencia_id, $idsTrasladoASesion, true);
        $cuentaComoEgreso = fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno';

        $nombresDespliegue = DespliegueDePago::whereIn(
            'id',
            $transacciones->pluck('despliegue_pago_id')->filter()->unique()
        )->pluck('name', 'id');

        $nombresUsuario = User::whereIn(
            'id',
            $transacciones->pluck('user_id')->filter()->unique()
        )->pluck('name', 'id');

        $filas = [];
        $total = 0.0;

        // Agrupado por despliegue de pago y, dentro de cada uno, por usuario: es el
        // orden en que se lee en pantalla ("cada caja tiene sus métodos, y en cada
        // método varios usuarios").
        foreach ($transacciones->groupBy(fn ($t) => $t->despliegue_pago_id ?? '') as $desplieguePagoId => $delDespliegue) {
            foreach ($delDespliegue->groupBy('user_id') as $userId => $delUsuario) {
                $ingresos = (float) $delUsuario->where('tipo_transaccion', 'ingreso')
                    ->filter($cuentaComoIngreso)->sum('monto');
                $egresos = (float) $delUsuario->where('tipo_transaccion', 'egreso')
                    ->filter($cuentaComoEgreso)->sum('monto');

                $monto = round($ingresos - $egresos, 2);

                if (abs($monto) < 0.005) {
                    continue;
                }

                $total += $monto;

                $filas[] = [
                    'despliegue_pago_id' => $desplieguePagoId ?: null,
                    'despliegue_nombre' => $nombresDespliegue[$desplieguePagoId] ?? 'Sin método',
                    'user_id' => $userId,
                    'user_nombre' => $nombresUsuario[$userId] ?? 'Sin usuario',
                    'ingresos' => round($ingresos, 2),
                    'egresos' => round($egresos, 2),
                    'monto' => $monto,
                ];
            }
        }

        // Orden estable: método alfabético, y dentro de él el mayor monto primero.
        usort($filas, fn ($a, $b) => [$a['despliegue_nombre'], -$a['monto']] <=> [$b['despliegue_nombre'], -$b['monto']]);

        return [
            'sub_caja_id' => $subCaja->id,
            'sub_caja_nombre' => $subCaja->nombre,
            'total' => round($total, 2),
            // La columna muestra max(total, 0). Si el neto es negativo, el detalle
            // suma menos que lo que se ve arriba; se avisa para no confundir.
            'total_aplanado' => $total < 0,
            'detalle' => $filas,
        ];
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
