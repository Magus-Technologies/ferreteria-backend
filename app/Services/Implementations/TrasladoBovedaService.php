<?php

namespace App\Services\Implementations;

use App\Models\TrasladoBoveda;
use App\Models\AperturaCierreCaja;
use App\Models\User;
use App\Models\SubCaja;
use App\Models\DespliegueDePago;
use App\Models\TransaccionCaja;
use App\Models\DistribucionEfectivoVendedor;
use App\Services\Interfaces\TrasladoBovedaServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class TrasladoBovedaService implements TrasladoBovedaServiceInterface
{
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

            // 2. Registrar el egreso en la historia de transacciones (para el cálculo de saldos)
            // Se registra como egreso pero NO resta del saldo_actual persistido de la sub-caja
            // porque sigue siendo dinero bajo responsabilidad de la Caja Chica.
            $subCaja = $traslado->subCaja;
            $transaccion = \App\Models\TransaccionCaja::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'sub_caja_id' => $data['sub_caja_id'],
                'user_id' => $data['vendedor_id'],
                'despliegue_pago_id' => $data['despliegue_pago_id'],
                'tipo_transaccion' => 'egreso',
                'monto' => $data['monto'],
                'saldo_anterior' => $subCaja->saldo_actual,
                'saldo_nuevo' => $subCaja->saldo_actual, // No descontamos de la responsabilidad total
                'descripcion' => "Traslado a bóveda: " . ($data['justificacion'] ?? ''),
                'referencia_tipo' => 'traslado_boveda',
                'referencia_id' => $traslado->id,
                'fecha' => now(),
            ]);

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
    public function obtenerTodosLosTrasladosPorCaja(string $aperturaCierreId): Collection
    {
        return TrasladoBoveda::where('apertura_cierre_caja_id', $aperturaCierreId)
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

        if ($subCaja->esCajaChica()) {
            $despliegue = DespliegueDePago::find($desplieguePagoId);
            if ($despliegue && $despliegue->metodoDePago) {
                $esEfectivo = (empty($despliegue->metodoDePago->cuenta_bancaria) ||
                              $despliegue->metodoDePago->cuenta_bancaria === 'SIN-CUENTA') &&
                             (stripos($despliegue->metodoDePago->name, 'efectivo') !== false);

                if ($esEfectivo) {
                    $aperturaActiva = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
                        ->whereNull('fecha_cierre')
                        ->first();

                    if ($aperturaActiva) {
                        $montoInicial = (float) DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                            ->where('user_id', $userId)
                            ->sum('monto');
                    }
                }
            }
        }

        $transacciones = TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $userId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get();

        $ingresos = (float) $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos  = (float) $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

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

            // 3. Restaurar saldo de la sub-caja
            $subCaja->saldo_actual += $monto;
            $subCaja->save();

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
