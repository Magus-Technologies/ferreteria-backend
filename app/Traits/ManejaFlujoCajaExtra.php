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

        // Validar contra el efectivo REAL de la sesión abierta del usuario (desde su
        // apertura): si tiene una apertura abierta en esta sub-caja, solo puede gastar
        // el dinero de SU sesión (monto_apertura + sus ingresos − sus egresos desde que
        // se abrió). El saldo_actual de la sub-caja puede estar inflado con dinero de
        // sesiones anteriores o de otros vendedores, y permitiría gastar lo que en
        // realidad no hay en caja (ej. apertura de 150 y gasto de 2500).
        // Sin apertura abierta del usuario, el límite es el saldo_actual.
        if ($tipoTransaccion === 'egreso') {
            $aperturaAbierta = AperturaCierreCaja::where('sub_caja_id', $subCajaId)
                ->where('user_id', Auth::id())
                ->where('estado', 'abierta')
                ->orderByDesc('fecha_apertura')
                ->first();

            if ($aperturaAbierta) {
                $transaccionesSesion = TransaccionCaja::where('sub_caja_id', $subCajaId)
                    ->where('user_id', Auth::id())
                    ->where('fecha', '>=', $aperturaAbierta->fecha_apertura)
                    ->get();

                // Ingresos de la sesión: cuentan TODOS (ventas, ingresos extras y
                // también los traslados/movimientos internos RECIBIDOS — ese dinero
                // llega justamente para poder gastar).
                $ingresosSesion = (float) $transaccionesSesion
                    ->where('tipo_transaccion', 'ingreso')
                    ->sum('monto');

                // Egresos de la sesión: restan todos, incluidos los movimientos internos
                // cuando el traslado SALIÓ del control del vendedor (a otro usuario u otra
                // sub-caja). Excepción: el traslado "cerrado → sesión" (mismo usuario lo
                // recibió de vuelta en la misma sub-caja) NO debe restar, porque el ingreso
                // ya lo suma y restarlo autocancelaría ese efectivo nuevo.
                $egresosSesion = (float) $transaccionesSesion
                    ->where('tipo_transaccion', 'egreso')
                    ->filter(function ($t) use ($transaccionesSesion) {
                        if (($t->referencia_tipo ?? null) !== 'movimiento_interno') {
                            return true;
                        }

                        return !$transaccionesSesion->contains(function ($i) use ($t) {
                            return ($i->referencia_tipo ?? null) === 'movimiento_interno'
                                && $i->tipo_transaccion === 'ingreso'
                                && $i->referencia_id === $t->referencia_id
                                && $i->user_id === $t->user_id
                                && (int) $i->sub_caja_id === (int) $t->sub_caja_id;
                        });
                    })
                    ->sum('monto');

                $disponibleSesion = (float) $aperturaAbierta->monto_apertura + $ingresosSesion - $egresosSesion;

                if ($monto > $disponibleSesion + 0.001) {
                    throw new \Exception(
                        "Efectivo insuficiente en tu sesión de la sub-caja '{$subCaja->nombre}'. "
                        . 'Disponible desde tu apertura: S/' . number_format(max($disponibleSesion, 0), 2)
                        . ' y el gasto es de S/' . number_format($monto, 2)
                        . '. Solo puedes gastar el dinero de tu sesión abierta.'
                    );
                }
            }
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
