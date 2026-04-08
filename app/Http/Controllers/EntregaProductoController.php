<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDeVenta;
use App\Models\DetalleEntregaProducto;
use App\Models\EntregaProducto;
use App\Models\RequerimientoInterno;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\User;
use App\Models\Venta;
use App\Exceptions\UsuarioEnMantenimientoException;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntregaProductoController extends Controller
{
    private FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    /**
     * Display a listing of the resource (todas las entregas o por venta).
     */
    public function index(Request $request)
    {
        $request->validate([
            'venta_id' => 'sometimes|string',
            'almacen_salida_id' => 'sometimes|integer',
            'chofer_id' => 'sometimes|string',
            'estado_entrega' => 'sometimes|string',
            'fecha_desde' => 'sometimes|date',
            'fecha_hasta' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:-1|max:100', // Permitir -1 para traer todos
            'search' => 'sometimes|string|nullable',
            'tipo_despacho' => 'sometimes|string|nullable',
        ]);

        // 🔍 DEBUG: Log de parámetros recibidos
        \Log::info('📥 EntregaProductoController@index - Parámetros recibidos:', $request->all());

        $query = EntregaProducto::query()
            ->with([
                'venta:id,serie,numero,tipo_documento,cliente_id',
                'venta.cliente:id,nombres,apellidos,razon_social,numero_documento,telefono',
                'almacenSalida:id,name',
                'despachador:id,name',
                'vehiculo:id,name,tipo,placa',
                'user:id,name',
                'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
                'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
            ]);

        // Filter by venta_id
        if ($request->has('venta_id')) {
            $query->where('venta_id', $request->venta_id);
        }

        // Filter by almacen_salida_id
        if ($request->has('almacen_salida_id')) {
            $query->where('almacen_salida_id', $request->almacen_salida_id);
        }

        // Filter by chofer_id (incluye entregas sin despachador + externos de su cargo)
        if ($request->has('chofer_id')) {
            $user = User::find($request->chofer_id);
            $query->where(function ($q) use ($request, $user) {
                $q->where('chofer_id', $request->chofer_id)
                  ->orWhere(function ($q2) {
                      $q2->whereNull('chofer_id')->where('estado_entrega', 'pe');
                  });
                // También mostrar pedidos externos pendientes para el cargo del usuario
                if ($user && $user->cargo) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('tipo_pedido', 'externo')
                           ->where('cargo_destino', $user->cargo)
                           ->where('estado_entrega', 'pe')
                           ->whereNull('chofer_id');
                    });
                }
            });
        }

        // Excluir entregas de ventas anuladas o en espera
        $query->whereHas('venta', function ($q) {
            $q->whereNotIn('estado_de_venta', [
                EstadoDeVenta::Anulado->value,
                EstadoDeVenta::EnEspera->value,
            ]);
        });

        // Filter by estado_entrega (soporta múltiples valores separados por coma)
        if ($request->has('estado_entrega')) {
            $estados = explode(',', $request->estado_entrega);
            if (count($estados) === 1) {
                $query->where('estado_entrega', $estados[0]);
            } else {
                $query->whereIn('estado_entrega', $estados);
            }
        }
 
        // Filter by tipo_despacho
        if ($request->has('tipo_despacho') && $request->tipo_despacho) {
            $query->where('tipo_despacho', $request->tipo_despacho);
        }
 
        // Global search (Client name, series, number)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // Search by sale series/number
                $q->whereHas('venta', function ($qv) use ($search) {
                    $qv->where('serie', 'LIKE', "%{$search}%")
                       ->orWhere('numero', 'LIKE', "%{$search}%")
                       // Search by client data
                       ->orWhereHas('cliente', function ($qc) use ($search) {
                           $qc->where('razon_social', 'LIKE', "%{$search}%")
                              ->orWhere('nombres', 'LIKE', "%{$search}%")
                              ->orWhere('apellidos', 'LIKE', "%{$search}%")
                              ->orWhere('numero_documento', 'LIKE', "%{$search}%");
                       });
                });
            });
        }

        // Filter by fecha range usando fecha_programada (para entregas programadas)
        // o fecha_entrega si no hay fecha_programada
        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->where(function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    // Tiene fecha_programada y está en rango
                    $q2->whereNotNull('fecha_programada')
                       ->whereDate('fecha_programada', '>=', $request->fecha_desde)
                       ->whereDate('fecha_programada', '<=', $request->fecha_hasta);
                })->orWhere(function ($q2) use ($request) {
                    // No tiene fecha_programada, usar fecha_entrega
                    $q2->whereNull('fecha_programada')
                       ->whereDate('fecha_entrega', '>=', $request->fecha_desde)
                       ->whereDate('fecha_entrega', '<=', $request->fecha_hasta);
                });
            });
        } elseif ($request->has('fecha_desde')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('fecha_programada', '>=', $request->fecha_desde)
                  ->orWhereDate('fecha_entrega', '>=', $request->fecha_desde);
            });
        } elseif ($request->has('fecha_hasta')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('fecha_programada', '<=', $request->fecha_hasta)
                  ->orWhereDate('fecha_entrega', '<=', $request->fecha_hasta);
            });
        }

        $perPage = $request->input('per_page', 50);

        // Si per_page es -1, traer todos los registros (sin paginación)
        if ($perPage === -1 || $perPage === '-1') {
            $entregas = $query->orderBy('created_at', 'desc')->limit(1000)->get();
            
            // 🔍 DEBUG: Log de resultados
            \Log::info('📤 EntregaProductoController@index - Total entregas encontradas:', ['count' => $entregas->count()]);
            \Log::info('📤 EntregaProductoController@index - Primera entrega (si existe):', [
                'entrega' => $entregas->first() ? [
                    'id' => $entregas->first()->id,
                    'fecha_programada' => $entregas->first()->fecha_programada,
                    'hora_inicio' => $entregas->first()->hora_inicio,
                    'hora_fin' => $entregas->first()->hora_fin,
                    'despachador' => $entregas->first()->despachador ? [
                        'id' => $entregas->first()->despachador->id,
                        'name' => $entregas->first()->despachador->name,
                    ] : null,
                ] : null
            ]);
            
            return response()->json([
                'data' => $entregas,
                'total' => $entregas->count(),
            ]);
        }

        $entregas = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $entregas->items(),
            'total' => $entregas->total(),
            'current_page' => $entregas->currentPage(),
            'per_page' => $entregas->perPage(),
            'last_page' => $entregas->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar que el chofer asignado no esté en mantenimiento
        if ($request->has('chofer_id') && $request->input('chofer_id')) {
            $chofer = User::findOrFail($request->input('chofer_id'));
            $this->validarUsuarioEnMantenimiento($chofer);
        }

        $validated = $request->validate([
            'venta_id' => 'required|string',
            'tipo_entrega' => 'required|string',
            'tipo_despacho' => 'nullable|string',
            'estado_entrega' => 'required|string',
            'fecha_entrega' => 'required|date',
            'fecha_programada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'direccion_entrega' => 'nullable|string',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'observaciones' => 'nullable|string',
            'almacen_salida_id' => 'required|integer',
            'chofer_id' => 'nullable|string',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'user_id' => 'required|string',
            'tipo_pedido' => 'sometimes|string|in:interno,externo',
            'cargo_destino' => 'required_if:tipo_pedido,externo|nullable|string',
            'vehiculo_id' => 'nullable|integer|exists:vehiculo,id',
            'productos_entregados' => 'required|array',
            'productos_entregados.*.unidad_derivada_venta_id' => 'required|integer',
            'productos_entregados.*.cantidad_entregada' => 'required|numeric|min:0',
            'productos_entregados.*.ubicacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            // Verificar que la venta existe
            $venta = Venta::findOrFail($validated['venta_id']);

            // Crear entrega
            $entrega = EntregaProducto::create([
                'venta_id' => $validated['venta_id'],
                'tipo_entrega' => $validated['tipo_entrega'],
                'tipo_despacho' => $validated['tipo_despacho'] ?? null,
                'estado_entrega' => $validated['estado_entrega'],
                'fecha_entrega' => $validated['fecha_entrega'],
                'fecha_programada' => $validated['fecha_programada'] ?? null,
                'hora_inicio' => $validated['hora_inicio'] ?? null,
                'hora_fin' => $validated['hora_fin'] ?? null,
                'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                'latitud' => $validated['latitud'] ?? null,
                'longitud' => $validated['longitud'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'almacen_salida_id' => $validated['almacen_salida_id'],
                'chofer_id' => $validated['chofer_id'] ?? null,
                'quien_entrega' => $validated['quien_entrega'] ?? null,
                'user_id' => $validated['user_id'],
                'tipo_pedido' => $validated['tipo_pedido'] ?? 'interno',
                'cargo_destino' => $validated['cargo_destino'] ?? null,
                'vehiculo_id' => $validated['vehiculo_id'] ?? null,
            ]);

            // Crear detalles y actualizar cantidades pendientes
            foreach ($validated['productos_entregados'] as $detalle) {
                // Validar que la unidad derivada venta existe y pertenece a esta venta
                $unidadDerivadaVenta = UnidadDerivadaInmutableVenta::whereHas('productoAlmacenVenta', function ($query) use ($validated) {
                    $query->where('venta_id', $validated['venta_id']);
                })->findOrFail($detalle['unidad_derivada_venta_id']);

                // Validar que hay suficiente cantidad pendiente
                $cantidadEntregada = (float) $detalle['cantidad_entregada'];
                $cantidadPendiente = (float) $unidadDerivadaVenta->cantidad_pendiente;

                if ($cantidadEntregada > $cantidadPendiente) {
                    throw new \Exception("La cantidad entregada ({$cantidadEntregada}) no puede ser mayor a la cantidad pendiente ({$cantidadPendiente})");
                }

                // Crear detalle de entrega
                DetalleEntregaProducto::create([
                    'entrega_producto_id' => $entrega->id,
                    'unidad_derivada_venta_id' => $detalle['unidad_derivada_venta_id'],
                    'cantidad_entregada' => $cantidadEntregada,
                    'ubicacion' => $detalle['ubicacion'] ?? null,
                ]);

                // Actualizar cantidad pendiente
                $unidadDerivadaVenta->decrement('cantidad_pendiente', $cantidadEntregada);
            }

            // Enviar notificaciones FCM según tipo de pedido
            $tipoPedido = $validated['tipo_pedido'] ?? 'interno';
            $notifData = [
                'type' => 'pedido_entrega',
                'entrega_id' => (string) $entrega->id,
                'tipo_pedido' => $tipoPedido,
                'venta_serie' => $venta->serie ?? '',
                'venta_numero' => $venta->numero ?? '',
                'direccion' => $validated['direccion_entrega'] ?? '',
            ];
            $direccionEntrega = $validated['direccion_entrega'] ?? null;
            $notifBody = "Venta {$venta->serie}-{$venta->numero}" .
                ($direccionEntrega ? " a {$direccionEntrega}" : '');

            $cargoDestino = $validated['cargo_destino'] ?? null;
            if ($tipoPedido === 'externo' && !empty($cargoDestino)) {
                $this->firebaseService->sendToCargo(
                    $cargoDestino,
                    'Nueva Entrega Disponible',
                    $notifBody,
                    $notifData
                );
            } elseif (!empty($validated['chofer_id'])) {
                $despachador = User::find($validated['chofer_id']);
                if ($despachador && $despachador->fcm_token) {
                    $this->firebaseService->sendNotification(
                        $despachador->fcm_token,
                        'Nueva Entrega Programada',
                        $notifBody,
                        $notifData
                    );
                }
            }

            return response()->json([
                'data' => $entrega->load([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social',
                    'almacenSalida:id,name',
                    'despachador:id,name',
                    'user:id,name',
                    'vehiculo:id,name,tipo,placa',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
                ]),
                'message' => 'Entrega de producto creada exitosamente',
            ], 201);
        });
    }

    /**
     * Aceptar un pedido externo (first-come-first-served con lock)
     */
    public function aceptar(Request $request, string $id)
    {
        $user = $request->user();

        return DB::transaction(function () use ($id, $user) {
            // Lock para evitar race condition
            $entrega = EntregaProducto::where('id', $id)
                ->where('tipo_pedido', 'externo')
                ->where('estado_entrega', 'pe')
                ->whereNull('chofer_id')
                ->lockForUpdate()
                ->first();

            if (!$entrega) {
                return response()->json([
                    'message' => 'Esta entrega ya fue aceptada por otro usuario',
                ], 409);
            }

            // Verificar que el usuario tiene el cargo correcto
            if ($user->cargo !== $entrega->cargo_destino) {
                return response()->json([
                    'message' => 'No tienes el cargo requerido para aceptar esta entrega',
                ], 403);
            }

            $entrega->update([
                'chofer_id' => $user->id,
                'aceptado_at' => now(),
            ]);

            // Notificar al creador que alguien aceptó
            $creador = User::find($entrega->user_id);
            if ($creador && $creador->fcm_token) {
                $entrega->load('venta:id,serie,numero');
                $this->firebaseService->sendNotification(
                    $creador->fcm_token,
                    'Entrega Aceptada',
                    "{$user->name} aceptó la entrega de Venta {$entrega->venta->serie}-{$entrega->venta->numero}",
                    [
                        'type' => 'pedido_entrega_aceptado',
                        'entrega_id' => (string) $entrega->id,
                    ]
                );
            }

            // Notificar a los demás del mismo cargo que ya fue tomada
            $entrega->load('venta:id,serie,numero');
            $this->firebaseService->sendToCargo(
                $entrega->cargo_destino,
                'Entrega ya tomada',
                "{$user->name} aceptó la entrega de Venta {$entrega->venta->serie}-{$entrega->venta->numero}",
                [
                    'type' => 'pedido_entrega_tomado',
                    'entrega_id' => (string) $entrega->id,
                ],
                $user->id // excluir al que aceptó
            );

            return response()->json([
                'data' => $entrega->fresh([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social,numero_documento,telefono',
                    'almacenSalida:id,name',
                    'despachador:id,name',
                    'user:id,name',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
                    'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
                ]),
                'message' => 'Entrega aceptada exitosamente',
            ]);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $entrega = EntregaProducto::with([
            'venta:id,serie,numero,cliente_id,almacen_id',
            'venta.cliente:id,nombres,apellidos,razon_social,numero_documento,telefono,email',
            'venta.almacen:id,name',
            'almacenSalida:id,name',
            'despachador:id,name,telefono,celular,email',
            'user:id,name',
            'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
            'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
        ])->findOrFail($id);

        return response()->json(['data' => $entrega]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'tipo_entrega' => 'sometimes|string',
            'tipo_despacho' => 'nullable|string',
            'estado_entrega' => 'sometimes|string',
            'fecha_entrega' => 'sometimes|date',
            'fecha_programada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'direccion_entrega' => 'nullable|string',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'observaciones' => 'nullable|string',
            'almacen_salida_id' => 'sometimes|integer',
            'chofer_id' => 'nullable|string',
            'vehiculo_id' => 'nullable|integer|exists:vehiculo,id',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($id);

            // Update entrega
            $entrega->update($validated);

            return response()->json([
                'data' => $entrega->fresh([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social,numero_documento,telefono',
                    'almacenSalida:id,name',
                    'despachador:id,name',
                    'vehiculo:id,name,tipo,placa',
                    'user:id,name',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
                    'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
                ]),
                'message' => 'Entrega de producto actualizada exitosamente',
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (anular entrega).
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($id);

            // Revertir cantidades pendientes
            foreach ($entrega->productosEntregados as $detalle) {
                $unidadDerivadaVenta = UnidadDerivadaInmutableVenta::findOrFail($detalle->unidad_derivada_venta_id);
                $unidadDerivadaVenta->increment('cantidad_pendiente', (float) $detalle->cantidad_entregada);
            }

            // Eliminar detalles
            DetalleEntregaProducto::where('entrega_producto_id', $id)->delete();

            // Eliminar entrega
            $entrega->delete();

            return response()->json([
                'data' => 'ok',
                'message' => 'Entrega de producto eliminada exitosamente',
            ]);
        });
    }

    /**
     * Validar que el usuario no esté en mantenimiento
     * 
     * @param User $user
     * @throws UsuarioEnMantenimientoException
     */
    private function validarUsuarioEnMantenimiento(User $user): void
    {
        $osAprobadaHoy = RequerimientoInterno::query()
            ->where('tipo_solicitud', 'OS')
            ->where('estado', 'aprobado')
            ->where('user_id', $user->id)
            ->whereHas('servicios', function ($query) {
                $query->whereDate('fecha_inicio_estimada', today());
            })
            ->exists();

        if ($osAprobadaHoy) {
            throw new UsuarioEnMantenimientoException(
                'No puedes hacer despachos. Tienes mantenimiento programado para hoy'
            );
        }
    }
}
