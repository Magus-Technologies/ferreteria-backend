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
use App\Models\ProductoAlmacen;
use App\Services\FirebaseNotificationService;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'vehiculo_id' => 'sometimes|integer',
            'estado_entrega' => 'sometimes|string',
            'fecha_desde' => 'sometimes|date',
            'fecha_hasta' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:-1|max:100', // Permitir -1 para traer todos
            'search' => 'sometimes|string|nullable',
            'tipo_despacho' => 'sometimes|string|nullable',
            'tipo_entrega' => 'sometimes|string|nullable',
            // `URLSearchParams` envía booleans como texto ("true"/"false").
            // Aceptamos ambas formas para no romper los filtros del calendario.
            'solo_programadas' => 'sometimes|in:true,false,1,0',
        ]);

        // 🔍 DEBUG: Log de parámetros recibidos

        $query = EntregaProducto::query()
            ->with([
                'venta:id,serie,numero,tipo_documento,cliente_id',
                'venta.cliente:id,nombres,apellidos,razon_social,numero_documento,telefono',
                'venta.cliente.direcciones:id,cliente_id,tipo,direccion,referencia,latitud,longitud',
                // Historial de cambios de la venta — usado para mostrar en el
                // modal de entrega "esta venta fue editada — ver cambios"
                // (productos anteriores vs actuales).
                'venta.historial' => fn ($q) => $q->where('accion', 'edicion')->orderBy('fecha', 'desc'),
                'venta.historial.usuario:id,name',
                'almacenSalida:id,name',
                'despachador:id,name',
                'vehiculo:id,name,tipo,placa',
                'user:id,name',
                'userEntregado:id,name',
                'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
                'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
                // Entregas hermanas de la misma venta — necesarias para calcular
                // totales acumulados de entregado/programado en el detalle de entrega.
                'venta.entregasProductos:id,venta_id,estado_entrega',
                'venta.entregasProductos.productosEntregados:id,entrega_producto_id,unidad_derivada_venta_id,cantidad_solicitada',
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

        if ($request->has('vehiculo_id')) {
            $query->where('vehiculo_id', $request->vehiculo_id);
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

        // Filter by tipo_entrega
        if ($request->has('tipo_entrega') && $request->tipo_entrega) {
            $query->where('tipo_entrega', $request->tipo_entrega);
        }

        // Filter solo_programadas: solo entregas a domicilio/parcial con fecha programada futura, no entregadas ni canceladas.
        // Usado por el calendario de entregas para evitar duplicados (en-tienda, ya entregadas, sin hora).
        if ($request->boolean('solo_programadas')) {
            $query->whereNotNull('fecha_programada')
                  ->where('tipo_despacho', '!=', 'in')
                  ->whereNotIn('estado_entrega', ['en', 'ca']);
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
            'grupo_entrega_id' => 'nullable|integer',
            'tipo_entrega' => 'required|string',
            'tipo_despacho' => 'nullable|string',
            'estado_entrega' => 'required|string',
            'fecha_entrega' => 'required|date',
            'fecha_programada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'direccion_entrega' => 'nullable|string',
            'referencia_entrega' => 'nullable|string',
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
            $esParcialInmediatoPendienteAlmacen =
                ($validated['tipo_entrega'] ?? null) === 'pa' &&
                ($validated['tipo_despacho'] ?? null) === 'in' &&
                ($validated['estado_entrega'] ?? null) === 'pe' &&
                ($validated['quien_entrega'] ?? null) === 'almacen';
            $esRecojoTiendaPendienteAlmacen =
                ($validated['tipo_entrega'] ?? null) === 'rt' &&
                ($validated['tipo_despacho'] ?? null) === 'in' &&
                ($validated['estado_entrega'] ?? null) === 'pe' &&
                ($validated['quien_entrega'] ?? null) === 'almacen';

            if ($esRecojoTiendaPendienteAlmacen) {
                $totalPendienteRegistrado = collect($validated['productos_entregados'] ?? [])
                    ->sum(fn ($detalle) => (float) ($detalle['cantidad_entregada'] ?? 0));

                if ($totalPendienteRegistrado <= 0) {
                    throw ValidationException::withMessages([
                        'productos_entregados' => [
                            'Despacho en tienda con entrega por almacén requiere una cantidad pendiente mayor a 0.',
                        ],
                    ]);
                }
            }

            // Si la entrega nace ya como ENTREGADO (caso EnTienda inmediato),
            // el creador es también quien entregó — registrar para auditoría.
            $userEntregadoId = $validated['estado_entrega'] === 'en'
                ? $validated['user_id']
                : null;

            // Crear entrega
            $entrega = EntregaProducto::create([
                'venta_id' => $validated['venta_id'],
                'grupo_entrega_id' => $validated['grupo_entrega_id'] ?? null,
                'tipo_entrega' => $validated['tipo_entrega'],
                'tipo_despacho' => $validated['tipo_despacho'] ?? null,
                'estado_entrega' => $validated['estado_entrega'],
                'fecha_entrega' => $validated['fecha_entrega'],
                'fecha_programada' => $validated['fecha_programada'] ?? null,
                'hora_inicio' => $validated['hora_inicio'] ?? null,
                'hora_fin' => $validated['hora_fin'] ?? null,
                'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                'referencia_entrega' => $validated['referencia_entrega'] ?? null,
                'latitud' => $validated['latitud'] ?? null,
                'longitud' => $validated['longitud'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'almacen_salida_id' => $validated['almacen_salida_id'],
                'chofer_id' => $validated['chofer_id'] ?? null,
                'quien_entrega' => $validated['quien_entrega'] ?? null,
                'user_id' => $validated['user_id'],
                'user_entregado_id' => $userEntregadoId,
                'tipo_pedido' => $validated['tipo_pedido'] ?? 'interno',
                'cargo_destino' => $validated['cargo_destino'] ?? null,
                'vehiculo_id' => $validated['vehiculo_id'] ?? null,
            ]);

            if ($entrega->tipo_entrega === 'pa' && ! $entrega->grupo_entrega_id) {
                $entrega->grupo_entrega_id = $entrega->id;
                $entrega->save();
            }

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
                    throw ValidationException::withMessages([
                        'productos_entregados' => [
                            "La cantidad entregada ({$cantidadEntregada}) no puede ser mayor a la cantidad pendiente ({$cantidadPendiente})",
                        ],
                    ]);
                }

                // Crear detalle de entrega
                DetalleEntregaProducto::create([
                    'entrega_producto_id' => $entrega->id,
                    'unidad_derivada_venta_id' => $detalle['unidad_derivada_venta_id'],
                    'cantidad_solicitada' => $cantidadEntregada,
                    'ubicacion' => $detalle['ubicacion'] ?? null,
                ]);

                // Parcial + Inmediato + Almacén: registrar el tramo pendiente
                // sin consumir aún stock ni cantidad_pendiente. Se consume
                // recién cuando almacén confirma la entrega desde Mis Entregas.
                if (! $esParcialInmediatoPendienteAlmacen && ! $esRecojoTiendaPendienteAlmacen) {
                    // Actualizar cantidad pendiente
                    $unidadDerivadaVenta->decrement('cantidad_pendiente', $cantidadEntregada);

                    // Descontar stock solo si la venta aún no lo aplicó.
                    // Cubre Parcial, ventas legacy sin tipo_despacho, y Domicilio/En Tienda
                    // creadas con "Omitir entrega" (donde el stock se difiere a la entrega).
                    if (! $venta->stock_aplicado) {
                        $productoAlmacenVenta = $unidadDerivadaVenta->productoAlmacenVenta;
                        $productoAlmacen = $productoAlmacenVenta?->productoAlmacen;

                        if ($productoAlmacen) {
                            $cantidadEnFraccion = $cantidadEntregada * (float) $unidadDerivadaVenta->factor;
                            $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);

                            // Descontar producto complementario
                            ComplementarioStockService::procesarComplementarioPorFactor(
                                $productoAlmacen->id,
                                (float) $unidadDerivadaVenta->factor,
                                $cantidadEntregada,
                                $validated['almacen_salida_id'],
                                false // salida
                            );
                        }
                    }
                }
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
            'venta.cliente.direcciones:id,cliente_id,tipo,direccion,referencia,latitud,longitud',
            'venta.entregasProductos:id,venta_id,grupo_entrega_id,estado_entrega,tipo_entrega,tipo_despacho,fecha_entrega,fecha_programada,hora_inicio,hora_fin,direccion_entrega,referencia_entrega,latitud,longitud,observaciones,chofer_id,vehiculo_id,quien_entrega,tipo_pedido,cargo_destino,user_id,user_entregado_id,created_at',
            'venta.entregasProductos.productosEntregados:id,entrega_producto_id,unidad_derivada_venta_id,cantidad_solicitada',
            'venta.entregasProductos.despachador:id,name,telefono,celular,email',
            'venta.entregasProductos.vehiculo:id,name,tipo,placa',
            'venta.almacen:id,name',
            'almacenSalida:id,name',
            'despachador:id,name,telefono,celular,email',
            'vehiculo:id,name,tipo,placa',
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
            'referencia_entrega' => 'nullable|string',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'observaciones' => 'nullable|string',
            'almacen_salida_id' => 'sometimes|integer',
            'chofer_id' => 'nullable|string',
            'vehiculo_id' => 'nullable|integer|exists:vehiculo,id',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'productos_entregados' => 'sometimes|array',
            'productos_entregados.*.unidad_derivada_venta_id' => 'required_with:productos_entregados|integer',
            'productos_entregados.*.cantidad_entregada' => 'required_with:productos_entregados|numeric|min:0',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $entrega = EntregaProducto::with([
                'productosEntregados',
                'productosEntregados.unidadDerivadaVenta',
            ])->findOrFail($id);
            $venta = Venta::find($entrega->venta_id);

            if (! empty($validated['productos_entregados'])) {
                $detallesExistentes = $entrega->productosEntregados->keyBy('unidad_derivada_venta_id');

                foreach ($validated['productos_entregados'] as $detalleActualizado) {
                    $unidadDerivadaVentaId = (int) $detalleActualizado['unidad_derivada_venta_id'];
                    $cantidadNueva = (float) $detalleActualizado['cantidad_entregada'];

                    /** @var DetalleEntregaProducto|null $detalleExistente */
                    $detalleExistente = $detallesExistentes->get($unidadDerivadaVentaId);

                    if (! $detalleExistente) {
                        throw ValidationException::withMessages([
                            'productos_entregados' => [
                                "No se encontró el detalle de entrega para la unidad {$unidadDerivadaVentaId}",
                            ],
                        ]);
                    }

                    $cantidadProgramadaOriginal = (float) $detalleExistente->cantidad_solicitada;

                    // Placeholder delivery (created via "Omitir" in crear-venta):
                    // cantidad_entregada = 0 means no stock was reserved at create time.
                    // Validate against UDV cantidad_pendiente and consume it immediately
                    // (same moment store() would consume it for regular deliveries).
                    $esPlaceholderOmitido = ($cantidadProgramadaOriginal === 0.0);

                    if ($esPlaceholderOmitido) {
                        $udv = $detalleExistente->unidadDerivadaVenta;
                        $maxPermitido = $udv ? (float) $udv->cantidad_pendiente : 0.0;
                        if ($cantidadNueva > $maxPermitido) {
                            throw ValidationException::withMessages([
                                'productos_entregados' => [
                                    "La cantidad a entregar ({$cantidadNueva}) supera la cantidad disponible ({$maxPermitido})",
                                ],
                            ]);
                        }
                        // Consume UDV pendiente and decrement stock right now —
                        // the Domicilio flow sends 'ec' + amounts together but
                        // 'en' arrives later without amounts, so we can't wait.
                        if ($cantidadNueva > 0 && $udv) {
                            $udv->decrement('cantidad_pendiente', $cantidadNueva);

                            if (! ($venta?->stock_aplicado ?? false)) {
                                $productoAlmacen = $udv->productoAlmacenVenta?->productoAlmacen;
                                if ($productoAlmacen) {
                                    $fraccion = $cantidadNueva * (float) $udv->factor;
                                    $productoAlmacen->decrement('stock_fraccion', $fraccion);

                                    ComplementarioStockService::procesarComplementarioPorFactor(
                                        $productoAlmacen->id,
                                        (float) $udv->factor,
                                        $cantidadNueva,
                                        $entrega->almacen_salida_id,
                                        false
                                    );
                                }
                            }
                        }
                    } else {
                        if ($cantidadNueva > $cantidadProgramadaOriginal) {
                            throw ValidationException::withMessages([
                                'productos_entregados' => [
                                    "La cantidad entregada ({$cantidadNueva}) no puede ser mayor a la cantidad programada ({$cantidadProgramadaOriginal})",
                                ],
                            ]);
                        }
                    }

                    // Placeholder: nothing was reserved before, so nothing to release.
                    $cantidadLiberada = $esPlaceholderOmitido ? 0.0 : ($cantidadProgramadaOriginal - $cantidadNueva);
                    $esTramoPendienteSinReserva =
                        in_array($entrega->tipo_entrega, ['pa', 'rt'], true) &&
                        $entrega->tipo_despacho === 'in' &&
                        $entrega->quien_entrega === 'almacen';

                    if ($cantidadLiberada > 0 && ! $esTramoPendienteSinReserva) {
                        UnidadDerivadaInmutableVenta::whereKey($unidadDerivadaVentaId)
                            ->increment('cantidad_pendiente', $cantidadLiberada);
                    }

                    $detalleExistente->cantidad_solicitada = $cantidadNueva;
                    $detalleExistente->save();
                }

                $entrega->load('productosEntregados');
            }

            // Si el estado pasa a ENTREGADO ahora (no estaba antes), registrar
            // al usuario que lo marcó. No sobrescribir si ya estaba en 'en'.
            $estadoNuevo = $validated['estado_entrega'] ?? null;
            $estadoAnterior = $entrega->estado_entrega;
            if ($estadoNuevo === 'en' && $estadoAnterior !== 'en') {
                $validated['user_entregado_id'] = auth()->id();
                // Si la entrega tenía una anulación previa registrada, se
                // limpia ahora que vuelve a entregarse correctamente.
                $validated['fecha_anulacion'] = null;
                $validated['motivo_anulacion'] = null;
                $validated['user_anulacion_id'] = null;

                // En entregas programadas/parciales la reserva ya se aplicó al
                // crear la entrega: `detalle.cantidad_entregada` representa la
                // cantidad asignada a ESTA entrega y `cantidad_pendiente` de la
                // UDV representa lo que aún queda por otra entrega. Al
                // confirmar, solo cambia el estado físico de la entrega; no se
                // debe consumir el pendiente restante de toda la venta.
                $requiereConsumoDiferido =
                    (
                        $entrega->tipo_entrega === 'pa' ||
                        $entrega->tipo_entrega === 'rt'
                    ) &&
                    $entrega->tipo_despacho === 'in' &&
                    $entrega->quien_entrega === 'almacen';

                if ($requiereConsumoDiferido) {
                    foreach ($entrega->productosEntregados as $detalle) {
                        $unidadDerivadaVenta = $detalle->unidadDerivadaVenta;
                        if (! $unidadDerivadaVenta) continue;

                        $cantidadEntregada = (float) $detalle->cantidad_solicitada;
                        if ($cantidadEntregada <= 0 && $entrega->tipo_entrega === 'rt') {
                            $cantidadEntregada = (float) $unidadDerivadaVenta->cantidad_pendiente;
                            $detalle->cantidad_solicitada = $cantidadEntregada;
                            $detalle->save();
                        }
                        $cantidadPendiente = (float) $unidadDerivadaVenta->cantidad_pendiente;

                        if ($cantidadEntregada > $cantidadPendiente) {
                            throw ValidationException::withMessages([
                                'productos_entregados' => [
                                    "La cantidad entregada ({$cantidadEntregada}) no puede ser mayor a la cantidad pendiente ({$cantidadPendiente})",
                                ],
                            ]);
                        }

                        $unidadDerivadaVenta->decrement('cantidad_pendiente', $cantidadEntregada);

                        if (! ($venta?->stock_aplicado ?? false)) {
                            $productoAlmacen = $unidadDerivadaVenta->productoAlmacenVenta?->productoAlmacen;
                            if ($productoAlmacen) {
                                $cantidadEnFraccion = $cantidadEntregada * (float) $unidadDerivadaVenta->factor;
                                $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);

                                ComplementarioStockService::procesarComplementarioPorFactor(
                                    $productoAlmacen->id,
                                    (float) $unidadDerivadaVenta->factor,
                                    $cantidadEntregada,
                                    $entrega->almacen_salida_id,
                                    false
                                );
                            }
                        }
                    }
                }

            }

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
                    'userEntregado:id,name',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
                    'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
                ]),
                'message' => 'Entrega de producto actualizada exitosamente',
            ]);
        });
    }

    /**
     * Anular una entrega que se marcó como entregada por error.
     *
     * Vuelve `estado_entrega` a `'pe'` (pendiente) y registra `fecha_anulacion`,
     * `motivo_anulacion`, `user_anulacion_id` para auditoría. NO toca stock,
     * NO modifica el comprobante SUNAT — la venta sigue válida, solo se
     * deshace la marca física de entregado.
     *
     * Si la entrega vuelve a marcarse como `'en'` después, los 3 campos de
     * anulación se limpian automáticamente (ver `update()`).
     */
    public function anular(Request $request, string $id)
    {
        $validated = $request->validate([
            'motivo' => 'required|string|min:5|max:500',
        ], [
            'motivo.required' => 'Debes ingresar un motivo de anulación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

            return DB::transaction(function () use ($id, $validated) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($id);

            if (!in_array($entrega->estado_entrega, ['en', 'ec'])) {
                return response()->json([
                    'message' => 'Solo se pueden anular entregas que estén En Camino o Entregadas.',
                    'error' => 'ESTADO_NO_ANULABLE',
                ], 422);
            }

            // Revertir cantidad_pendiente en las UDV al anular
            foreach ($entrega->productosEntregados as $detalle) {
                $unidadDerivadaVenta = $detalle->unidadDerivadaVenta;
                if ($unidadDerivadaVenta) {
                    $unidadDerivadaVenta->increment('cantidad_pendiente', (float) $detalle->cantidad_solicitada);
                }
            }

            $entrega->update([
                'estado_entrega' => 'pe',
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $validated['motivo'],
                'user_anulacion_id' => auth()->id(),
                // Limpiar el "entregado por" anterior — ya no aplica.
                'user_entregado_id' => null,
            ]);

            return response()->json([
                'data' => $entrega->fresh([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social,numero_documento',
                    'almacenSalida:id,name',
                    'despachador:id,name',
                    'userAnulacion:id,name',
                ]),
                'message' => 'Entrega anulada — vuelve a estado Pendiente.',
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (anular entrega).
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $entrega = EntregaProducto::with([
                'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen',
            ])->findOrFail($id);

            // Obtener la venta para saber si el stock se descontó al entregar
            // Si la venta tiene stock_aplicado=false, el stock se descontó al crear la entrega
            // (caso Parcial, legacy, o Domicilio/EnTienda con omitir_entrega).
            $venta = Venta::find($entrega->venta_id);
            $stockDescontadoAlEntregar = ! ($venta?->stock_aplicado ?? false);

            // Revertir cantidades pendientes y stock
            foreach ($entrega->productosEntregados as $detalle) {
                $unidadDerivadaVenta = $detalle->unidadDerivadaVenta;
                if (! $unidadDerivadaVenta) continue;

                $unidadDerivadaVenta->increment('cantidad_pendiente', (float) $detalle->cantidad_solicitada);

                // Revertir stock solo si se descontó al entregar (Parcial/legacy)
                if ($stockDescontadoAlEntregar) {
                    $productoAlmacen = $unidadDerivadaVenta->productoAlmacenVenta?->productoAlmacen;
                    if ($productoAlmacen) {
                        $cantidadEnFraccion = (float) $detalle->cantidad_solicitada * (float) $unidadDerivadaVenta->factor;
                        $productoAlmacen->increment('stock_fraccion', $cantidadEnFraccion);

                        // Revertir producto complementario
                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $productoAlmacen->id,
                            (float) $unidadDerivadaVenta->factor,
                            (float) $detalle->cantidad_solicitada,
                            $entrega->almacen_salida_id,
                            true // ingreso (revertir)
                        );
                    }
                }
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
