<?php

namespace App\Services\Implementations;

use App\Models\AbonoDeudaPersonal;
use App\Models\DeudaPersonal;
use App\Services\Interfaces\AbonoDeudaServiceInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class AbonoDeudaService implements AbonoDeudaServiceInterface
{
    /**
     * Registrar un abono a una deuda personal
     *
     * @param array $data
     * @return AbonoDeudaPersonal
     * @throws Exception
     */
    public function registrarAbono(array $data): AbonoDeudaPersonal
    {
        DB::beginTransaction();
        try {
            $deuda = DeudaPersonal::findOrFail($data['deuda_personal_id']);

            // Validaciones
            if ($deuda->esta_pagada) {
                throw new Exception('Esta deuda ya está completamente pagada');
            }

            if ($data['monto'] > $deuda->saldo_pendiente) {
                throw new Exception('El monto a abonar excede el saldo pendiente');
            }

            if ($data['monto'] <= 0) {
                throw new Exception('El monto debe ser mayor a 0');
            }

            // Crear abono
            $abono = AbonoDeudaPersonal::create([
                'deuda_personal_id' => $deuda->id,
                'monto' => $data['monto'],
                'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                'numero_operacion' => $data['numero_operacion'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'saldo_anterior' => $deuda->saldo_pendiente,
                'saldo_despues' => $deuda->saldo_pendiente - $data['monto'],
                'registrado_por_user_id' => auth()->id(),
                'fecha_abono' => now(),
            ]);

            // Actualizar deuda
            $deuda->monto_abonado += $data['monto'];
            $deuda->saldo_pendiente -= $data['monto'];

            // Actualizar estado
            if ($deuda->saldo_pendiente <= 0) {
                $deuda->estado = 'pagada';
            } elseif ($deuda->monto_abonado > 0) {
                $deuda->estado = 'parcialmente_pagada';
            }

            $deuda->save();

            DB::commit();
            return $abono->load(['metodoPago', 'registradoPor']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener historial de abonos de una deuda
     *
     * @param int $deudaId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerHistorialAbonos(int $deudaId)
    {
        return AbonoDeudaPersonal::where('deuda_personal_id', $deudaId)
            ->with(['metodoPago', 'registradoPor'])
            ->orderBy('fecha_abono', 'desc')
            ->get();
    }

    /**
     * Obtener resumen de deudas de un usuario
     *
     * @param int|string $userId
     * @return array
     */
    public function obtenerResumenDeudas(int|string $userId): array
    {
        $deudas = DeudaPersonal::where('user_id', $userId)
            ->with(['arqueoDiario.aperturaCierreCaja.cajaPrincipal', 'abonos', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'total_deudas' => $deudas->count(),
            'deudas_pendientes' => $deudas->where('estado', 'pendiente')->count(),
            'deudas_parciales' => $deudas->where('estado', 'parcialmente_pagada')->count(),
            'deudas_pagadas' => $deudas->where('estado', 'pagada')->count(),
            'monto_total_original' => $deudas->sum('monto_original'),
            'monto_total_abonado' => $deudas->sum('monto_abonado'),
            'saldo_total_pendiente' => $deudas->sum('saldo_pendiente'),
            'deudas' => $deudas,
        ];
    }

    /**
     * Actualizar un abono existente
     *
     * @param int $abonoId
     * @param array $data
     * @return AbonoDeudaPersonal
     * @throws Exception
     */
    public function actualizarAbono(int $abonoId, array $data): AbonoDeudaPersonal
    {
        DB::beginTransaction();
        try {
            $abono = AbonoDeudaPersonal::findOrFail($abonoId);
            $deuda = DeudaPersonal::findOrFail($abono->deuda_personal_id);

            // Si el monto cambia, ajustamos la deuda
            if (isset($data['monto']) && $data['monto'] != $abono->monto) {
                // Revertimos el abono anterior
                $deuda->monto_abonado -= $abono->monto;
                $deuda->saldo_pendiente += $abono->monto;

                // Validamos el nuevo monto
                if ($data['monto'] > $deuda->saldo_pendiente) {
                    throw new Exception('El nuevo monto a abonar excede el saldo pendiente');
                }

                if ($data['monto'] <= 0) {
                    throw new Exception('El monto debe ser mayor a 0');
                }

                // Aplicamos el nuevo monto
                $deuda->monto_abonado += $data['monto'];
                $deuda->saldo_pendiente -= $data['monto'];

                $abono->saldo_despues = $abono->saldo_anterior - $data['monto'];
                $abono->monto = $data['monto'];
            }

            if (isset($data['metodo_pago_id']) || array_key_exists('metodo_pago_id', $data)) {
                $abono->metodo_pago_id = $data['metodo_pago_id'];
            }

            if (isset($data['numero_operacion']) || array_key_exists('numero_operacion', $data)) {
                $abono->numero_operacion = $data['numero_operacion'];
            }

            if (isset($data['observaciones']) || array_key_exists('observaciones', $data)) {
                $abono->observaciones = $data['observaciones'];
            }

            $abono->save();

            // Actualizar estado de la deuda
            if ($deuda->saldo_pendiente <= 0) {
                $deuda->estado = 'pagada';
            } elseif ($deuda->monto_abonado > 0) {
                $deuda->estado = 'parcialmente_pagada';
            } else {
                $deuda->estado = 'pendiente';
            }

            $deuda->save();

            DB::commit();
            return $abono->load(['metodoPago', 'registradoPor']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Eliminar un abono
     *
     * @param int $abonoId
     * @return bool
     * @throws Exception
     */
    public function eliminarAbono(int $abonoId): bool
    {
        DB::beginTransaction();
        try {
            $abono = AbonoDeudaPersonal::findOrFail($abonoId);
            $deuda = DeudaPersonal::findOrFail($abono->deuda_personal_id);

            // Revertimos el abono
            $deuda->monto_abonado -= $abono->monto;
            $deuda->saldo_pendiente += $abono->monto;

            // Actualizar estado de la deuda
            if ($deuda->saldo_pendiente <= 0) {
                $deuda->estado = 'pagada';
            } elseif ($deuda->monto_abonado > 0) {
                $deuda->estado = 'parcialmente_pagada';
            } else {
                $deuda->estado = 'pendiente';
            }

            $deuda->save();
            $abono->delete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
