<?php

namespace App\Services\Implementations;

use App\Models\TrasladoBoveda;
use App\Models\AperturaCierreCaja;
use App\Models\User;
use App\Models\SubCaja;
use App\Models\DespliegueDePago;
use App\Models\TransaccionCaja;
use App\Models\DistribucionEfectivoVendedor;
use App\Services\Cajas\EfectivoDisponibleService;
use App\Services\Interfaces\TrasladoBovedaServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class TrasladoBovedaService implements TrasladoBovedaServiceInterface
{
    public function __construct(
        private EfectivoDisponibleService $efectivoDisponibleService
    ) {}

    /**
     * Registrar un nuevo traslado a bóveda
     *
     * @param array $data
     * @return TrasladoBoveda
     * @throws Exception
     */
    public function registrarTraslado(array $data): TrasladoBoveda
    {
        DB::beginTransaction();
        try {
            $aperturaCierre = AperturaCierreCaja::find($data['apertura_cierre_caja_id']);

            if (!$aperturaCierre) {
                throw new Exception('No se encontró la apertura de caja.');
            }

            if (isset($aperturaCierre->estado) && $aperturaCierre->estado !== 'activa' && $aperturaCierre->estado !== 'abierta') {
                throw new Exception('La caja no está activa. No se pueden realizar traslados.');
            }

            // Validar que el supervisor sea válido (solo si se provee)
            if (!empty($data['supervisor_id'] ?? null)) {
                $supervisor = User::findOrFail($data['supervisor_id']);

                if (!$supervisor->es_supervisor) {
                    throw new Exception('El usuario no tiene permisos de supervisor.');
                }

                // Validar contraseña del supervisor
                if (!empty($data['supervisor_password']) && !Hash::check($data['supervisor_password'], $supervisor->password)) {
                    throw new Exception('Contraseña de supervisor incorrecta.');
                }
            }


            // Validar saldo disponible del vendedor para este despliegue
            $saldoDisponible = $this->calcularSaldoDisponible(
                (int) $data['sub_caja_id'],
                $data['vendedor_id'],
                $data['despliegue_pago_id']
            );

            if ((float) $data['monto'] > $saldoDisponible) {
                throw new Exception(
                    "El monto a trasladar (S/ " . number_format($data['monto'], 2) . ") " .
                    "excede el efectivo disponible (S/ " . number_format($saldoDisponible, 2) . ")."
                );
            }

            // Crear el traslado
            $createData = [
                'apertura_cierre_caja_id' => $data['apertura_cierre_caja_id'],
                'sub_caja_id' => $data['sub_caja_id'],
                'despliegue_pago_id' => $data['despliegue_pago_id'],
                'vendedor_id' => $data['vendedor_id'],
                'monto' => $data['monto'],
                'justificacion' => $data['justificacion'] ?? null,
                'fecha_traslado' => now(),
            ];

            if (!empty($data['supervisor_id'] ?? null)) {
                $createData['supervisor_id'] = $data['supervisor_id'];
            }

            $traslado = TrasladoBoveda::create($createData);

            // 2. NO se escribe nada en `transacciones_caja`.
            //
            // El traslado a bóveda es solo un REGISTRO: el efectivo se queda en la
            // Caja Chica, no se va a ningún lado. Antes se grababa un egreso acá y,
            // como TODOS los saldos del sistema se recalculan sumando ese libro,
            // cada calculador tenía que acordarse de excluir esas filas. Los que se
            // acordaban daban un saldo y los que no, otro: la misma sub-caja llegó a
            // mostrar 18,948.30 en "Saldo Cerrado" y 11,948.30 en "Mover Dinero
            // entre Sub-Cajas", diferencia exacta de los traslados registrados.
            //
            // Sin la fila, todos los calculadores coinciden solos y no hace falta
            // ningún filtro especial en ninguno.
            $subCaja = $traslado->subCaja;

            // 3. Registrar el movimiento de caja (para el ticket de cierre)
            \App\Models\MovimientoCaja::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'cajero_id' => $data['vendedor_id'],
                'apertura_cierre_id' => $data['apertura_cierre_caja_id'],
                'caja_principal_id' => $subCaja->caja_principal_id,
                'sub_caja_id' => $data['sub_caja_id'],
                'despliegue_pago_id' => $data['despliegue_pago_id'],
                'fecha_hora' => now(),
                'tipo_movimiento' => 'transferencia',
                'concepto' => "Traslado a bóveda: " . ($data['justificacion'] ?? ''),
                'saldo_inicial' => $subCaja->saldo_actual,
                'ingreso' => 0,
                'salida' => $data['monto'],
                'saldo_final' => $subCaja->saldo_actual, // No descontamos de la responsabilidad total
                'estado_caja' => 'abierta',
            ]);

            // 4. NO actualizamos el saldo actual de la sub-caja fìsicamente
            // $subCaja->saldo_actual -= $data['monto'];
            // $subCaja->save();

            DB::commit();
            return $traslado->load(['vendedor', 'supervisor', 'aperturaCierreCaja', 'subCaja']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener traslados de una apertura/cierre específica
     *
     * @param string $aperturaCierreId
     * @return Collection
     */
    /**
     * Obtener traslados activos de una caja (para cierre de caja, excludes anulados)
     */
    public function obtenerTrasladosPorCaja(string $aperturaCierreId): Collection
    {
        return TrasladoBoveda::where('apertura_cierre_caja_id', $aperturaCierreId)
            ->where('estado', 'activo')
            ->with(['vendedor', 'supervisor', 'subCaja', 'desplieguePago.metodoDePago'])
            ->orderBy('fecha_traslado', 'desc')
            ->get();
    }

    /**
     * Obtener todos los traslados (incluyendo anulados) - para historial
     */
    /**
     * Historial de traslados para un usuario, SIN depender de que tenga caja
     * abierta: se resuelve desde su última apertura (abierta o cerrada) y de ahí
     * se amplía a toda la caja principal.
     *
     * La pestaña "Traslado a Bóveda" pedía la apertura ACTIVA y, si no había,
     * ni siquiera llamaba al backend: el historial salía vacío como si no
     * existieran registros. Ver los movimientos ya hechos no requiere estar
     * operando.
     */
    public function obtenerHistorialDelUsuario(string|int $userId): Collection
    {
        $apertura = AperturaCierreCaja::where('user_id', $userId)
            ->orderByDesc('fecha_apertura')
            ->first();

        if (! $apertura) {
            return new Collection();
        }

        return $this->obtenerTodosLosTrasladosPorCaja($apertura->id);
    }

    public function obtenerTodosLosTrasladosPorCaja(string $aperturaCierreId): Collection
    {
        // "Caja General" es compartida: cada vendedor tiene su PROPIA apertura dentro
        // de la misma caja principal. Filtrar por el `apertura_cierre_caja_id` exacto
        // del usuario actual dejaba invisibles los traslados hechos por OTROS
        // vendedores en la misma caja principal. Hay que resolver la caja principal de
        // esta apertura y traer los traslados de TODAS las aperturas de esa caja.
        $apertura = AperturaCierreCaja::find($aperturaCierreId);
        if (! $apertura) {
            return new Collection();
        }

        return TrasladoBoveda::whereHas(
            'aperturaCierreCaja',
            fn ($q) => $q->where('caja_principal_id', $apertura->caja_principal_id)
        )
            ->with(['vendedor', 'supervisor', 'subCaja', 'desplieguePago.metodoDePago'])
            ->orderBy('fecha_traslado', 'desc')
            ->get();
    }

    /**
     * Obtener el total trasladado de una caja
     *
     * @param string $aperturaCierreId
     * @return float
     */
    public function obtenerTotalTrasladado(string $aperturaCierreId): float
    {
        return TrasladoBoveda::where('apertura_cierre_caja_id', $aperturaCierreId)
            ->where('estado', 'activo')
            ->sum('monto');
    }

    /**
     * Validar contraseña de supervisor
     *
     * @param string $supervisorId
     * @param string $password
     * @return bool
     */
    private function calcularSaldoDisponible(int $subCajaId, string $userId, string $desplieguePagoId): float
    {
        $subCaja = SubCaja::find($subCajaId);
        if (!$subCaja) return 0.0;

        $montoInicial = 0.0;

        // Resuelto UNA vez, fuera del if de Caja Chica: hace falta también para acotar
        // por fecha la consulta de transacciones más abajo, sin importar el tipo de
        // sub-caja. IMPORTANTE: filtrar por `user_id` — "Caja General" es compartida,
        // así que puede haber VARIAS aperturas abiertas simultáneas en la misma caja
        // principal (una por vendedor). Sin este filtro, `first()` podía devolver la
        // apertura de OTRO usuario, usando su fecha_apertura como límite y dejando el
        // saldo de este usuario completamente mal calculado.
        $aperturaActiva = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $userId)
            ->whereNull('fecha_cierre')
            ->first();

        // SIN sesión abierta no hay nada que trasladar: si el usuario ya cerró (o
        // nunca aperturó), su efectivo quedó consolidado en el cierre y no es dinero
        // suyo "en mano". Antes el filtro por fecha de más abajo era condicional
        // (`->when($aperturaActiva, ...)`), así que en ese caso la consulta salía SIN
        // acotar y sumaba el historial completo del usuario en la sub-caja: se podía
        // trasladar a bóveda contra meses de transacciones de sesiones ya cerradas.
        // La ruta de traslados no exige `caja.abierta`, así que era alcanzable.
        if (!$aperturaActiva) {
            return 0.0;
        }

        if ($subCaja->esCajaChica()) {
            $despliegue = DespliegueDePago::find($desplieguePagoId);
            if ($despliegue && $despliegue->metodoDePago) {
                $esEfectivo = (empty($despliegue->metodoDePago->cuenta_bancaria) ||
                              $despliegue->metodoDePago->cuenta_bancaria === 'SIN-CUENTA') &&
                             (stripos($despliegue->metodoDePago->name, 'efectivo') !== false);

                if ($esEfectivo) {
                    $montoInicial = (float) DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                        ->where('user_id', $userId)
                        ->sum('monto');
                }
            }
        }

        // IMPORTANTE: acotar SOLO a transacciones DESDE la apertura activa actual —
        // sin este filtro se sumaban TODAS las transacciones históricas del usuario en
        // esta sub-caja (de aperturas ya cerradas hace meses), dando saldos absurdos
        // (ej. negativos enormes) que no reflejan el efectivo real disponible AHORA.
        // Mismo criterio que SubCajaController::calcularEfectivoEnSubCaja().
        $transacciones = TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $userId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->where('created_at', '>=', $aperturaActiva->fecha_apertura)
            ->get();

        // De los `movimiento_interno` solo cuenta el TRASLADO DE EFECTIVO (con
        // `destino_user_id`): ese sí es efectivo nuevo entrando a la sesión del
        // vendedor. El MOVIMIENTO ENTRE CAJAS queda fuera de ambos lados — dinero ya
        // cerrado que solo cambia de cajón. Mismo criterio que el resto del sistema:
        // la validación de este traslado tiene que usar la misma lógica que el número
        // que se le mostró al usuario en el modal, para que nunca diverjan.
        $idsTrasladoASesion = $this->efectivoDisponibleService->idsTrasladoASesion($transacciones);

        $ingresos = (float) $transacciones
            ->where('tipo_transaccion', 'ingreso')
            ->filter(fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
                || in_array($t->referencia_id, $idsTrasladoASesion, true))
            ->sum('monto');

        $egresos = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        return $montoInicial + $ingresos - $egresos;
    }

    public function validarSupervisor(string $supervisorId, string $password): bool
    {
        try {
            $supervisor = User::findOrFail($supervisorId);

            if (!$supervisor->es_supervisor) {
                return false;
            }

            return Hash::check($password, $supervisor->password);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Anular un traslado
     *
     * @param string $trasladoId
     * @param string|null $supervisorId
     * @param string|null $password
     * @return bool
     * @throws Exception
     */
    public function anularTraslado(string $trasladoId, ?string $supervisorId = null, ?string $password = null): bool
    {
        DB::beginTransaction();
        try {
            // Validar supervisor solo si se proporciona
            if ($supervisorId && $password) {
                if (!$this->validarSupervisor($supervisorId, $password)) {
                    throw new Exception('Supervisor no válido o contraseña incorrecta.');
                }
            }

            $traslado = TrasladoBoveda::findOrFail($trasladoId);
            $monto = $traslado->monto;
            $subCaja = $traslado->subCaja;

            // Verificar que la caja aún esté activa
            $aperturaCierre = $traslado->aperturaCierreCaja;
            if ($aperturaCierre->estado !== 'abierta' && $aperturaCierre->estado !== 'activa') {
                throw new Exception('No se puede anular un traslado de una caja cerrada.');
            }

            // Verificar que el traslado no esté ya anulado
            if ($traslado->estado === 'anulado') {
                throw new Exception('Este traslado ya fue anulado anteriormente.');
            }

            // 1. Eliminar transacciones de caja asociadas
            \App\Models\TransaccionCaja::where('referencia_id', $trasladoId)
                ->where('referencia_tipo', 'traslado_boveda')
                ->delete();

            // 2. Eliminar movimientos de caja asociados
            \App\Models\MovimientoCaja::where('apertura_cierre_id', $aperturaCierre->id)
                ->where('sub_caja_id', $subCaja->id)
                ->where('salida', $monto)
                ->where('tipo_movimiento', 'egreso')
                ->where('concepto', 'LIKE', 'Traslado a bóveda%')
                ->delete();

            // 3. NO se toca `saldo_actual`.
            //
            // Antes acá se hacía `$subCaja->saldo_actual += $monto` para "restaurar"
            // el saldo — pero registrarTraslado() NUNCA lo restó, así que cada
            // anulación le SUMABA plata que nunca había salido. Ese era el origen del
            // desfase de la columna guardada (7,000 de más en la Caja Chica, igual al
            // total de traslados). Como el traslado es solo un registro, anularlo no
            // tiene que mover ningún saldo: basta con marcarlo.

            // 4. Marcar el traslado como anulado (no se elimina)
            $traslado->estado = 'anulado';
            $traslado->fecha_anulacion = now();
            $traslado->save();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
