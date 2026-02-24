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
    protected function registrarEnCajaActiva(string $referenciaId, string $referenciaTipo, string $tipoTransaccion, float $monto, ?string $desplieguePagoId, string $descripcion): void
    {
        // Obtener la caja abierta activa del usuario actual
        $apertura = AperturaCierreCaja::where('estado', 'abierta')
            ->where('user_id', Auth::id())
            ->first();

        // Si el usuario no tiene una caja abierta, registramos que no hubo impacto en una subcaja específica,
        // o podemos lanzar excepción si las reglas de negocio exigen siempre caja abierta.
        // Dado que es un gasto/ingreso "extra", asumiremos que SÍ requiere caja abierta si se declara "aprobado".
        if (!$apertura) {
            throw new \Exception('No hay una caja abierta para el usuario actual. No se puede procesar el flujo extra aprobado.');
        }

        $subCajaId = $apertura->sub_caja_id;

        // Si la apertura es en la caja principal, requiere que tenga un sub_caja_id asignada
        if (!$subCajaId) {
            // Caso donde tienen permiso general de todo. Tratamos de usar la primera subcaja ligada a la apertura o caja principal.
            $subCajaPrincipal = DB::table('sub_cajas')->where('caja_principal_id', $apertura->caja_principal_id)->first();
            if ($subCajaPrincipal) {
                $subCajaId = $subCajaPrincipal->id;
            } else {
                throw new \Exception('No se ubicó una sub-caja válida para procesar el flujo extra.');
            }
        }

        $subCaja = DB::table('sub_cajas')->where('id', $subCajaId)->first();

        // Calcular el nuevo saldo
        $saldoAnterior = $subCaja->saldo_actual;
        $saldoNuevo = $tipoTransaccion === 'ingreso'
            ? $saldoAnterior + $monto
            : $saldoAnterior - $monto;

        if ($tipoTransaccion === 'egreso' && $saldoNuevo < 0) {
            // throw new \Exception('Saldo insuficiente en la caja actual para registrar este egreso extra. Saldo actual: S/' . $saldoAnterior);
            // Comentado para permitir egresos en negativo temporalmente si las reglas lo permiten.
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
        // Buscar la transacción original
        $transaccionOriginal = TransaccionCaja::where('referencia_tipo', $referenciaTipoOriginal)
            ->where('referencia_id', $referenciaId)
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
