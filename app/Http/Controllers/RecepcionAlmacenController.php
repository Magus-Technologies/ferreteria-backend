<?php

namespace App\Http\Controllers;

use App\Models\RecepcionAlmacen;
use App\Models\ProductoAlmacenRecepcion;
use App\Models\UnidadDerivadaInmutableRecepcion;
use App\Models\HistorialUnidadDerivadaInmutableRecepcion;
use App\Models\UnidadDerivadaInmutableCompra;
use App\Models\UnidadDerivadaInmutable;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCompra;
use App\Enums\EstadoDeCompra;
use App\Enums\EstadoDeCompraDefinitiva;
use App\Services\Cache\ProductoCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RecepcionAlmacenController extends Controller
{
    /**
     * Convertir claves de relaciones camelCase a snake_case recursivamente
     * para mantener compatibilidad con el frontend (que usaba Prisma con snake_case)
     */
    private function toSnakeCase($data)
    {
        if ($data instanceof \Illuminate\Database\Eloquent\Collection || $data instanceof \Illuminate\Support\Collection) {
            return $data->map(fn($item) => $this->toSnakeCase($item));
        }

        if ($data instanceof \Illuminate\Database\Eloquent\Model) {
            $array = $data->attributesToArray();
            $relations = $data->getRelations();

            $result = $array;
            foreach ($relations as $key => $value) {
                $snakeKey = \Illuminate\Support\Str::snake($key);
                $result[$snakeKey] = $this->toSnakeCase($value);
            }

            return $result;
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $snakeKey = is_string($key) ? \Illuminate\Support\Str::snake($key) : $key;
                $result[$snakeKey] = is_array($value) || is_object($value) ? $this->toSnakeCase($value) : $value;
            }
            return $result;
        }

        return $data;
    }

    /**
     * Listar recepciones de almacén
     * GET /api/recepciones-almacen?almacen_id=&fecha_desde=&fecha_hasta=&user_id=&estado=&compra_id=
     */
    public function index(Request $request)
    {
        $query = RecepcionAlmacen::with([
            'compra.proveedor',
            'compra.almacen',
            'compra.ordenCompra:id,codigo,estado',
            'ordenCompra.proveedor',
            'ordenCompra.almacen',
            'productosPorAlmacen.productoAlmacen.almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.historial',
            'user',
        ]);

        if ($request->has('compra_id')) {
            $query->where('compra_id', $request->compra_id);
        }

        if ($request->has('almacen_id')) {
            // Filtrar por almacén de Compra antigua O almacén de OrdenCompra
            $query->where(function ($q) use ($request) {
                $q->whereHas('compra', function ($subQ) use ($request) {
                    $subQ->where('almacen_id', $request->almacen_id);
                })
                // O recepciones de OrdenCompra con el mismo almacén
                ->orWhereHas('ordenCompra', function ($subQ) use ($request) {
                    $subQ->where('almacen_id', $request->almacen_id);
                });
            });
        }

        // Filtrar por el ALMACÉN RECEPTOR real (donde entró el stock), que puede
        // diferir del almacén de la Compra/OrdenCompra cuando se recepciona en otro almacén.
        if ($request->has('almacen_recepcion_id')) {
            $query->whereHas('productosPorAlmacen.productoAlmacen', function ($q) use ($request) {
                $q->where('almacen_id', $request->almacen_recepcion_id);
            });
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', filter_var($request->estado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('proveedor_id')) {
            // Filtrar por proveedor de Compra antigua O proveedor de OrdenCompra
            $query->where(function ($q) use ($request) {
                $q->whereHas('compra', function ($subQ) use ($request) {
                    $subQ->where('proveedor_id', $request->proveedor_id);
                })
                ->orWhereHas('ordenCompra', function ($subQ) use ($request) {
                    $subQ->where('proveedor_id', $request->proveedor_id);
                });
            });
        }

        if ($request->has('tipo_documento')) {
            // Filtrar por tipo de documento de Compra
            $query->whereHas('compra', function ($subQ) use ($request) {
                $subQ->where('tipo_documento', $request->tipo_documento);
            });
        }
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'LIKE', "%{$search}%")
                    ->orWhereHas('compra', function ($subQ) use ($search) {
                        $subQ->where('serie', 'LIKE', "%{$search}%")
                            ->orWhere('numero', 'LIKE', "%{$search}%")
                            ->orWhere('guia', 'LIKE', "%{$search}%")
                            ->orWhereHas('proveedor', function ($pQ) use ($search) {
                                $pQ->where('razon_social', 'LIKE', "%{$search}%")
                                    ->orWhere('ruc', 'LIKE', "%{$search}%");
                            });
                    })
                    ->orWhereHas('ordenCompra', function ($subQ) use ($search) {
                        $subQ->where('codigo', 'LIKE', "%{$search}%")
                            ->orWhere('guia', 'LIKE', "%{$search}%")
                            ->orWhereHas('proveedor', function ($pQ) use ($search) {
                                $pQ->where('razon_social', 'LIKE', "%{$search}%")
                                    ->orWhere('ruc', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        // Filtrar solo órdenes de compra aprobadas (completada)
        // Soporta tanto Compra antigua como OrdenCompra nueva
        $query->where(function ($q) {
            $q->whereHas('compra', function ($subQ) {
                $subQ->whereIn('estado_de_compra', [
                    EstadoDeCompraDefinitiva::Creado,
                    EstadoDeCompraDefinitiva::EnEspera,
                    EstadoDeCompraDefinitiva::Procesado
                ]);
            })
            ->orWhereHas('ordenCompra', function ($subQ) {
                $subQ->where('estado', EstadoDeCompra::Completada);
            });
        });

        // Filtrar recepciones de finalización (técnicas)
        // Se muestran solo si NO existen recepciones normales para la misma compra/orden.
        // Si existen normales, las de finalización se ocultan porque su data ya se heredó a las normales.
        $query->where(function ($q) {
            $q->where('es_finalizacion', false)
                ->orWhere(function ($q2) {
                    $q2->where('es_finalizacion', true)
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('recepcionalmacen as r_sub')
                                ->where('r_sub.es_finalizacion', false)
                                ->where('r_sub.estado', true)
                                ->where(function ($q_match) {
                                    $q_match->where(function ($q_compra) {
                                        $q_compra->whereNotNull('recepcionalmacen.compra_id')
                                            ->whereColumn('r_sub.compra_id', 'recepcionalmacen.compra_id');
                                    })
                                    ->orWhere(function ($q_orden) {
                                        $q_orden->whereNotNull('recepcionalmacen.orden_compra_id')
                                            ->whereColumn('r_sub.orden_compra_id', 'recepcionalmacen.orden_compra_id');
                                    });
                                });
                        });
                });
        });

        $query->orderBy('fecha', 'desc');

        $perPage = $request->input('per_page', 50);

        if ($perPage == -1) {
            $recepciones = $query->get();
            return response()->json(['data' => $this->toSnakeCase($recepciones)]);
        }

        $paginated = $query->paginate($perPage);
        $items = collect($paginated->items());

        // Enriquecer con datos de finalización de la compra/orden (herencia de datos para las columnas de la tabla)
        $compraIds = $items->pluck('compra_id')->filter()->unique();
        $ordenCompraIds = $items->pluck('orden_compra_id')->filter()->unique();

        if ($compraIds->isNotEmpty() || $ordenCompraIds->isNotEmpty()) {
            $finalizaciones = RecepcionAlmacen::where('es_finalizacion', true)
                ->where('estado', true)
                ->where(function ($q) use ($compraIds, $ordenCompraIds) {
                    if ($compraIds->isNotEmpty()) $q->whereIn('compra_id', $compraIds);
                    if ($ordenCompraIds->isNotEmpty()) $q->orWhereIn('orden_compra_id', $ordenCompraIds);
                })
                ->get();

            $items->each(function ($item) use ($finalizaciones) {
                // Si el item ya es una finalización, conserva SUS propios
                // datos (motivo/fecha). No heredar de la finalización activa,
                // porque sobrescribiría finalizaciones anuladas/anteriores de
                // la misma compra con el motivo de la nueva.
                if ($item->es_finalizacion) {
                    return;
                }

                // No heredar datos de finalización a recepciones anuladas/inactivas:
                // deben conservar su estado real (deshechas), no mostrarse como finalizadas.
                if (!$item->estado || $item->anulada) {
                    return;
                }

                $fin = $finalizaciones->first(function ($f) use ($item) {
                    return ($item->compra_id && $f->compra_id === $item->compra_id) ||
                           ($item->orden_compra_id && $f->orden_compra_id === $item->orden_compra_id);
                });

                if ($fin) {
                    $item->setAttribute('motivo_finalizacion', $fin->motivo_finalizacion);
                    $item->setAttribute('fecha_finalizacion', $fin->fecha_finalizacion);
                    $item->setAttribute('es_finalizacion', true); // Mostramos el badge de finalizada
                }
            });
        }

        return response()->json([
            'data' => $this->toSnakeCase($items),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /**
     * Obtener una recepción específica
     * GET /api/recepciones-almacen/{id}
     */
    public function show($id)
    {
        $recepcion = RecepcionAlmacen::with([
            'compra.proveedor',
            'compra.almacen',
            'compra.productosPorAlmacen.unidadesDerivadas',
            'ordenCompra.proveedor',
            'ordenCompra.almacen',
            'ordenCompra.productos',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.historial',
            'user',
        ])->find($id);

        if (!$recepcion) {
            return response()->json([
                'error' => ['message' => 'Recepción no encontrada']
            ], 404);
        }

        // Enriquecer con cantidades pedidas, recepcionadas y finalizadas
        $this->enrichRecepcionWithCounts($recepcion);

        return response()->json(['data' => $this->toSnakeCase($recepcion)]);
    }

    /**
     * Enriquecer una recepción con cantidades pedidas, recepcionadas y finalizadas
     */
    private function enrichRecepcionWithCounts($recepcion)
    {
        $parentDocId = $recepcion->compra_id ?? $recepcion->orden_compra_id;
        if (!$parentDocId) return;

        // Obtener todas las recepciones asociadas al mismo documento padre.
        // Incluir SIEMPRE la recepción que se está viendo, aunque esté deshecha
        // (estado=false): así su detalle conserva las cantidades recepcionadas /
        // finalizadas y no se muestran en blanco al anularla.
        $query = RecepcionAlmacen::where(function ($q) use ($recepcion) {
            $q->where('estado', true)->orWhere('id', $recepcion->id);
        });
        if ($recepcion->compra_id) {
            $query->where('compra_id', $recepcion->compra_id);
        } else {
            $query->where('orden_compra_id', $recepcion->orden_compra_id);
        }

        $todasRecepciones = $query->with([
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable'
        ])->get();

        // Obtener counts originales del documento padre
        $parentDoc = null;
        if ($recepcion->compra_id) {
            $parentDoc = $recepcion->compra;
        } else {
            $parentDoc = $recepcion->ordenCompra;
        }

        foreach ($recepcion->productosPorAlmacen as $productoRecepcion) {
            $productoAlmacenId = $productoRecepcion->producto_almacen_id;
            $productoId = $productoRecepcion->productoAlmacen->producto_id;
            
            // Encontrar el producto original en el documento padre
            $parentProduct = null;
            if ($recepcion->compra_id && $parentDoc) {
                $parentProduct = $parentDoc->productosPorAlmacen
                    ->where('producto_almacen_id', $productoAlmacenId)
                    ->first();
            } else if ($recepcion->orden_compra_id && $parentDoc) {
                $parentProduct = $parentDoc->productos
                    ->where('producto_id', $productoId)
                    ->first();
            }

            foreach ($productoRecepcion->unidadesDerivadas as $unidadRecepcion) {
                $unidadId = $unidadRecepcion->unidad_derivada_inmutable_id;
                $unidadName = $unidadRecepcion->unidadDerivadaInmutable->name;
                $bonificacion = $unidadRecepcion->bonificacion;

                // 1. Cantidad Pedida (original del documento padre)
                $cantidadPedida = 0;
                if ($parentProduct) {
                    if ($recepcion->compra_id) {
                        $unidadCompra = $parentProduct->unidadesDerivadas
                            ->where('unidad_derivada_inmutable_id', $unidadId)
                            ->where('bonificacion', $bonificacion)
                            ->first();
                        $cantidadPedida = $unidadCompra ? (float) $unidadCompra->cantidad : 0;
                    } else {
                        // Flujo OrdenCompra: comparar por nombre de unidad si coincide
                        if ($parentProduct->unidad === $unidadName && !$bonificacion) {
                            $cantidadPedida = (float) $parentProduct->cantidad;
                        }
                    }
                }

                // 2 & 3. Cantidad Recepcionada y Finalizada (acumulados)
                $cantidadRecepcionada = 0;
                $cantidadFinalizada = 0;

                foreach ($todasRecepciones as $otraRecepcion) {
                    $productoOtraRecepcion = $otraRecepcion->productosPorAlmacen
                        ->where('producto_almacen_id', $productoAlmacenId)
                        ->first();
                    
                    if ($productoOtraRecepcion) {
                        $unidadOtraRecepcion = $productoOtraRecepcion->unidadesDerivadas
                            ->where('unidad_derivada_inmutable_id', $unidadId)
                            ->where('bonificacion', $bonificacion)
                            ->first();
                        
                        if ($unidadOtraRecepcion) {
                            if ($otraRecepcion->es_finalizacion) {
                                $cantidadFinalizada += (float) $unidadOtraRecepcion->cantidad;
                            } else {
                                $cantidadRecepcionada += (float) $unidadOtraRecepcion->cantidad;
                            }
                        }
                    }
                }

                // Agregar los campos calculados dinámicamente
                $unidadRecepcion->setAttribute('cantidad_pedida', $cantidadPedida);
                $unidadRecepcion->setAttribute('cantidad_recepcionada', $cantidadRecepcionada);
                $unidadRecepcion->setAttribute('cantidad_finalizada', $cantidadFinalizada);
            }
        }
    }

    /**
     * Crear una recepción de almacén
     * POST /api/recepciones-almacen
     *
     * Replica exactamente la lógica de createRecepcionAlmacen de Prisma:
     * 1. Crear RecepcionAlmacen con número autoincremental
     * 2. Crear ProductoAlmacenRecepcion por cada producto
     * 3. Crear UnidadDerivadaInmutableRecepcion por cada unidad derivada
     * 4. Decrementar cantidad_pendiente en UnidadDerivadaInmutableCompra
     * 5. Incrementar stock_fraccion en ProductoAlmacen
     * 6. Actualizar costo si stock era <= 0
     * 7. Crear historial de stock
     * 8. Si no quedan pendientes, marcar compra como Procesado
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'compra_id' => 'required_without:orden_compra_id|nullable|string|exists:compra,id',
            'orden_compra_id' => 'required_without:compra_id|nullable|integer|exists:ordenes_compra,id',
            'user_id' => 'required|string|exists:user,id',
            'fecha' => 'required|date',
            'observaciones' => 'nullable|string',
            'transportista_razon_social' => 'nullable|string|max:191',
            'transportista_ruc' => 'nullable|string|max:191',
            'transportista_placa' => 'nullable|string|max:191',
            'transportista_licencia' => 'nullable|string|max:191',
            'transportista_dni' => 'nullable|string|max:191',
            'transportista_name' => 'nullable|string|max:191',
            'transportista_guia_remision' => 'nullable|string|max:191',
            'productos_por_almacen' => 'required|array|min:1',
            'productos_por_almacen.*.producto_id' => 'required|integer',
            'productos_por_almacen.*.almacen_id' => 'required|integer',
            'productos_por_almacen.*.costo' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas' => 'required|array|min:1',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_name' => 'required|string',
            'productos_por_almacen.*.unidades_derivadas.*.factor' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad' => 'required|numeric|min:0.001',
            'productos_por_almacen.*.unidades_derivadas.*.lote' => 'nullable|string',
            'productos_por_almacen.*.unidades_derivadas.*.vencimiento' => 'nullable|date',
            'productos_por_almacen.*.unidades_derivadas.*.flete' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.bonificacion' => 'nullable|boolean',
        ], [
            'compra_id.required_without' => 'Debe proporcionar una Compra o una Orden de Compra',
            'orden_compra_id.required_without' => 'Debe proporcionar una Compra o una Orden de Compra',
            'user_id.required' => 'El usuario es requerido',
            'productos_por_almacen.required' => 'Debe incluir al menos un producto',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request) {
                // 1. Obtener el siguiente número de recepción
                $ultimoNumero = RecepcionAlmacen::max('numero') ?? 0;
                $numero = $ultimoNumero + 1;

                // Guardar el estado anterior de la compra (si existe)
                $estadoCompraAnterior = null;
                if ($request->compra_id) {
                    $compraActual = DB::table('compra')->where('id', $request->compra_id)->first();
                    if ($compraActual) {
                        $estadoCompraAnterior = $compraActual->estado_de_compra;

                        // Una compra "en espera" no está formalmente registrada, por lo
                        // que no puede recepcionarse hasta que se confirme (Creado).
                        if ($estadoCompraAnterior === EstadoDeCompraDefinitiva::EnEspera->value) {
                            throw new \Exception('No se puede recepcionar una compra en espera. Primero debe registrarla.');
                        }
                    }
                }

                // 2. Crear la recepción de almacén
                $recepcion = RecepcionAlmacen::create([
                    'numero' => $numero,
                    'compra_id' => $request->compra_id,
                    'orden_compra_id' => $request->orden_compra_id,
                    'user_id' => $request->user_id,
                    'fecha' => $request->fecha,
                    'observaciones' => $request->observaciones,
                    'transportista_razon_social' => $request->transportista_razon_social,
                    'transportista_ruc' => $request->transportista_ruc,
                    'transportista_placa' => $request->transportista_placa,
                    'transportista_licencia' => $request->transportista_licencia,
                    'transportista_dni' => $request->transportista_dni,
                    'transportista_name' => $request->transportista_name,
                    'transportista_guia_remision' => $request->transportista_guia_remision,
                    'estado' => true,
                    'estado_compra_anterior' => $estadoCompraAnterior,
                ]);

                // 3. Procesar cada producto
                $stocksAnteriores = []; // Guardar stocks anteriores para kardex
                $costosAnteriores = []; // Guardar costos anteriores para kardex
                // Resolver cada UnidadDerivadaInmutable una sola vez por nombre y reutilizarla
                // en el loop de stock y en el de kardex (evita firstOrCreate duplicado por unidad).
                $unidadesInmutablesCache = [];
                // Acumular almacenes a invalidar para hacerlo una sola vez al final
                // (varios productos suelen entrar al mismo almacén receptor).
                $almacenesAInvalidar = [];

                foreach ($request->productos_por_almacen as $productoData) {
                    $productoId = $productoData['producto_id'];
                    $almacenId = $productoData['almacen_id'];
                    $costo = $productoData['costo'];

                    // Obtener producto_almacen_id
                    $productoAlmacen = \App\Models\ProductoAlmacen::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenId)
                        ->first();

                    if (!$productoAlmacen) {
                        throw new \Exception("No se encontró el producto {$productoId} en el almacén {$almacenId}");
                    }

                    // Crear ProductoAlmacenRecepcion
                    $productoRecepcion = \App\Models\ProductoAlmacenRecepcion::create([
                        'recepcion_id' => $recepcion->id,
                        'costo' => $costo,
                        'producto_almacen_id' => $productoAlmacen->id,
                    ]);

                    // Referencia para decrementar cantidad_pendiente (Compra o OrdenCompra)
                    $refProductoDoc = null;
                    if ($request->compra_id) {
                        // La línea de la compra apunta al producto_almacen del ALMACÉN DE LA COMPRA.
                        // Al recepcionar en otro almacén, $productoAlmacen->id es el del almacén
                        // receptor y no coincide; por eso buscamos la línea por PRODUCTO, no por
                        // producto_almacen_id, para decrementar el pendiente correctamente.
                        $refProductoDoc = \App\Models\ProductoAlmacenCompra::where('compra_id', $request->compra_id)
                            ->whereHas('productoAlmacen', function ($q) use ($productoId) {
                                $q->where('producto_id', $productoId);
                            })
                            ->first();
                    } else if ($request->orden_compra_id) {
                        $refProductoDoc = \App\Models\OrdenCompraProducto::where('orden_compra_id', $request->orden_compra_id)
                            ->where('producto_id', $productoId)
                            ->first();
                    }

                    // Stock actual antes de procesar
                    $stockBase = (float) $productoAlmacen->stock_fraccion;
                    $acumulado = 0;
                    $sumaFletes = 0; // Acumula el flete de las líneas para prorratearlo en el costo

                    // 4. Procesar cada unidad derivada
                    foreach ($productoData['unidades_derivadas'] as $udData) {
                        $unidadInmutable = $unidadesInmutablesCache[$udData['unidad_derivada_name']]
                            ??= \App\Models\UnidadDerivadaInmutable::firstOrCreate(
                                ['name' => $udData['unidad_derivada_name']],
                                ['name' => $udData['unidad_derivada_name']]
                            );

                        $factor = (float) $udData['factor'];
                        $cantidad = (float) $udData['cantidad'];
                        $bonificacion = $udData['bonificacion'] ?? false;
                        $flete = (float) ($udData['flete'] ?? 0);
                        $lote = $udData['lote'] ?? null;
                        $vencimiento = $udData['vencimiento'] ?? null;

                        // Crear UnidadDerivadaInmutableRecepcion
                        $udRecepcion = \App\Models\UnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_id' => $unidadInmutable->id,
                            'producto_almacen_recepcion_id' => $productoRecepcion->id,
                            'factor' => $factor,
                            'cantidad' => $cantidad,
                            'cantidad_restante' => $cantidad,
                            'lote' => $lote,
                            'vencimiento' => $vencimiento,
                            'flete' => $flete,
                            'bonificacion' => $bonificacion,
                        ]);

                        // DECREMENTAR cantidad_pendiente
                        if ($request->compra_id && $refProductoDoc) {
                            \App\Models\UnidadDerivadaInmutableCompra::where('producto_almacen_compra_id', $refProductoDoc->id)
                                ->where('unidad_derivada_inmutable_id', $unidadInmutable->id)
                                ->where('bonificacion', $bonificacion)
                                ->decrement('cantidad_pendiente', $cantidad);

                            // Si la compra viene de una OC, buscar el producto de la OC y actualizar su pendiente
                            $compraActual = DB::table('compra')->where('id', $request->compra_id)->first();
                            if ($compraActual && $compraActual->orden_compra_id) {
                                \App\Models\OrdenCompraProducto::where('orden_compra_id', $compraActual->orden_compra_id)
                                    ->where('producto_id', $productoId)
                                    ->decrement('cantidad_pendiente', $cantidad);
                            }
                        } else if ($request->orden_compra_id && $refProductoDoc) {
                            // En OrdenCompraProducto, cantidad_pendiente está en la misma tabla del producto
                            // Validar que la unidad coincida (Orden de Compra simplificada asume una unidad principal)
                            if ($refProductoDoc->unidad === $udData['unidad_derivada_name']) {
                                $refProductoDoc->decrement('cantidad_pendiente', $cantidad);
                            }
                        }

                        // Crear historial de stock
                        $stockInicial = $stockBase + $acumulado;
                        $cantidadTotal = $cantidad * $factor;

                        // Acumular flete solo de líneas NO bonificación (las bonificaciones no inflan el costo)
                        if (!$bonificacion) {
                            $sumaFletes += $flete;
                        }

                        \App\Models\HistorialUnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_recepcion_id' => $udRecepcion->id,
                            'stock_anterior' => $stockInicial,
                            'stock_nuevo' => $stockInicial + $cantidadTotal,
                        ]);

                        $acumulado += $cantidadTotal;
                    }

                    // Actualizar stock y costo del producto
                    $cantidadTotalProducto = $acumulado;

                    $productoAlmacenModel = \App\Models\ProductoAlmacen::find($productoAlmacen->id);
                    if ($productoAlmacenModel) {
                        // Guardar el stock anterior ANTES de incrementar para el kardex
                        $stockAntesDeRecepcion = $productoAlmacenModel->stock_fraccion;
                        $costoAnterior = $productoAlmacenModel->costo; // Guardar costo anterior ANTES de actualizar
                        
                        // Guardar en array para usar después en kardex
                        $stocksAnteriores["{$productoId}_{$almacenId}"] = $stockAntesDeRecepcion;
                        $costosAnteriores["{$productoId}_{$almacenId}"] = $costoAnterior;
                        
                        // Verificar si es bonificación
                        $todasBonificacion = collect($productoData['unidades_derivadas'])
                            ->every(fn($ud) => $ud['bonificacion'] ?? false);
                        
                        // Costo REAL = costo crudo + flete prorrateado de esta compra.
                        //
                        // El flete se prorratea sobre la cantidad TOTAL de la línea de la
                        // COMPRA, no sobre lo recibido: en recepciones PARCIALES el form
                        // manda el flete total de la línea cada vez, y dividirlo solo entre
                        // lo recibido lo duplicaba e inflaba (ej. compra de 7 con flete 7 =
                        // +1/u real; recibir 3 daba +2.33/u y luego 4 daba +1.75/u → flete
                        // cobrado 14 en vez de 7). Sin compra asociada (OC/directa) se
                        // mantiene el prorrateo sobre lo recibido.
                        $baseProrrateoFlete = $cantidadTotalProducto;
                        if ($request->compra_id && $refProductoDoc) {
                            $totalLineaCompra = (float) \App\Models\UnidadDerivadaInmutableCompra::where('producto_almacen_compra_id', $refProductoDoc->id)
                                ->where('bonificacion', false)
                                ->get()
                                ->sum(fn ($u) => (float) $u->cantidad * (float) $u->factor);
                            if ($totalLineaCompra > 0) {
                                $baseProrrateoFlete = $totalLineaCompra;
                            }
                        }
                        $fleteUnitario = ($baseProrrateoFlete > 0 && $sumaFletes > 0)
                            ? ($sumaFletes / $baseProrrateoFlete)
                            : 0;
                        $costoReal = (float) $costo + $fleteUnitario;

                        $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                        if (!$todasBonificacion && $costo > 0) {
                            // Lote con el costo real (crudo + flete). Si es una recepción
                            // PARCIAL de una compra ya recepcionada antes, registrarLote
                            // SUMA al lote existente de esa compra (misma fila/costo) en
                            // vez de crear otra fila.
                            $loteService->registrarLote(
                                $productoAlmacenModel,
                                $costoReal,
                                $cantidadTotalProducto,
                                ['recepcion_id' => $recepcion->id, 'compra_id' => $request->compra_id]
                            );
                        } else {
                            // Bonificación: entra a costo 0 (no infla el costo del inventario).
                            $loteService->registrarLote(
                                $productoAlmacenModel,
                                0,
                                $cantidadTotalProducto,
                                ['recepcion_id' => $recepcion->id, 'compra_id' => $request->compra_id]
                            );
                        }

                        // registrarLote ya guardó el producto con los derivados al día.
                        $productoAlmacenModel->refresh();
                        
                        \Illuminate\Support\Facades\Log::info(
                            "Stock actualizado en store (Recepcion): AlmacenProducto {$productoAlmacen->id}, " .
                            "Incremento: {$cantidadTotalProducto}, Nuevo Stock: {$productoAlmacenModel->stock_fraccion}, " .
                            "Costo Anterior: {$costoAnterior}, Costo Actual: {$productoAlmacenModel->costo_actual}, " .
                            "Stock Costo Anterior: {$productoAlmacenModel->stock_costo_anterior}, " .
                            "Stock Costo Actual: {$productoAlmacenModel->stock_costo_actual}"
                        );
                    }

                    // Diferir la invalidación de caché: se hace una sola vez por almacén al final.
                    $almacenesAInvalidar[$productoAlmacen->almacen_id] = true;
                }

                // 5. Registrar en kardex inventario (ENTRADA - con impacto en stock)
                $kardexInventarioService = app(\App\Services\Kardex\KardexInventarioService::class);
                
                foreach ($request->productos_por_almacen as $productoData) {
                    $productoId = $productoData['producto_id'];
                    $almacenId = $productoData['almacen_id'];
                    $costo = $productoData['costo'];

                    $productoAlmacen = \App\Models\ProductoAlmacen::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenId)
                        ->first();

                    if ($productoAlmacen) {
                        // Recargar para obtener el stock actualizado después de los incrementos
                        $productoAlmacen->refresh();
                        
                        // El stock anterior para kardex es el stock ANTES de esta recepción
                        // que ya fue guardado en $stocksAnteriores
                        $stockAnteriorRecepcion = $stocksAnteriores["{$productoId}_{$almacenId}"] ?? null;
                        
                        $acumuladoKardex = 0; // fracciones acumuladas para stock_anterior correcto por unidad

                        foreach ($productoData['unidades_derivadas'] as $udData) {
                            $unidadInmutable = $unidadesInmutablesCache[$udData['unidad_derivada_name']]
                                ??= \App\Models\UnidadDerivadaInmutable::firstOrCreate(
                                    ['name' => $udData['unidad_derivada_name']],
                                    ['name' => $udData['unidad_derivada_name']]
                                );

                            // Crear objeto temporal con la estructura esperada por registrarRecepcion
                            $unidadTemporal = (object) [
                                'unidadDerivadaInmutable' => $unidadInmutable,
                                'cantidad' => (float) $udData['cantidad'],
                                'factor' => (float) $udData['factor'],
                            ];

                            $stockOverride = $stockAnteriorRecepcion !== null
                                ? $stockAnteriorRecepcion + $acumuladoKardex
                                : null;

                            $kardexInventarioService->registrarRecepcion(
                                $recepcion,
                                $productoAlmacen,
                                $unidadTemporal,
                                $costo,
                                2,
                                $stockOverride,
                                $costosAnteriores["{$productoId}_{$almacenId}"] ?? null
                            );

                            $acumuladoKardex += (float) $udData['cantidad'] * (float) $udData['factor'];
                        }
                    }
                }

                // 6. Invalidar caché de productos una sola vez por almacén afectado
                $cacheService = app(\App\Services\Cache\ProductoCacheService::class);
                foreach (array_keys($almacenesAInvalidar) as $almacenIdAfectado) {
                    $cacheService->invalidateProductosAlmacen($almacenIdAfectado);
                }

                // 7. Verificar si quedan productos pendientes
                $existePendiente = false;
                if ($request->compra_id) {
                    $existePendiente = \App\Models\UnidadDerivadaInmutableCompra::whereHas('productoAlmacenCompra', function ($q) use ($request) {
                        $q->where('compra_id', $request->compra_id);
                    })->where('cantidad_pendiente', '>', 0)->exists();

                    if (!$existePendiente) {
                        DB::table('compra')->where('id', $request->compra_id)->update(['estado_de_compra' => EstadoDeCompraDefinitiva::Procesado]);
                        
                        // Si la compra tiene una orden_compra_id, actualizar también la orden
                        $compraActual = DB::table('compra')->where('id', $request->compra_id)->first();
                        if ($compraActual && $compraActual->orden_compra_id) {
                            \App\Models\OrdenCompra::where('id', $compraActual->orden_compra_id)
                                ->update(['estado' => EstadoDeCompra::Completada]);
                        }
                    }
                } else if ($request->orden_compra_id) {
                    $existePendiente = \App\Models\OrdenCompraProducto::where('orden_compra_id', $request->orden_compra_id)
                        ->where('cantidad_pendiente', '>', 0)
                        ->exists();

                    if (!$existePendiente) {
                        \App\Models\OrdenCompra::where('id', $request->orden_compra_id)->update(['estado' => EstadoDeCompra::Completada]);
                    }
                }

                return $recepcion;
            });

            $result->load([
                'compra.proveedor',
                'compra.ordenCompra:id,codigo,estado',
                'ordenCompra.proveedor',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user',
            ]);

            return response()->json([
                'data' => $result,
                'message' => 'Recepción de almacén creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'Error al crear la recepción: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Finalizar recepción de una compra
     * POST /api/recepciones-almacen/finalizar-compra/{compra_id}
     * 
     * Crea automáticamente una recepción con todos los productos pendientes
     * y marca la compra como Procesado
     */
    public function finalizarCompra(Request $request, $compra_id)
    {
        $validator = Validator::make($request->all(), [
            'motivo_finalizacion' => 'required|string|min:10',
        ], [
            'motivo_finalizacion.required' => 'El motivo de finalización es requerido',
            'motivo_finalizacion.min' => 'El motivo debe tener al menos 10 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        try {
            $compra = \App\Models\Compra::with([
                'productosPorAlmacen.productoAlmacen',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable'
            ])->findOrFail($compra_id);

            // (Removido) Ya no es obligatorio tener recepciones previas para finalizar

            // Verificar que haya productos pendientes
            $hayPendientes = false;
            $productosPendientes = [];
            
            foreach ($compra->productosPorAlmacen as $producto) {
                foreach ($producto->unidadesDerivadas as $unidad) {
                    if ($unidad->cantidad_pendiente > 0) {
                        $hayPendientes = true;
                        $productosPendientes[] = [
                            'producto' => $producto->productoAlmacen->producto->name ?? 'Producto',
                            'unidad' => $unidad->unidadDerivadaInmutable->name ?? 'UND',
                            'cantidad_pendiente' => $unidad->cantidad_pendiente,
                        ];
                    }
                }
            }

            if (!$hayPendientes) {
                return response()->json([
                    'error' => ['message' => 'No hay productos pendientes para recepcionar']
                ], 400);
            }

            $result = DB::transaction(function () use ($compra, $request) {
                // Capturar el estado actual de la compra ANTES de finalizar,
                // para poder restaurarlo correctamente si se deshace.
                $estadoCompraAnterior = $compra->estado_de_compra;

                // Crear recepción automática con productos pendientes
                $ultimoNumero = RecepcionAlmacen::max('numero') ?? 0;
                $numero = $ultimoNumero + 1;

                $recepcion = RecepcionAlmacen::create([
                    'numero' => $numero,
                    'compra_id' => $compra->id,
                    'orden_compra_id' => $compra->orden_compra_id,
                    'user_id' => auth()->id() ?? $compra->user_id,
                    'fecha' => now(),
                    'observaciones' => 'Recepción automática - Finalización de compra con productos faltantes (NO RECIBIDOS)',
                    'motivo_finalizacion' => $request->motivo_finalizacion,
                    'fecha_finalizacion' => now(),
                    'es_finalizacion' => true,
                    'estado_compra_anterior' => $estadoCompraAnterior,
                    'estado' => true,
                ]);

                // Procesar cada producto con cantidad pendiente
                foreach ($compra->productosPorAlmacen as $productoCompra) {
                    $productoAlmacen = $productoCompra->productoAlmacen;
                    $tieneUnidadesPendientes = false;

                    // Verificar si tiene unidades pendientes
                    foreach ($productoCompra->unidadesDerivadas as $unidad) {
                        if ($unidad->cantidad_pendiente > 0) {
                            $tieneUnidadesPendientes = true;
                            break;
                        }
                    }

                    if (!$tieneUnidadesPendientes) {
                        continue;
                    }

                    // Crear ProductoAlmacenRecepcion
                    $productoRecepcion = \App\Models\ProductoAlmacenRecepcion::create([
                        'recepcion_id' => $recepcion->id,
                        'costo' => $productoCompra->costo,
                        'producto_almacen_id' => $productoAlmacen->id,
                    ]);

                    $stockBase = (float) $productoAlmacen->stock_fraccion;
                    $acumulado = 0;

                    // Procesar cada unidad derivada pendiente
                    foreach ($productoCompra->unidadesDerivadas as $unidadCompra) {
                        $cantidadPendiente = (float) $unidadCompra->cantidad_pendiente;

                        if ($cantidadPendiente <= 0) {
                            continue;
                        }

                        $factor = (float) $unidadCompra->factor;
                        $bonificacion = $unidadCompra->bonificacion ?? false;

                        // Crear UnidadDerivadaInmutableRecepcion
                        $udRecepcion = \App\Models\UnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_id' => $unidadCompra->unidad_derivada_inmutable_id,
                            'producto_almacen_recepcion_id' => $productoRecepcion->id,
                            'factor' => $factor,
                            'cantidad' => $cantidadPendiente,
                            'cantidad_restante' => 0, // Finalización = NO recibido, por lo tanto 0 disponible para venta
                            'lote' => $unidadCompra->lote,
                            'vencimiento' => $unidadCompra->vencimiento,
                            'flete' => $unidadCompra->flete ?? 0,
                            'bonificacion' => $bonificacion,
                        ]);

                        // Actualizar cantidad_pendiente en la COMPRA a 0
                        $unidadCompra->update(['cantidad_pendiente' => 0]);

                        // Actualizar cantidad_pendiente en la ORDEN DE COMPRA vinculada (si existe)
                        if ($compra->orden_compra_id) {
                            \App\Models\OrdenCompraProducto::where('orden_compra_id', $compra->orden_compra_id)
                                ->where('producto_id', $productoAlmacen->producto_id)
                                ->where('unidad', $unidadCompra->unidadDerivadaInmutable->name ?? '')
                                ->update(['cantidad_pendiente' => 0]);
                        }

                        // Crear historial con movimiento 0
                        $stockInicial = $stockBase + $acumulado;
                        \App\Models\HistorialUnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_recepcion_id' => $udRecepcion->id,
                            'stock_anterior' => $stockInicial,
                            'stock_nuevo' => $stockInicial, // Movimiento 0 (Solo queda como registro)
                        ]);
                    }

                    // NOTA: NO incrementamos el stock_fraccion ni actualizamos costos en el Almacen
                    // porque esta es una recepción de 'Finalización' (productos NO recibidos).
                    
                    app(\App\Services\Cache\ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);
                }

                // Marcar compra como Procesado
                $compra->update(['estado_de_compra' => \App\Enums\EstadoDeCompraDefinitiva::Procesado]);

                // Si la compra tiene orden_compra_id, cerrar la orden si ya no quedan pendientes globales
                if ($compra->orden_compra_id) {
                    $todasCerradas = !\App\Models\OrdenCompraProducto::where('orden_compra_id', $compra->orden_compra_id)
                        ->where('cantidad_pendiente', '>', 0)
                        ->exists();

                    if ($todasCerradas) {
                        \App\Models\OrdenCompra::where('id', $compra->orden_compra_id)
                            ->update(['estado' => \App\Enums\EstadoDeCompra::Completada]);
                    }
                }

                return $recepcion;
            });

            $result->load([
                'compra.proveedor',
                'compra.ordenCompra:id,codigo,estado',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user',
            ]);

            return response()->json([
                'data' => $result,
                'productos_finalizados' => $productosPendientes,
                'message' => 'Recepción finalizada exitosamente. Se creó una recepción automática con los productos faltantes.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'Error al finalizar la recepción: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Eliminar (anular) una recepción de almacén
     * DELETE /api/recepciones-almacen/{id}
     *
     * Replica la lógica de eliminarRecepcionAlmacen de Prisma:
     * 1. Verificar que no tenga unidades usadas en ventas
     * 2. Marcar estado = false
     * 3. Revertir stock (decrementar)
     * 4. Incrementar cantidad_pendiente en compra
     * 5. Marcar compra como Creado
     */
    public function destroy($id)
    {
        $recepcion = RecepcionAlmacen::find($id);

        if (!$recepcion) {
            return response()->json([
                'error' => ['message' => 'Recepción no encontrada']
            ], 404);
        }

        try {
            $result = DB::transaction(function () use ($recepcion) {
                // Nota: se eliminó la validación que bloqueaba la anulación
                // cuando cantidad_restante != cantidad. Esa diferencia también
                // se produce al consumir la recepción para cubrir stock
                // negativo / backorder (no es una venta real), por lo que
                // bloqueaba indebidamente. Se permite deshacer la recepción
                // aunque el stock quede negativo, mientras no haya ventas.

                // 2. Marcar como inactiva Y anulada
                $recepcion->update(['estado' => false, 'anulada' => true]);

                // 3. Revertir stock - obtener productos de la recepción
                $productosRecepcion = ProductoAlmacenRecepcion::where('recepcion_id', $recepcion->id)
                    ->with(['unidadesDerivadas', 'productoAlmacen'])
                    ->get();

                // Mapeo de productos del documento padre (Compra u OrdenCompra).
                // Se mapea por producto_id (no por producto_almacen_id) para que la
                // restauración del pendiente funcione aunque la recepción se haya hecho
                // en un almacén distinto al de la compra.
                $productosDocPadreMap = collect();
                if ($recepcion->compra_id) {
                    $productosDocPadreMap = ProductoAlmacenCompra::where('compra_id', $recepcion->compra_id)
                        ->with('productoAlmacen')
                        ->get()
                        ->keyBy(fn ($pac) => $pac->productoAlmacen->producto_id);
                } else if ($recepcion->orden_compra_id) {
                    $productosDocPadreMap = \App\Models\OrdenCompraProducto::where('orden_compra_id', $recepcion->orden_compra_id)
                        ->get()
                        ->keyBy('producto_id');
                }

                $stocksAnterioresAnulacion = []; // stocks capturados antes de decrementar para kardex correcto

                foreach ($productosRecepcion as $productoRecepcion) {
                    $productoAlmacenId = $productoRecepcion->producto_almacen_id;
                    $productoId = $productoRecepcion->productoAlmacen->producto_id;

                    // Encontrar el producto correspondiente en el documento padre (por producto_id)
                    $productoDocPadre = $productosDocPadreMap->get($productoId);

                    if (!$productoDocPadre) {
                        // Si no se encuentra, logueamos pero continuamos (podría ser un producto eliminado de la compra?)
                        \Illuminate\Support\Facades\Log::warning("No se encontró el producto ID {$productoId} en el documento padre al anular recepción {$recepcion->id}");
                        continue;
                    }

                    $stockBase = (float) $productoRecepcion->productoAlmacen->stock_fraccion;
                    $stocksAnterioresAnulacion[$productoRecepcion->id] = $stockBase; // guardar antes del decremento
                    $acumulado = 0;

                    foreach ($productoRecepcion->unidadesDerivadas as $unidadDerivada) {
                        $factor = (float) $unidadDerivada->factor;
                        $cantidad = (float) $unidadDerivada->cantidad;
                        $cantidadTotal = $cantidad * $factor;

                        // Restaurar cantidad_pendiente en el documento padre
                        if ($recepcion->compra_id) {
                            UnidadDerivadaInmutableCompra::where('producto_almacen_compra_id', $productoDocPadre->id)
                                ->where('unidad_derivada_inmutable_id', $unidadDerivada->unidad_derivada_inmutable_id)
                                ->where('bonificacion', $unidadDerivada->bonificacion)
                                ->increment('cantidad_pendiente', $cantidad);
                            
                            // Si la compra viene de una OC, restaurar también en la OC
                            $compraActual = DB::table('compra')->where('id', $recepcion->compra_id)->first();
                            if ($compraActual && $compraActual->orden_compra_id) {
                                \App\Models\OrdenCompraProducto::where('orden_compra_id', $compraActual->orden_compra_id)
                                    ->where('producto_id', $productoId)
                                    ->increment('cantidad_pendiente', $cantidad);
                            }
                        } else if ($recepcion->orden_compra_id) {
                            // En OrdenCompraProducto, la cantidad_pendiente está en la misma tabla del producto
                            // Solo restauramos si la unidad coincide (flujo simplificado OC)
                            if ($productoDocPadre->unidad === $unidadDerivada->unidadDerivadaInmutable->name) {
                                $productoDocPadre->increment('cantidad_pendiente', $cantidad);
                            }
                        }

                        // NOTA: NO se crea historial de reversión en la recepción.
                        // La recepción debe permanecer intacta como registro histórico:
                        // al anular solo cambia el stock del almacén (decrement más abajo)
                        // y el movimiento de reversión queda registrado en el kardex.

                        $acumulado += $cantidadTotal;
                    }

                    // Revertir el lote creado por esta recepción SOLO si no es finalización.
                    // revertirLotesPorRecepcion le quita a esos lotes su cantidad inicial
                    // (puede quedar negativo si ya se vendió) y recalcula los derivados.
                    if (!$recepcion->es_finalizacion && $acumulado > 0) {
                        $paModel = ProductoAlmacen::find($productoAlmacenId);
                        if ($paModel) {
                            // Pasa compra y cantidad: si el lote está FUSIONADO (varias
                            // recepciones de la misma compra) solo resta lo de ESTA recepción.
                            app(\App\Services\Producto\ProductoLoteService::class)
                                ->revertirLotesPorRecepcion($paModel, $recepcion->id, $recepcion->compra_id, (float) $acumulado);
                            app(ProductoCacheService::class)->invalidateProductosAlmacen($paModel->almacen_id);
                        }
                    }
                }

                // 4. Registrar anulación en kardex inventario
                $kardexInventarioService = app(\App\Services\Kardex\KardexInventarioService::class);

                foreach ($productosRecepcion as $productoRecepcion) {
                    $productoAlmacen = $productoRecepcion->productoAlmacen;
                    $stockAnteriorAnulacion = $stocksAnterioresAnulacion[$productoRecepcion->id] ?? null;
                    $acumuladoKardexAnulacion = 0;

                    foreach ($productoRecepcion->unidadesDerivadas as $unidadDerivada) {
                        // Crear objeto temporal con la estructura esperada
                        $unidadTemporal = (object) [
                            'unidadDerivadaInmutable' => $unidadDerivada->unidadDerivadaInmutable,
                            'cantidad' => (float) $unidadDerivada->cantidad,
                            'factor' => (float) $unidadDerivada->factor,
                        ];

                        // stock_anterior correcto: stock antes del decremento, menos lo ya procesado
                        $stockOverride = $stockAnteriorAnulacion !== null
                            ? $stockAnteriorAnulacion - $acumuladoKardexAnulacion
                            : null;

                        $kardexInventarioService->registrarAnulacionRecepcion(
                            $recepcion,
                            $productoAlmacen,
                            $unidadTemporal,
                            $productoRecepcion->costo,
                            5,
                            $stockOverride
                        );

                        $acumuladoKardexAnulacion += (float) $unidadDerivada->cantidad * (float) $unidadDerivada->factor;
                    }
                }

                // 5. Actualizar estado del documento padre si corresponde
                if ($recepcion->compra_id) {
                    // Restaurar el estado anterior de la compra
                    $estadoARestaurar = $recepcion->estado_compra_anterior ?? 'cr';
                    DB::table('compra')
                        ->where('id', $recepcion->compra_id)
                        ->update(['estado_de_compra' => $estadoARestaurar]);
                } else if ($recepcion->orden_compra_id) {
                    \App\Models\OrdenCompra::where('id', $recepcion->orden_compra_id)
                        ->update(['estado' => \App\Enums\EstadoDeCompra::Completada]); // Sigue completada pero con pendientes
                }

                return $recepcion;
            });

            return response()->json([
                'data' => $result,
                'message' => 'Recepción eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => $e->getMessage()
                ]
            ], $e->getMessage() === 'No se puede eliminar una recepción que ya fue usada en ventas' ? 409 : 500);
        }
    }
}
