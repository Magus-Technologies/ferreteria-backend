<?php

namespace App\Traits;

use App\Models\AperturaCierreCaja;
use App\Models\DistribucionEfectivoVendedor;
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

        // Validar contra el efectivo REAL de la sesión abierta del usuario: si tiene
        // apertura abierta en esta sub-caja, solo puede gastar el dinero de SU sesión.
        //
        // Esta validación va PRIMERO. Antes corría después de un chequeo contra
        // `sub_cajas.saldo_actual`, que es el saldo del cajón COMPLETO —compartido
        // entre vendedores y desalineado del libro—, así que el usuario recibía
        // "Disponible: S/230.00" (el saldo de la sub-caja) cuando su efectivo real de
        // sesión era 120. El mensaje reportaba un monto que no era suyo.
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

                // De los `movimiento_interno` solo cuenta el TRASLADO DE EFECTIVO (el
                // que lleva `destino_user_id`): ese dinero llega justamente para poder
                // gastar. El MOVIMIENTO ENTRE CAJAS queda fuera de AMBOS lados — es
                // dinero ya cerrado que solo cambia de cajón, el vendedor nunca lo tuvo.
                //
                // Antes el egreso solo se perdonaba si el ingreso volvía a la MISMA
                // sub-caja y al MISMO usuario, así que un movimiento entre cajas le
                // restaba al vendedor plata ajena y le bloqueaba registrar gastos.
                $idsTrasladoASesion = app(\App\Services\Cajas\EfectivoDisponibleService::class)
                    ->idsTrasladoASesion($transaccionesSesion);

                $ingresosSesion = (float) $transaccionesSesion
                    ->where('tipo_transaccion', 'ingreso')
                    ->filter(fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
                        || in_array($t->referencia_id, $idsTrasladoASesion, true))
                    ->sum('monto');

                $egresosSesion = (float) $transaccionesSesion
                    ->where('tipo_transaccion', 'egreso')
                    ->where('referencia_tipo', '!=', 'movimiento_interno')
                    ->sum('monto');

                // Base = lo que le tocó A ESTE VENDEDOR en el reparto, NO el
                // `monto_apertura` de la apertura. Una apertura se distribuye entre
                // varios vendedores, así que usar el total le daba a cada uno el
                // dinero de todos: con una apertura de 230 repartida, un vendedor con
                // 120 veía 230 disponibles.
                //
                // Si no hay fila de distribución, la apertura no se repartió y es
                // enteramente suya (ya viene filtrada por su `user_id`), así que ahí
                // sí corresponde el monto de apertura.
                $distribuido = (float) DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaAbierta->id)
                    ->where('user_id', Auth::id())
                    ->sum('monto');

                $montoInicial = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaAbierta->id)->exists()
                    ? $distribuido
                    : (float) $aperturaAbierta->monto_apertura;

                $disponibleSesion = $montoInicial + $ingresosSesion - $egresosSesion;

                if ($monto > $disponibleSesion + 0.001) {
                    throw new \Exception(
                        "Efectivo insuficiente en tu sesión de la sub-caja '{$subCaja->nombre}'. "
                        . 'Disponible desde tu apertura: S/' . number_format(max($disponibleSesion, 0), 2)
                        . ' y el gasto es de S/' . number_format($monto, 2)
                        . '. Solo puedes gastar el dinero de tu sesión abierta.'
                    );
                }
            } elseif ($saldoNuevo < 0) {
                // Sin sesión abierta del usuario el único límite posible es el saldo
                // del cajón. Queda como red de seguridad para no dejarlo en negativo.
                throw new \Exception(
                    "Saldo insuficiente en la sub-caja '{$subCaja->nombre}'. Disponible: S/"
                    . number_format($saldoAnterior, 2)
                );
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
