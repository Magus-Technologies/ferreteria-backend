<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill — convierte el modelo viejo de "entregas múltiples por grupo"
 * al modelo nuevo de "1 orden + N eventos":
 *
 *   1) Para cada `entregaproducto` SIN `grupo_entrega_id` (entrega independiente):
 *        - Si su estado es 'en' o 'ec' → crear 1 `entregaevento` con sus
 *          detalles convertidos a `detalleentregaevento`. La cantidad del
 *          evento sale del valor original de `cantidad_entregada` (ahora
 *          renombrada a `cantidad_solicitada`).
 *        - Si su estado es 'pe' (pendiente / placeholder de "Omitir") → no
 *          se crea evento; la entrega queda como orden lógica esperando
 *          que se registren eventos físicos vía la nueva API.
 *
 *   2) Para entregas con `grupo_entrega_id` apuntando a OTRA entrega
 *      (padre placeholder + hijas reales del flujo viejo "crear-entrega-resto"):
 *        - La PADRE (placeholder con cantidades reales en sus detalles) se
 *          mantiene como la orden lógica.
 *        - Cada HIJA se convierte en un `entregaevento` colgado de la padre,
 *          mapeando sus detalles a `detalleentregaevento` sobre los detalles
 *          de la padre por `unidad_derivada_venta_id`.
 *        - Las hijas se ELIMINAN al final (sus detalles caen en cascada,
 *          pero ya fueron migrados a eventos).
 *
 *   3) Para entregas con `grupo_entrega_id = id_propio` (orden multi-evento
 *      sin placeholder externo): se trata igual que el caso 1.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // ── PASO 1: identificar familias agrupadas por grupo_entrega_id ──
            $grupos = DB::table('entregaproducto')
                ->whereNotNull('grupo_entrega_id')
                ->select('grupo_entrega_id')
                ->distinct()
                ->pluck('grupo_entrega_id');

            $hijasIdsParaBorrar = [];

            foreach ($grupos as $grupoId) {
                $hermanas = DB::table('entregaproducto')
                    ->where('grupo_entrega_id', $grupoId)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                // PADRE = la que tiene id == grupo_entrega_id, o si no existe,
                // la más antigua.
                $padre = $hermanas->firstWhere('id', $grupoId) ?? $hermanas->first();
                if (! $padre) continue;

                $detallesPadre = DB::table('detalleentregaproducto')
                    ->where('entrega_producto_id', $padre->id)
                    ->get()
                    ->keyBy('unidad_derivada_venta_id');

                foreach ($hermanas as $hermana) {
                    // Solo migrar como evento las hermanas que YA tienen entrega
                    // física registrada (estado 'en' o 'ec'). Las pendientes
                    // duplicadas se descartan (la padre ya es la orden lógica).
                    if (! in_array($hermana->estado, ['en', 'ec'])) {
                        if ($hermana->id !== $padre->id) {
                            $hijasIdsParaBorrar[] = $hermana->id;
                        }
                        continue;
                    }

                    $estadoEvento = $hermana->estado === 'en' ? 'en' : 'ec';
                    $eventoId = DB::table('entregaevento')->insertGetId([
                        'entrega_producto_id' => $padre->id,
                        'estado' => $estadoEvento,
                        'fecha_programada' => $hermana->fecha_programada,
                        'fecha_ejecutada' => $hermana->estado === 'en'
                            ? ($hermana->updated_at ?? $hermana->fecha_entrega)
                            : null,
                        'hora_inicio' => $hermana->hora_inicio,
                        'hora_fin' => $hermana->hora_fin,
                        'chofer_id' => $hermana->chofer_id,
                        'vehiculo_id' => $hermana->vehiculo_id,
                        'quien_entrega' => $hermana->quien_entrega,
                        'tipo_pedido' => $hermana->tipo_pedido ?? 'interno',
                        'cargo_destino' => $hermana->cargo_destino,
                        'direccion_entrega' => $hermana->direccion_entrega,
                        'referencia_entrega' => $hermana->referencia_entrega,
                        'latitud' => $hermana->latitud,
                        'longitud' => $hermana->longitud,
                        'observaciones' => $hermana->observaciones,
                        'user_id' => $hermana->user_id,
                        'user_entregado_id' => $hermana->user_entregado_id,
                        'aceptado_at' => $hermana->aceptado_at,
                        'fecha_anulacion' => $hermana->fecha_anulacion,
                        'motivo_anulacion' => $hermana->motivo_anulacion,
                        'user_anulacion_id' => $hermana->user_anulacion_id,
                        'created_at' => $hermana->created_at,
                        'updated_at' => $hermana->updated_at,
                    ]);

                    $detallesHermana = DB::table('detalleentregaproducto')
                        ->where('entrega_producto_id', $hermana->id)
                        ->get();

                    foreach ($detallesHermana as $detHermana) {
                        $cantidad = (float) $detHermana->cantidad_solicitada;
                        if ($cantidad <= 0) continue;

                        // Buscar el detalle correspondiente en la PADRE por
                        // unidad_derivada_venta_id (la línea de producto
                        // solicitada). Si la padre no lo tiene, lo creamos.
                        $detPadre = $detallesPadre->get($detHermana->unidad_derivada_venta_id);
                        if (! $detPadre) {
                            $nuevoDetPadreId = DB::table('detalleentregaproducto')->insertGetId([
                                'entrega_producto_id' => $padre->id,
                                'unidad_derivada_venta_id' => $detHermana->unidad_derivada_venta_id,
                                'cantidad_solicitada' => $cantidad,
                                'ubicacion' => $detHermana->ubicacion,
                            ]);
                            $detPadre = (object) [
                                'id' => $nuevoDetPadreId,
                                'unidad_derivada_venta_id' => $detHermana->unidad_derivada_venta_id,
                                'cantidad_solicitada' => $cantidad,
                            ];
                            $detallesPadre->put($detHermana->unidad_derivada_venta_id, $detPadre);
                        }

                        DB::table('detalleentregaevento')->insert([
                            'entrega_evento_id' => $eventoId,
                            'detalle_entrega_producto_id' => $detPadre->id,
                            'cantidad' => $cantidad,
                            'ubicacion' => $detHermana->ubicacion,
                        ]);
                    }

                    if ($hermana->id !== $padre->id) {
                        $hijasIdsParaBorrar[] = $hermana->id;
                    }
                }

                // Recalcular estado de la padre en función de los eventos
                $this->recalcularEstadoEntrega($padre->id);
            }

            // ── PASO 2: entregas independientes (sin grupo_entrega_id) ──
            $independientes = DB::table('entregaproducto')
                ->whereNull('grupo_entrega_id')
                ->whereIn('estado_entrega', ['en', 'ec'])
                ->whereNotIn('id', $hijasIdsParaBorrar)
                ->get();

            foreach ($independientes as $entrega) {
                // Evitar duplicar si ya tiene eventos (re-ejecución segura)
                $yaTiene = DB::table('entregaevento')
                    ->where('entrega_producto_id', $entrega->id)
                    ->exists();
                if ($yaTiene) continue;

                $estadoEvento = $entrega->estado_entrega === 'en' ? 'en' : 'ec';
                $eventoId = DB::table('entregaevento')->insertGetId([
                    'entrega_producto_id' => $entrega->id,
                    'estado' => $estadoEvento,
                    'fecha_programada' => $entrega->fecha_programada,
                    'fecha_ejecutada' => $entrega->estado_entrega === 'en'
                        ? ($entrega->updated_at ?? $entrega->fecha_entrega)
                        : null,
                    'hora_inicio' => $entrega->hora_inicio,
                    'hora_fin' => $entrega->hora_fin,
                    'chofer_id' => $entrega->chofer_id,
                    'vehiculo_id' => $entrega->vehiculo_id,
                    'quien_entrega' => $entrega->quien_entrega,
                    'tipo_pedido' => $entrega->tipo_pedido ?? 'interno',
                    'cargo_destino' => $entrega->cargo_destino,
                    'direccion_entrega' => $entrega->direccion_entrega,
                    'referencia_entrega' => $entrega->referencia_entrega,
                    'latitud' => $entrega->latitud,
                    'longitud' => $entrega->longitud,
                    'observaciones' => $entrega->observaciones,
                    'user_id' => $entrega->user_id,
                    'user_entregado_id' => $entrega->user_entregado_id,
                    'aceptado_at' => $entrega->aceptado_at,
                    'fecha_anulacion' => $entrega->fecha_anulacion,
                    'motivo_anulacion' => $entrega->motivo_anulacion,
                    'user_anulacion_id' => $entrega->user_anulacion_id,
                    'created_at' => $entrega->created_at,
                    'updated_at' => $entrega->updated_at,
                ]);

                $detalles = DB::table('detalleentregaproducto')
                    ->where('entrega_producto_id', $entrega->id)
                    ->get();

                foreach ($detalles as $detalle) {
                    $cantidad = (float) $detalle->cantidad_solicitada;
                    if ($cantidad <= 0) continue;

                    DB::table('detalleentregaevento')->insert([
                        'entrega_evento_id' => $eventoId,
                        'detalle_entrega_producto_id' => $detalle->id,
                        'cantidad' => $cantidad,
                        'ubicacion' => $detalle->ubicacion,
                    ]);
                }
            }

            // ── PASO 3: borrar hijas migradas ──
            if (! empty($hijasIdsParaBorrar)) {
                DB::table('detalleentregaproducto')
                    ->whereIn('entrega_producto_id', $hijasIdsParaBorrar)
                    ->delete();
                DB::table('entregaproducto')
                    ->whereIn('id', $hijasIdsParaBorrar)
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Reverso destructivo — solo limpia los eventos (las hijas borradas
        // no se pueden recuperar). Esto es aceptable porque el backfill es
        // una migración de datos one-shot.
        DB::table('detalleentregaevento')->delete();
        DB::table('entregaevento')->delete();
    }

    /**
     * Recalcula `estado_entrega` de la orden lógica a partir de los eventos:
     *   - 'en' si todos los detalles tienen sus cantidades cubiertas por
     *     eventos en estado 'en'
     *   - 'ec' si hay algún evento en estado 'ec' (en camino)
     *   - 'pe' en cualquier otro caso
     */
    private function recalcularEstadoEntrega(int $entregaProductoId): void
    {
        $detalles = DB::table('detalleentregaproducto')
            ->where('entrega_producto_id', $entregaProductoId)
            ->get();

        $todoEntregado = true;
        $hayEnCamino = false;

        foreach ($detalles as $det) {
            $entregadoSum = (float) DB::table('detalleentregaevento as dee')
                ->join('entregaevento as ee', 'ee.id', '=', 'dee.entrega_evento_id')
                ->where('dee.detalle_entrega_producto_id', $det->id)
                ->where('ee.estado', 'en')
                ->sum('dee.cantidad');
            $enCaminoSum = (float) DB::table('detalleentregaevento as dee')
                ->join('entregaevento as ee', 'ee.id', '=', 'dee.entrega_evento_id')
                ->where('dee.detalle_entrega_producto_id', $det->id)
                ->where('ee.estado', 'ec')
                ->sum('dee.cantidad');

            if ($entregadoSum + 0.001 < (float) $det->cantidad_solicitada) {
                $todoEntregado = false;
            }
            if ($enCaminoSum > 0) {
                $hayEnCamino = true;
            }
        }

        $nuevoEstado = $todoEntregado ? 'en' : ($hayEnCamino ? 'ec' : 'pe');
        DB::table('entregaproducto')
            ->where('id', $entregaProductoId)
            ->update(['estado_entrega' => $nuevoEstado]);
    }
};
