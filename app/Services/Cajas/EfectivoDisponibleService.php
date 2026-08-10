<?php

namespace App\Services\Cajas;

use App\Models\AperturaCierreCaja;
use App\Models\DistribucionEfectivoVendedor;
use App\Models\MovimientoInterno;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use Illuminate\Support\Collection;

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
     * De un lote de transacciones, cuáles vienen de un TRASLADO DE EFECTIVO
     * (movimiento interno con `destino_user_id`): dinero CERRADO que se mandó a
     * la SESIÓN ABIERTA de un usuario. Se distingue del movimiento interno
     * normal (cajón → cajón) porque ahí el dinero sigue cerrado en ambas puntas,
     * mientras que acá pasa a ser dinero de sesión y deja de ser movible.
     *
     * Devuelve los IDs de movimiento (= `transacciones_caja.referencia_id`).
     */
    public function idsTrasladoASesion(Collection $transacciones): array
    {
        $referencias = $transacciones
            ->where('referencia_tipo', 'movimiento_interno')
            ->pluck('referencia_id')
            ->filter()
            ->unique()
            ->values();

        if ($referencias->isEmpty()) {
            return [];
        }

        return MovimientoInterno::whereIn('id', $referencias)
            ->whereNotNull('destino_user_id')
            ->pluck('id')
            ->all();
    }

    /**
     * Efectivo de un usuario en una sub-caja/método ACOTADO a una apertura:
     * distribución de esa apertura (si es Caja Chica) + ingresos − egresos de
     * ese método registrados DESDE la fecha de apertura. Así solo cuenta "lo
     * que tengo desde que aperturé".
     *
     * SIN apertura el resultado es 0, no el histórico. El filtro por fecha de más
     * abajo solo se aplica si hay apertura, así que devolver el cálculo con
     * `$apertura = null` significaba sumar TODAS las transacciones del usuario en
     * la sub-caja desde siempre — un "efectivo disponible" inflado con dinero de
     * sesiones ya cerradas. Este método es "lo que tengo desde que aperturé": si
     * no hay sesión abierta, la respuesta correcta es cero.
     */
    public function calcularDesdeApertura(SubCaja $subCaja, string|int $userId, string $desplieguePagoId, ?AperturaCierreCaja $apertura): float
    {
        if (!$apertura || !$apertura->fecha_apertura) {
            return 0.0;
        }

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

        // Solo desde que se aperturó (garantizado no-null por el guard de arriba)
        $query->where('created_at', '>=', $apertura->fecha_apertura);

        $transacciones = $query->get();

        // De los `movimiento_interno` solo cuenta el TRASLADO DE EFECTIVO (el que
        // lleva `destino_user_id`): ese dinero sale del pozo CERRADO de la sub-caja y
        // entra a la sesión abierta de un vendedor, así que le SUMA a quien lo recibe
        // y no le resta a quien lo ejecutó.
        //
        // El MOVIMIENTO ENTRE CAJAS (sin `destino_user_id`) queda fuera de AMBOS
        // lados: es dinero ya cerrado que solo cambia de cajón, nadie lo recibe en
        // mano. Antes el egreso solo se perdonaba si el ingreso volvía a la MISMA
        // sub-caja y al MISMO usuario, así que un movimiento 57 → 58 le restaba al
        // vendedor plata que nunca fue de su sesión y lo dejaba en negativo.
        $idsTrasladoASesion = $this->idsTrasladoASesion($transacciones);

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

        // movimiento_interno se excluye en AMBOS lados (ingreso y egreso): un
        // "Mover Dinero entre Sub-Cajas" no es actividad nueva de la sesión, es
        // dinero YA movible que solo cambió de sub-caja — debe seguir siendo
        // movible de inmediato en las dos puntas. Antes solo se excluía el
        // egreso, así que el ingreso del lado receptor se autocancelaba con el
        // aumento del saldo total y el movible del destino no subía al recibir
        // dinero (mismo criterio que MovimientoInternoService::calcularSaldoMovible).
        //
        // EXCEPCIÓN: el TRASLADO DE EFECTIVO (destino_user_id) manda el dinero a
        // la SESIÓN ABIERTA de un usuario, no a otro cajón — ese ingreso SÍ es
        // dinero de sesión y debe salir del movible hasta que se cierre caja.
        $idsTrasladoASesion = $this->idsTrasladoASesion($transaccionesSesion);

        $ingresosSesion = (float) $transaccionesSesion
            ->where('tipo_transaccion', 'ingreso')
            ->filter(fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
                || in_array($t->referencia_id, $idsTrasladoASesion, true))
            ->sum('monto');
        $egresosSesion = (float) $transaccionesSesion
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        $dineroSesion = $ingresosSesion - $egresosSesion;

        return $saldoTotal - max($dineroSesion, 0);
    }
}
