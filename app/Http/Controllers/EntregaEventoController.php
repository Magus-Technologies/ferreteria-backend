<?php

namespace App\Http\Controllers;

use App\Models\DetalleEntregaEvento;
use App\Models\DetalleEntregaProducto;
use App\Models\EntregaEvento;
use App\Models\EntregaProducto;
use App\Models\User;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\Venta;
use App\Services\FirebaseNotificationService;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EntregaEventoController — CRUD de despachos físicos parciales sobre una
 * orden lógica de entrega (`EntregaProducto`).
 *
 * Endpoints:
 *   GET    /entregas-productos/{entregaId}/eventos
 *   POST   /entregas-productos/{entregaId}/eventos
 *   PUT    /entregas-productos/{entregaId}/eventos/{eventoId}
 *   POST   /entregas-productos/{entregaId}/eventos/{eventoId}/anular
 *   DELETE /entregas-productos/{entregaId}/eventos/{eventoId}
 *
 * Las cantidades enviadas en cada evento se validan contra el pendiente de
 * cada detalle solicitado (cantidad_solicitada − entregado − en_camino). Al
 * crear/modificar un evento, se actualiza el `estado_entrega` de la orden
 * lógica y el stock real cuando corresponde.
 */
class EntregaEventoController extends Controller
{
    public function __construct(private FirebaseNotificationService $firebaseService) {}

    public function index(string $entregaId)
    {
        $eventos = EntregaEvento::where('entrega_producto_id', $entregaId)
            ->with([
                'despachador:id,name,telefono,celular,email',
                'vehiculo:id,name,tipo,placa',
                'user:id,name',
                'userEntregado:id,name',
                'userAnulacion:id,name',
                'detalles.detalleEntregaProducto.unidadDerivadaVenta.unidadDerivadaInmutable',
                'detalles.detalleEntregaProducto.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $eventos]);
    }

    public function store(Request $request, string $entregaId)
    {
        $validated = $request->validate([
            'estado' => 'required|string|in:pr,ec,en',
            'fecha_programada' => 'nullable|date',
            'fecha_ejecutada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'chofer_id' => 'nullable|string',
            'vehiculo_id' => 'nullable|integer|exists:vehiculo,id',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'tipo_pedido' => 'sometimes|string|in:interno,externo',
            'cargo_destino' => 'required_if:tipo_pedido,externo|nullable|string',
            'direccion_entrega' => 'nullable|string',
            'referencia_entrega' => 'nullable|string',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'observaciones' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.detalle_entrega_producto_id' => 'required|integer|exists:detalleentregaproducto,id',
            'detalles.*.cantidad' => 'required|numeric|min:0',
            'detalles.*.ubicacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($entregaId, $validated, $request) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($entregaId);
            $userId = $request->user()?->id ?? $entrega->user_id;

            // Validar que cada detalle pertenezca a esta entrega y que la
            // cantidad solicitada no supere el pendiente.
            $detallesIndex = $entrega->productosEntregados->keyBy('id');

            foreach ($validated['detalles'] as $det) {
                $detallePadre = $detallesIndex->get($det['detalle_entrega_producto_id']);
                if (! $detallePadre) {
                    throw ValidationException::withMessages([
                        'detalles' => ["El detalle {$det['detalle_entrega_producto_id']} no pertenece a esta entrega"],
                    ]);
                }
                $pendiente = (float) $detallePadre->cantidad_pendiente_detalle;
                $cantidad = (float) $det['cantidad'];

                // Fallback defensivo: si el pendiente calculado es 0 pero la UDV
                // aún tiene stock pendiente, usar ese valor. Cubre registros donde
                // cantidad_solicitada fue corrompida por el flujo pre-evento.
                if ($pendiente <= 0.001) {
                    $udv = UnidadDerivadaInmutableVenta::find($detallePadre->unidad_derivada_venta_id);
                    if ($udv && (float) $udv->cantidad_pendiente > 0.001) {
                        $pendiente = (float) $udv->cantidad_pendiente;
                    }
                }

                if ($cantidad > $pendiente + 0.001) {
                    throw ValidationException::withMessages([
                        'detalles' => [
                            "Cantidad {$cantidad} supera el pendiente ({$pendiente}) del detalle {$det['detalle_entrega_producto_id']}",
                        ],
                    ]);
                }
            }

            $evento = EntregaEvento::create([
                'entrega_producto_id' => $entrega->id,
                'estado' => $validated['estado'],
                'fecha_programada' => $validated['fecha_programada'] ?? null,
                'fecha_ejecutada' => $validated['estado'] === 'en'
                    ? ($validated['fecha_ejecutada'] ?? now())
                    : ($validated['fecha_ejecutada'] ?? null),
                'hora_inicio' => $validated['hora_inicio'] ?? null,
                'hora_fin' => $validated['hora_fin'] ?? null,
                'chofer_id' => $validated['chofer_id'] ?? null,
                'vehiculo_id' => $validated['vehiculo_id'] ?? null,
                'quien_entrega' => $validated['quien_entrega'] ?? null,
                'tipo_pedido' => $validated['tipo_pedido'] ?? 'interno',
                'cargo_destino' => $validated['cargo_destino'] ?? null,
                'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                'referencia_entrega' => $validated['referencia_entrega'] ?? null,
                'latitud' => $validated['latitud'] ?? null,
                'longitud' => $validated['longitud'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'user_id' => $userId,
                'user_entregado_id' => $validated['estado'] === 'en' ? $userId : null,
            ]);

            // Crear detalles del evento + aplicar stock cuando estado='en'
            $venta = Venta::find($entrega->venta_id);

            foreach ($validated['detalles'] as $det) {
                if ((float) $det['cantidad'] <= 0) continue;

                DetalleEntregaEvento::create([
                    'entrega_evento_id' => $evento->id,
                    'detalle_entrega_producto_id' => $det['detalle_entrega_producto_id'],
                    'cantidad' => $det['cantidad'],
                    'ubicacion' => $det['ubicacion'] ?? null,
                ]);

                if ($validated['estado'] === 'en') {
                    $this->aplicarStock(
                        $detallesIndex->get($det['detalle_entrega_producto_id']),
                        (float) $det['cantidad'],
                        $entrega,
                        $venta,
                    );
                }
            }

            $entrega->recalcularEstado();

            // Notificación FCM al despachador asignado
            if ($evento->chofer_id) {
                $despachador = User::find($evento->chofer_id);
                if ($despachador && $despachador->fcm_token) {
                    $entrega->load('venta:id,serie,numero');
                    $this->firebaseService->sendNotification(
                        $despachador->fcm_token,
                        'Nueva Entrega Programada',
                        "Venta {$entrega->venta->serie}-{$entrega->venta->numero}",
                        [
                            'type' => 'pedido_entrega',
                            'entrega_id' => (string) $entrega->id,
                            'evento_id' => (string) $evento->id,
                        ],
                    );
                }
            }

            return response()->json([
                'data' => $evento->fresh([
                    'despachador:id,name',
                    'vehiculo:id,name,tipo,placa',
                    'user:id,name',
                    'detalles.detalleEntregaProducto.unidadDerivadaVenta.unidadDerivadaInmutable',
                ]),
                'message' => 'Evento de entrega creado',
            ], 201);
        });
    }

    public function update(Request $request, string $entregaId, string $eventoId)
    {
        $validated = $request->validate([
            'estado' => 'sometimes|string|in:pr,ec,en',
            'fecha_programada' => 'nullable|date',
            'fecha_ejecutada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'chofer_id' => 'nullable|string',
            'vehiculo_id' => 'nullable|integer|exists:vehiculo,id',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'tipo_pedido' => 'sometimes|string|in:interno,externo',
            'cargo_destino' => 'nullable|string',
            'direccion_entrega' => 'nullable|string',
            'referencia_entrega' => 'nullable|string',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'observaciones' => 'nullable|string',
            'detalles' => 'sometimes|array',
            'detalles.*.detalle_entrega_producto_id' => 'required_with:detalles|integer',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0',
        ]);

        return DB::transaction(function () use ($entregaId, $eventoId, $validated, $request) {
            $evento = EntregaEvento::where('entrega_producto_id', $entregaId)
                ->with('detalles')
                ->findOrFail($eventoId);
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($entregaId);
            $venta = Venta::find($entrega->venta_id);
            $estadoAnterior = $evento->estado;

            // Si cambian las cantidades, reemplazar detalles del evento.
            if (array_key_exists('detalles', $validated)) {
                $detallesIndex = $entrega->productosEntregados->keyBy('id');

                // Revertir stock del evento si estaba 'en' (vamos a re-aplicar)
                if ($estadoAnterior === 'en') {
                    foreach ($evento->detalles as $detPrevio) {
                        $detallePadre = $detallesIndex->get($detPrevio->detalle_entrega_producto_id);
                        if ($detallePadre) {
                            $this->revertirStock($detallePadre, (float) $detPrevio->cantidad, $entrega, $venta);
                        }
                    }
                }

                DetalleEntregaEvento::where('entrega_evento_id', $evento->id)->delete();

                // Validar pendientes con el estado actualizado (recargar entrega)
                $entrega->load('productosEntregados.eventosDetalle.entregaEvento');
                $detallesIndex = $entrega->productosEntregados->keyBy('id');

                foreach ($validated['detalles'] as $det) {
                    $detallePadre = $detallesIndex->get($det['detalle_entrega_producto_id']);
                    if (! $detallePadre) {
                        throw ValidationException::withMessages([
                            'detalles' => ["Detalle inválido para esta entrega"],
                        ]);
                    }
                    $cantidad = (float) $det['cantidad'];
                    if ($cantidad <= 0) continue;

                    $pendiente = (float) $detallePadre->cantidad_pendiente_detalle;
                    if ($cantidad > $pendiente + 0.001) {
                        throw ValidationException::withMessages([
                            'detalles' => [
                                "Cantidad {$cantidad} supera el pendiente ({$pendiente})",
                            ],
                        ]);
                    }

                    DetalleEntregaEvento::create([
                        'entrega_evento_id' => $evento->id,
                        'detalle_entrega_producto_id' => $det['detalle_entrega_producto_id'],
                        'cantidad' => $cantidad,
                    ]);
                }
            }

            // Actualizar campos del evento
            $userId = $request->user()?->id ?? $evento->user_id;
            $nuevoEstado = $validated['estado'] ?? $evento->estado;

            $evento->fill(array_filter([
                'estado' => $validated['estado'] ?? null,
                'fecha_programada' => $validated['fecha_programada'] ?? null,
                'hora_inicio' => $validated['hora_inicio'] ?? null,
                'hora_fin' => $validated['hora_fin'] ?? null,
                'chofer_id' => $validated['chofer_id'] ?? null,
                'vehiculo_id' => $validated['vehiculo_id'] ?? null,
                'quien_entrega' => $validated['quien_entrega'] ?? null,
                'tipo_pedido' => $validated['tipo_pedido'] ?? null,
                'cargo_destino' => $validated['cargo_destino'] ?? null,
                'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                'referencia_entrega' => $validated['referencia_entrega'] ?? null,
                'latitud' => $validated['latitud'] ?? null,
                'longitud' => $validated['longitud'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
            ], fn ($v) => $v !== null));

            // Transición a 'en': aplicar stock + registrar user_entregado
            if ($nuevoEstado === 'en' && $estadoAnterior !== 'en') {
                $evento->user_entregado_id = $userId;
                $evento->fecha_ejecutada = $validated['fecha_ejecutada'] ?? now();
                $evento->fecha_anulacion = null;
                $evento->motivo_anulacion = null;
                $evento->user_anulacion_id = null;
                $evento->save();

                $evento->load('detalles');
                $detallesIndex = $entrega->productosEntregados->keyBy('id');
                foreach ($evento->detalles as $detEvento) {
                    $detallePadre = $detallesIndex->get($detEvento->detalle_entrega_producto_id);
                    if ($detallePadre) {
                        $this->aplicarStock($detallePadre, (float) $detEvento->cantidad, $entrega, $venta);
                    }
                }
            } else {
                $evento->save();
            }

            $entrega->recalcularEstado();

            return response()->json([
                'data' => $evento->fresh([
                    'despachador:id,name',
                    'vehiculo:id,name,tipo,placa',
                    'user:id,name',
                    'detalles.detalleEntregaProducto.unidadDerivadaVenta.unidadDerivadaInmutable',
                ]),
                'message' => 'Evento actualizado',
            ]);
        });
    }

    public function anular(Request $request, string $entregaId, string $eventoId)
    {
        $validated = $request->validate([
            'motivo' => 'required|string|min:5|max:500',
        ]);

        return DB::transaction(function () use ($entregaId, $eventoId, $validated, $request) {
            $evento = EntregaEvento::where('entrega_producto_id', $entregaId)
                ->with('detalles')
                ->findOrFail($eventoId);
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($entregaId);

            if (! in_array($evento->estado, ['en', 'ec'])) {
                return response()->json([
                    'message' => 'Solo se pueden anular eventos En Camino o Entregados',
                    'error' => 'ESTADO_NO_ANULABLE',
                ], 422);
            }

            // Revertir stock si estaba 'en'
            if ($evento->estado === 'en') {
                $venta = Venta::find($entrega->venta_id);
                $detallesIndex = $entrega->productosEntregados->keyBy('id');
                foreach ($evento->detalles as $detEvento) {
                    $detallePadre = $detallesIndex->get($detEvento->detalle_entrega_producto_id);
                    if ($detallePadre) {
                        $this->revertirStock($detallePadre, (float) $detEvento->cantidad, $entrega, $venta);
                    }
                }
            }

            $evento->update([
                'estado' => 'an',
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $validated['motivo'],
                'user_anulacion_id' => $request->user()?->id ?? $evento->user_id,
                'user_entregado_id' => null,
            ]);

            $entrega->recalcularEstado();

            return response()->json([
                'data' => $evento->fresh(['userAnulacion:id,name']),
                'message' => 'Evento anulado',
            ]);
        });
    }

    /**
     * POST /entregas-productos/{entregaId}/eventos/confirmar-en-camino
     *
     * Promueve TODOS los eventos en estado 'ec' (En Camino) a 'en' (Entregado)
     * para una entrega lógica. Aplica el stock real de cada uno y marca al
     * usuario que confirmó la entrega. Usado por el botón "Confirmar Entrega"
     * cuando el chofer regresa y reporta éxito de un viaje completo.
     */
    public function confirmarEnCamino(Request $request, string $entregaId)
    {
        return DB::transaction(function () use ($entregaId, $request) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($entregaId);
            $venta = Venta::find($entrega->venta_id);
            $userId = $request->user()?->id ?? $entrega->user_id;

            $eventos = EntregaEvento::where('entrega_producto_id', $entregaId)
                ->where('estado', 'ec')
                ->with('detalles')
                ->get();

            if ($eventos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay eventos En Camino para confirmar',
                    'error' => 'SIN_EVENTOS_EC',
                ], 422);
            }

            $detallesIndex = $entrega->productosEntregados->keyBy('id');

            foreach ($eventos as $evento) {
                $evento->estado = 'en';
                $evento->fecha_ejecutada = now();
                $evento->user_entregado_id = $userId;
                $evento->save();

                foreach ($evento->detalles as $detEvento) {
                    $detallePadre = $detallesIndex->get($detEvento->detalle_entrega_producto_id);
                    if ($detallePadre) {
                        $this->aplicarStock(
                            $detallePadre,
                            (float) $detEvento->cantidad,
                            $entrega,
                            $venta,
                        );
                    }
                }
            }

            $entrega->recalcularEstado();

            return response()->json([
                'data' => [
                    'eventos_confirmados' => $eventos->count(),
                    'entrega_estado' => $entrega->fresh()->estado_entrega,
                ],
                'message' => "Se confirmaron {$eventos->count()} entrega(s) En Camino",
            ]);
        });
    }

    public function destroy(string $entregaId, string $eventoId)
    {
        return DB::transaction(function () use ($entregaId, $eventoId) {
            $evento = EntregaEvento::where('entrega_producto_id', $entregaId)
                ->with('detalles')
                ->findOrFail($eventoId);
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($entregaId);

            // Revertir stock antes de borrar
            if ($evento->estado === 'en') {
                $venta = Venta::find($entrega->venta_id);
                $detallesIndex = $entrega->productosEntregados->keyBy('id');
                foreach ($evento->detalles as $detEvento) {
                    $detallePadre = $detallesIndex->get($detEvento->detalle_entrega_producto_id);
                    if ($detallePadre) {
                        $this->revertirStock($detallePadre, (float) $detEvento->cantidad, $entrega, $venta);
                    }
                }
            }

            $evento->detalles()->delete();
            $evento->delete();
            $entrega->recalcularEstado();

            return response()->json([
                'data' => 'ok',
                'message' => 'Evento eliminado',
            ]);
        });
    }

    /**
     * Aplica el stock real cuando un evento pasa a 'en'.
     *   - Decrementa cantidad_pendiente de la UDV
     *   - Si la venta no aplicó stock al crearse, descuenta de productoalmacen
     *   - Procesa complementarios
     */
    private function aplicarStock(
        DetalleEntregaProducto $detallePadre,
        float $cantidad,
        EntregaProducto $entrega,
        ?Venta $venta,
    ): void {
        if ($cantidad <= 0) return;
        $udv = $detallePadre->unidadDerivadaVenta()->first();
        if (! $udv) return;

        $udv->decrement('cantidad_pendiente', $cantidad);

        if (! ($venta?->stock_aplicado ?? false)) {
            $productoAlmacen = $udv->productoAlmacenVenta?->productoAlmacen;
            if ($productoAlmacen) {
                $fraccion = $cantidad * (float) $udv->factor;
                $productoAlmacen->decrement('stock_fraccion', $fraccion);

                ComplementarioStockService::procesarComplementarioPorFactor(
                    $productoAlmacen->id,
                    (float) $udv->factor,
                    $cantidad,
                    $entrega->almacen_salida_id,
                    false,
                );
            }
        }
    }

    /** Reversa de `aplicarStock` — al anular/borrar un evento entregado */
    private function revertirStock(
        DetalleEntregaProducto $detallePadre,
        float $cantidad,
        EntregaProducto $entrega,
        ?Venta $venta,
    ): void {
        if ($cantidad <= 0) return;
        $udv = $detallePadre->unidadDerivadaVenta()->first();
        if (! $udv) return;

        $udv->increment('cantidad_pendiente', $cantidad);

        if (! ($venta?->stock_aplicado ?? false)) {
            $productoAlmacen = $udv->productoAlmacenVenta?->productoAlmacen;
            if ($productoAlmacen) {
                $fraccion = $cantidad * (float) $udv->factor;
                $productoAlmacen->increment('stock_fraccion', $fraccion);

                ComplementarioStockService::procesarComplementarioPorFactor(
                    $productoAlmacen->id,
                    (float) $udv->factor,
                    $cantidad,
                    $entrega->almacen_salida_id,
                    true,
                );
            }
        }
    }
}
