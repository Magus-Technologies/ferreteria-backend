<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Models\EntregaProducto;
use App\Models\UnidadDerivadaInmutableVenta;
use Illuminate\Support\Facades\DB;

/**
 * Puente entre el sistema NUEVO de entregas (`entrega`) y el VIEJO
 * (`entregaproducto`).
 *
 * mis-entregas opera sobre la tabla NUEVA, pero la columna "Entrega" de
 * mis-ventas lee la tabla VIEJA (vía `Venta::entregasProductos`) y
 * `unidadderivadainmutableventa.cantidad_pendiente`. La creación ya se
 * espeja (`EntregaService::crearSync` con `entrega_legacy_id`), pero las
 * TRANSICIONES de estado (confirmar / en-camino / anular) solo tocaban la
 * tabla nueva. Resultado: al entregar en mis-entregas, el legacy quedaba en
 * `pe` y la columna de mis-ventas nunca pasaba a "Completa".
 *
 * Este service se invoca tras cada transición del sistema nuevo y deja
 * ambas fuentes consistentes.
 */
class EntregaSyncLegacyService
{
    public function sincronizar(Entrega $entrega): void
    {
        DB::transaction(function () use ($entrega) {
            $entrega->loadMissing('estadoEntrega', 'detalles');
            $estadoCodigo = $entrega->estadoEntrega?->codigo?->value;

            // 1) Espejar el estado al registro legacy vinculado.
            if ($entrega->entrega_legacy_id && $estadoCodigo) {
                EntregaProducto::where('id', $entrega->entrega_legacy_id)
                    ->update(['estado_entrega' => $estadoCodigo]);
            }

            // 2) Recalcular cantidad_pendiente de cada UDV tocada por esta
            //    entrega, de forma ABSOLUTA desde la tabla nueva (idempotente):
            //    pendiente = cantidad_total − cubierto, donde cubierto es la
            //    suma de los detalles de todas las entregas NO canceladas de la
            //    misma venta que tocan esa UDV. "No cancelada" (pe/ec/en) es
            //    coherente con la regla "el stock/cobertura se compromete al
            //    PROGRAMAR la entrega, no al confirmarla".
            $udvIds = $entrega->detalles->pluck('unidad_derivada_venta_id')->unique();

            foreach ($udvIds as $udvId) {
                $udv = UnidadDerivadaInmutableVenta::find($udvId);
                if (! $udv) {
                    continue;
                }

                $cubierto = (float) DB::table('entrega_detalle as d')
                    ->join('entrega as e', 'e.id', '=', 'd.entrega_id')
                    ->join('estado_entrega as est', 'est.id', '=', 'e.estado_entrega_id')
                    ->where('e.venta_id', $entrega->venta_id)
                    ->where('d.unidad_derivada_venta_id', $udvId)
                    ->where('est.codigo', '!=', 'ca')
                    ->sum('d.cantidad');

                $pendiente = max(0.0, (float) $udv->cantidad - $cubierto);
                $udv->update(['cantidad_pendiente' => $pendiente]);
            }
        });
    }
}
