<?php

namespace App\Traits;

use App\Models\AperturaCierreCaja;
use App\Models\TransaccionCaja;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

trait ManejaFlujoCajaExtra
{
    /**
     * Registra un ingreso o egreso extra en la caja activa del usuario.
     */
    protected function registrarEnCajaActiva(string $referenciaId, string $referenciaTipo, string $tipoTransaccion, float $monto, ?string $desplieguePagoId, string $descripcion, ?string $subCajaIdParam = null): void
    {
        $subCajaId = $subCajaIdParam;

        if (!$subCajaId) {
            // Obtener la caja abierta activa del usuario actual
            // Puede ser el dueño de la apertura o un vendedor con distribución asignada
            $apertura = AperturaCierreCaja::where('estado', 'abierta')
                ->where(function ($query) {
                    $query->where('user_id', Auth::id())
                        ->orWhereHas('distribucionesVendedores', function ($q) {
                            $q->where('user_id', Auth::id());
                        });
                })
                ->first();

            // Dado que es un gasto/ingreso "extra", exigimos caja abierta para procesar el flujo.
            if (!$apertura) {
                throw new \Exception('No tiene una caja abierta para procesar este movimiento contable.');
            }

            // Si se indicó un despliegue de pago, usar la sub-caja que lo tenga habilitado
            // (ej. "efectivo negro" pertenece a la sub-caja "Caja Negra", no a la Caja
            // Chica de la apertura). Antes siempre se guardaba en la sub-caja de la
            // apertura sin importar el método elegido, así que el dinero quedaba en la
            // sub-caja equivocada y desaparecía del cálculo de efectivo de la sub-caja
            // real (ej. Traslado a Bóveda nunca lo veía).
            if ($desplieguePagoId) {
                $subCajaResuelta = app(\App\Repositories\Interfaces\SubCajaRepositoryInterface::class)
                    ->buscarSubCajaParaDespliegue($apertura->caja_principal_id, $desplieguePagoId);
                if ($subCajaResuelta) {
                    $subCajaId = $subCajaResuelta->id;
                }
            }

            if (!$subCajaId) {
                $subCajaId = $apertura->sub_caja_id;
            }

            // Si la apertura es en la caja principal, requiere que tenga un sub_caja_id asignada
            if (!$subCajaId) {
                $subCajaPrincipal = DB::table('sub_cajas')->where('caja_principal_id', $apertura->caja_principal_id)->first();
                if ($subCajaPrincipal) {
                    $subCajaId = $subCajaPrincipal->id;
                } else {
                    throw new \Exception('No se ubicó una sub-caja válida para procesar el flujo extra.');
                }
            }
        }

        $subCaja = DB::table('sub_cajas')->where('id', $subCajaId)->first();
        if (!$subCaja) {
            throw new \Exception('La sub-caja seleccionada no es válida.');
        }

        // Calcular el nuevo saldo
        $saldoAnterior = (float) $subCaja->saldo_actual;
        $saldoNuevo = $tipoTransaccion === 'ingreso'
            ? $saldoAnterior + $monto
            : $saldoAnterior - $monto;

        // Validar saldo insuficiente para gastos
        if ($tipoTransaccion === 'egreso' && $saldoNuevo < 0) {
            throw new \Exception("Saldo insuficiente en la sub-caja '{$subCaja->nombre}'. Disponible: S/" . number_format($saldoAnterior, 2));
        }

        // Crear la transacción
        TransaccionCaja::create([
            'sub_caja_id' => $subCaja->id,
            'user_id' => Auth::id(),
            'tipo_transaccion' => $tipoTransaccion, // 'ingreso' o 'egreso'
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'descripcion' => $descripcion,
            'despliegue_pago_id' => $desplieguePagoId,
            'referencia_tipo' => $referenciaTipo, // 'gasto_extra' o 'ingreso_extra'
            'referencia_id' => $referenciaId,
            'fecha' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualizar el saldo de la sub-caja
        DB::table('sub_cajas')
            ->where('id', $subCaja->id)
            ->update([
                'saldo_actual' => $saldoNuevo,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reversa (anula) una transacción en caja previamente registrada.
     */
    protected function reversarEnCajaActiva(string $referenciaId, string $referenciaTipoOriginal, string $motivoBase): void
    {
        // Buscar la transacción original vigente. Si el ingreso/gasto ya fue editado
        // antes, puede haber más de una fila con este referencia_tipo+referencia_id (una
        // por cada edición) — se toma la más reciente para no reversar una ya reemplazada.
        $transaccionOriginal = TransaccionCaja::where('referencia_tipo', $referenciaTipoOriginal)
            ->where('referencia_id', $referenciaId)
            ->orderByDesc('created_at')
            ->first();

        if ($transaccionOriginal) {
            $subCaja = DB::table('sub_cajas')->where('id', $transaccionOriginal->sub_caja_id)->first();

            if ($subCaja) {
                $tipoReverso = $transaccionOriginal->tipo_transaccion === 'egreso' ? 'ingreso' : 'egreso';

                $saldoAnterior = $subCaja->saldo_actual;
                $saldoNuevo = $tipoReverso === 'ingreso'
                    ? $saldoAnterior + $transaccionOriginal->monto
                    : $saldoAnterior - $transaccionOriginal->monto;

                // Registrar transacción de reversión
                TransaccionCaja::create([
                    'sub_caja_id' => $subCaja->id,
                    'user_id' => Auth::id(),
                    'tipo_transaccion' => $tipoReverso,
                    'monto' => $transaccionOriginal->monto,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_nuevo' => $saldoNuevo,
                    'despliegue_pago_id' => $transaccionOriginal->despliegue_pago_id,
                    'descripcion' => 'Anulación de ' . str_replace('_', ' ', $referenciaTipoOriginal) . ': ' . $motivoBase,
                    'referencia_tipo' => 'anulacion_' . $referenciaTipoOriginal,
                    'referencia_id' => $referenciaId,
                    'fecha' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Actualizar saldo de sub-caja
                DB::table('sub_cajas')
                    ->where('id', $subCaja->id)
                    ->update([
                        'saldo_actual' => $saldoNuevo,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
