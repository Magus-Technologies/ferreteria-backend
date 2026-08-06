<?php

namespace App\Services\Cajas;

use App\Models\AperturaCierreCaja;
use App\Models\DistribucionEfectivoVendedor;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;

/**
 * Única fuente de verdad para "cuánto efectivo tiene un vendedor en una
 * sub-caja/método de pago, desde que aperturó". Antes había varias copias
 * casi idénticas de esta lógica (SubCajaController, MovimientoInternoService)
 * que fueron divergiendo — algunas sin el límite de fecha desde apertura,
 * otras sin excluir la auto-cancelación de "movimiento interno" — y por eso
 * el efectivo mostrado/validado no coincidía entre pantallas. Todas deben
 * llamar a este servicio en vez de recalcular por su cuenta.
 */
class EfectivoDisponibleService
{
    /**
     * Efectivo de un usuario en una sub-caja/método ACOTADO a una apertura:
     * distribución de esa apertura (si es Caja Chica) + ingresos − egresos de
     * ese método registrados DESDE la fecha de apertura. Así solo cuenta "lo
     * que tengo desde que aperturé".
     */
    public function calcularDesdeApertura(SubCaja $subCaja, string|int $userId, string $desplieguePagoId, ?AperturaCierreCaja $apertura): float
    {
        // Monto distribuido en ESTA apertura (solo Caja Chica)
        $montoInicial = 0.0;
        if ($apertura && $subCaja->esCajaChica()) {
            $montoInicial = (float) DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
                ->where('user_id', $userId)
                ->sum('monto');
        }

        // Transacciones del método, excluyendo las de tipo "apertura" (ya contadas arriba)
        $query = TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->where('user_id', $userId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where(function ($q) {
                $q->whereNull('referencia_tipo')
                    ->orWhere('referencia_tipo', '!=', 'apertura');
            });

        // Solo desde que se aperturó
        if ($apertura && $apertura->fecha_apertura) {
            $query->where('created_at', '>=', $apertura->fecha_apertura);
        }

        $transacciones = $query->get();

        // Ingresos: los de movimiento_interno (traslados de efectivo recibidos) SÍ cuentan
        // — es efectivo nuevo que entró a la sesión abierta.
        $ingresos = (float) $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');

        // Egresos: restan todos, incluidos los de 'movimiento_interno' cuando el
        // traslado SALIÓ del control del vendedor (a otro usuario u otra sub-caja).
        // Excepción: el traslado "cerrado → sesión" (mismo usuario lo recibió de
        // vuelta en la misma sub-caja) NO debe restar, porque el ingreso ya lo suma
        // y restarlo autocancelaría ese efectivo nuevo entrando a la sesión.
        $egresos = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->filter(function ($t) use ($transacciones) {
                if (($t->referencia_tipo ?? null) !== 'movimiento_interno') {
                    return true;
                }

                return !$transacciones->contains(function ($i) use ($t) {
                    return ($i->referencia_tipo ?? null) === 'movimiento_interno'
                        && $i->tipo_transaccion === 'ingreso'
                        && $i->referencia_id === $t->referencia_id
                        && $i->user_id === $t->user_id
                        && (int) $i->sub_caja_id === (int) $t->sub_caja_id;
                });
            })
            ->sum('monto');

        return $montoInicial + $ingresos - $egresos;
    }

    /**
     * "Saldo MOVIBLE" de una sub-caja (opcionalmente acotado a un método de
     * pago): el TOTAL desde siempre (dinero de sesiones ya cerradas + lo que
     * se aperturó hoy), SIN contar lo que entró/salió DURANTE la sesión
     * actual (ventas, gastos, etc. — eso recién se puede mover al cerrar
     * caja). Es SUB-CAJA-WIDE por diseño (Caja Chica es un cajón físico
     * compartido entre vendedores) — el total de TODOS los vendedores que
     * hayan usado esta sub-caja, no solo el vendedor logueado.
     *
     * Distinto de calcularDesdeApertura(): ese devuelve solo "lo que tengo
     * desde que aperturé" (session-scoped, para Traslado a Bóveda/Efectivo —
     * cuánto tengo físicamente en mano ahora, por vendedor). Este devuelve
     * "todo lo que se puede mover a otra sub-caja" (para "Mover Dinero entre
     * Sub-Cajas" — incluye lo acumulado en sesiones cerradas anteriores, de
     * cualquier vendedor).
     *
     * Ej: la sub-caja tiene 1000 cerrado (de cualquier vendedor), se aperturó
     * hoy con 500 → movible 1500. Si además se vende 200 hoy, esos 200 NO se
     * pueden mover todavía, pero el 1500 sí.
     */
    public function calcularMovibleDesdeApertura(SubCaja $subCaja, ?string $desplieguePagoId, ?AperturaCierreCaja $apertura): float
    {
        $totalQuery = TransaccionCaja::where('sub_caja_id', $subCaja->id);
        if ($desplieguePagoId) {
            $totalQuery->where('despliegue_pago_id', $desplieguePagoId);
        }
        $saldoTotal = (float) (clone $totalQuery)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_transaccion = 'ingreso' THEN monto ELSE -monto END), 0) as total")
            ->value('total');

        if (!$apertura || !$apertura->fecha_apertura) {
            return $saldoTotal;
        }

        // Transacciones de la sesión (desde que aperturó), EXCLUYENDO la propia
        // fila de "apertura" — ese monto ya es movible por definición, no es
        // dinero "nuevo" de la sesión.
        $sesionQuery = TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->where('created_at', '>=', $apertura->fecha_apertura)
            ->where(function ($q) {
                $q->whereNull('referencia_tipo')
                    ->orWhere('referencia_tipo', '!=', 'apertura');
            });
        if ($desplieguePagoId) {
            $sesionQuery->where('despliegue_pago_id', $desplieguePagoId);
        }
        $transaccionesSesion = $sesionQuery->get();

        $ingresosSesion = (float) $transaccionesSesion->where('tipo_transaccion', 'ingreso')->sum('monto');
        // Egresos de movimiento_interno se excluyen: ese dinero sale del
        // "cerrado" por regla, no de la sesión (mismo criterio que
        // MovimientoInternoService::calcularSaldoMovible).
        $egresosSesion = (float) $transaccionesSesion
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        $dineroSesion = $ingresosSesion - $egresosSesion;

        return $saldoTotal - max($dineroSesion, 0);
    }
}
