<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaStock;
use App\Models\ProductoTransferenciaStock;
use App\Models\ProductoAlmacen;
use App\Models\Ubicacion;
use App\Models\UnidadDerivadaInmutable;
use App\Services\Cache\ProductoCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransferenciaStockController extends Controller
{
    /**
     * Resumen para el dashboard: "Préstamos" (stock que SALE del almacén = origen)
     * vs "Prestés" (stock que ENTRA al almacén = destino), valorizado (cantidad*costo).
     * GET /api/transferencias-stock-resumen-dashboard
     */
    public function resumenDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
        ]);

        $monto = function (string $columnaAlmacen) use ($request): float {
            $q = DB::table('producto_transferencia_stock as pts')
                ->join('transferencia_stock as ts', 'pts.transferencia_stock_id', '=', 'ts.id')
                ->where('ts.estado', 1);

            if ($request->filled('almacen_id')) {
                $q->where("ts.$columnaAlmacen", $request->almacen_id);
            }
            if ($request->filled('desde')) {
                $q->whereDate('ts.fecha', '>=', $request->desde);
            }
            if ($request->filled('hasta')) {
                $q->whereDate('ts.fecha', '<=', $request->hasta);
            }

            return (float) $q->selectRaw('COALESCE(SUM(pts.cantidad * pts.costo), 0) as total')->value('total');
        };

        return response()->json(['data' => [
            ['label' => 'Préstamos', 'value' => round($monto('almacen_origen_id'), 2)],
            ['label' => 'Prestés', 'value' => round($monto('almacen_destino_id'), 2)],
        ]]);
    }

    /**
     * GET /api/transferencias-stock
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'nullable|integer|exists:almacen,id',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = TransferenciaStock::with([
            'almacenOrigen:id,name',
            'almacenDestino:id,name',
            'user:id,name',
            'productos' => function ($q) {
                $q->with([
                    'productoAlmacenOrigen.producto:id,name,cod_producto',
                    'unidadDerivadaInmutable:id,name',
                ]);
            },
        ]);

        // Filtro por almacén (origen o destino)
        if ($request->filled('almacen_id')) {
            $almacenId = $request->almacen_id;
            $query->where(function ($q) use ($almacenId) {
                $q->where('almacen_origen_id', $almacenId)
                  ->orWhere('almacen_destino_id', $almacenId);
            });
        }

        // Filtro por fechas
        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->hasta . ' 23:59:59');
        }

        $perPage = $request->per_page ?? 50;

        $result = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($result);
    }

    /**
     * POST /api/transferencias-stock
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'almacen_origen_id' => 'required|integer|exists:almacen,id',
            'almacen_destino_id' => 'required|integer|exists:almacen,id|different:almacen_origen_id',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer|exists:unidadderivada,id',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'fecha' => 'nullable|date',
            'descripcion' => 'nullable|string|max:500',
        ]);

        try {
        return DB::transaction(function () use ($validated) {
            $almacenOrigenId = $validated['almacen_origen_id'];
            $almacenDestinoId = $validated['almacen_destino_id'];

            // Ubicación destino compartida
            $ubicacionDestino = Ubicacion::firstOrCreate(
                ['almacen_id' => $almacenDestinoId, 'name' => 'OTROS'],
                ['estado' => true]
            );

            // Obtener número secuencial
            $ultimaTransferencia = TransferenciaStock::orderBy('numero', 'desc')->first();
            $numero = $ultimaTransferencia ? $ultimaTransferencia->numero + 1 : 1;

            // Crear cabecera TransferenciaStock
            $transferencia = TransferenciaStock::create([
                'serie' => 1,
                'numero' => $numero,
                'fecha' => $validated['fecha'] ?? now(),
                'almacen_origen_id' => $almacenOrigenId,
                'almacen_destino_id' => $almacenDestinoId,
                'user_id' => Auth::id(),
                'descripcion' => $validated['descripcion'] ?? null,
                'estado' => true,
            ]);

            // Procesar cada producto
            foreach ($validated['productos'] as $item) {
                $productoId = $item['producto_id'];
                $unidadDerivadaId = $item['unidad_derivada_id'];
                $cantidad = (float) $item['cantidad'];

                // Obtener ProductoAlmacen ORIGEN con unidad derivada
                $productoAlmacenOrigen = ProductoAlmacen::where('producto_id', $productoId)
                    ->where('almacen_id', $almacenOrigenId)
                    ->with([
                        'unidadesDerivadas' => function ($q) use ($unidadDerivadaId) {
                            $q->where('unidad_derivada_id', $unidadDerivadaId)
                              ->with('unidadDerivada:id,name');
                        },
                    ])
                    ->firstOrFail();

                $unidadDerivada = $productoAlmacenOrigen->unidadesDerivadas->first();
                if (!$unidadDerivada) {
                    throw new \Exception('Unidad derivada no encontrada para el producto en el almacén origen');
                }

                $unidadDerivadaId = $unidadDerivada->unidad_derivada_id;
                $factor = (float) $unidadDerivada->factor;
                $cantidadFraccion = $factor * $cantidad;

                // Obtener o crear ProductoAlmacen DESTINO
                $productoAlmacenDestino = ProductoAlmacen::firstOrCreate(
                    [
                        'producto_id' => $productoId,
                        'almacen_id' => $almacenDestinoId,
                    ],
                    [
                        'stock_fraccion' => 0,
                        'costo' => $productoAlmacenOrigen->costo,
                        'ubicacion_id' => $ubicacionDestino->id,
                    ]
                );

                // Copiar unidad derivada al destino si no existe
                $udDestino = $productoAlmacenDestino->unidadesDerivadas()
                    ->where('unidad_derivada_id', $unidadDerivadaId)
                    ->first();

                if (!$udDestino) {
                    $productoAlmacenDestino->unidadesDerivadas()->create([
                        'unidad_derivada_id' => $unidadDerivada->unidad_derivada_id,
                        'factor' => $unidadDerivada->factor,
                        'precio_publico' => $unidadDerivada->precio_publico ?? 0,
                        'precio_especial' => $unidadDerivada->precio_especial ?? 0,
                        'precio_minimo' => $unidadDerivada->precio_minimo ?? 0,
                        'precio_ultimo' => $unidadDerivada->precio_ultimo ?? 0,
                    ]);
                }

                // Registrar stock anterior/nuevo
                $stockAnteriorOrigen = (float) $productoAlmacenOrigen->stock_fraccion;
                $stockAnteriorDestino = (float) $productoAlmacenDestino->stock_fraccion;
                $stockNuevoOrigen = $stockAnteriorOrigen - $cantidadFraccion;
                $stockNuevoDestino = $stockAnteriorDestino + $cantidadFraccion;

                // Crear UnidadDerivadaInmutable
                $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                    ['name' => $unidadDerivada->unidadDerivada->name],
                    ['estado' => true],
                );

                // Mover stock vía ledger PEPS: consumir lotes FIFO en el ORIGEN y
                // crear en el DESTINO un lote por cada tramo consumido, con SU
                // costo real. Así el destino recibe los mismos costos (anterior/
                // actual y el detalle del select) que salieron del origen.
                $loteService = app(\App\Services\Producto\ProductoLoteService::class);
                $resConsumo = $loteService->consumirLotes(
                    $productoAlmacenOrigen,
                    $cantidadFraccion,
                    ['tipo' => 'transferencia', 'id' => $transferencia->id]
                );
                foreach ($resConsumo['consumos'] as $tramo) {
                    $loteService->registrarLote(
                        $productoAlmacenDestino,
                        $tramo['costo'],
                        $tramo['cantidad'],
                        ['transferencia_stock_id' => $transferencia->id]
                    );
                }
                $productoAlmacenOrigen->refresh();
                $productoAlmacenDestino->refresh();

                // Crear detalle (costo = costo PEPS real de lo transferido)
                ProductoTransferenciaStock::create([
                    'transferencia_stock_id' => $transferencia->id,
                    'producto_almacen_origen_id' => $productoAlmacenOrigen->id,
                    'producto_almacen_destino_id' => $productoAlmacenDestino->id,
                    'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                    'unidad_derivada_id' => $unidadDerivadaId,
                    'factor' => $factor,
                    'cantidad' => $cantidad,
                    'costo' => $resConsumo['costo_promedio'],
                    'stock_anterior_origen' => $stockAnteriorOrigen,
                    'stock_nuevo_origen' => $stockNuevoOrigen,
                    'stock_anterior_destino' => $stockAnteriorDestino,
                    'stock_nuevo_destino' => $stockNuevoDestino,
                ]);
            }

            // Invalidar cache de ambos almacenes
            $cacheService = app(ProductoCacheService::class);
            $cacheService->invalidateProductosAlmacen($almacenOrigenId);
            $cacheService->invalidateProductosAlmacen($almacenDestinoId);

            // Retornar con relaciones
            $result = TransferenciaStock::with([
                'almacenOrigen:id,name',
                'almacenDestino:id,name',
                'user:id,name',
                'productos' => function ($q) {
                    $q->with([
                        'productoAlmacenOrigen.producto:id,name,cod_producto',
                        'unidadDerivadaInmutable:id,name',
                    ]);
                },
            ])->findOrFail($transferencia->id);

            return response()->json(['data' => $result], 201);
        }, 5);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/transferencias-stock/{id}
     */
    public function show(int $id): JsonResponse
    {
        $transferencia = TransferenciaStock::with([
            'almacenOrigen:id,name',
            'almacenDestino:id,name',
            'user:id,name',
            'productos' => function ($q) {
                $q->with([
                    'productoAlmacenOrigen.producto:id,name,cod_producto',
                    'unidadDerivadaInmutable:id,name',
                ]);
            },
        ])->findOrFail($id);

        return response()->json(['data' => $transferencia]);
    }

    /**
     * PUT /api/transferencias-stock/{id}
     * Revierte el stock original y aplica los nuevos movimientos.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'almacen_origen_id'              => 'required|integer|exists:almacen,id',
            'almacen_destino_id'             => 'required|integer|exists:almacen,id|different:almacen_origen_id',
            'productos'                      => 'required|array|min:1',
            'productos.*.producto_id'        => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer|exists:unidadderivada,id',
            'productos.*.cantidad'           => 'required|numeric|min:0.001',
            'fecha'                          => 'nullable|date',
            'descripcion'                    => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($validated, $id) {
                $transferencia = TransferenciaStock::with('productos')
                    ->where('estado', true)
                    ->findOrFail($id);

                // 1. Revertir stock original vía ledger PEPS (una vez por producto):
                //    devolver a los lotes exactos del origen y quitar del destino
                //    los lotes que la transferencia creó.
                $loteService = app(\App\Services\Producto\ProductoLoteService::class);
                $totalPorOrigen = [];
                $totalPorDestino = [];
                foreach ($transferencia->productos as $detalle) {
                    $cant = (float) $detalle->factor * (float) $detalle->cantidad;
                    $totalPorOrigen[$detalle->producto_almacen_origen_id] =
                        ($totalPorOrigen[$detalle->producto_almacen_origen_id] ?? 0) + $cant;
                    $totalPorDestino[$detalle->producto_almacen_destino_id] =
                        ($totalPorDestino[$detalle->producto_almacen_destino_id] ?? 0) + $cant;
                }
                foreach ($totalPorOrigen as $paId => $totalFr) {
                    $origen = ProductoAlmacen::find($paId);
                    if ($origen) {
                        $loteService->revertirConsumoOReingresar($origen, 'transferencia', $transferencia->id, (float) $totalFr);
                    }
                }
                foreach ($totalPorDestino as $paId => $totalFr) {
                    $destino = ProductoAlmacen::find($paId);
                    if ($destino) {
                        $loteService->revertirLotesPorTransferencia($destino, $transferencia->id, (float) $totalFr);
                    }
                }

                // 2. Borrar detalles anteriores
                $transferencia->productos()->delete();

                // 3. Ubicación destino compartida
                $almacenDestinoId = $validated['almacen_destino_id'];
                $almacenOrigenId  = $validated['almacen_origen_id'];
                $ubicacionDestino = Ubicacion::firstOrCreate(
                    ['almacen_id' => $almacenDestinoId, 'name' => 'OTROS'],
                    ['estado' => true]
                );

                // 4. Aplicar nuevos movimientos
                foreach ($validated['productos'] as $item) {
                    $productoId      = $item['producto_id'];
                    $unidadDerivadaId = $item['unidad_derivada_id'];
                    $cantidad        = (float) $item['cantidad'];

                    $productoAlmacenOrigen = ProductoAlmacen::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenOrigenId)
                        ->with([
                            'unidadesDerivadas' => fn($q) => $q
                                ->where('unidad_derivada_id', $unidadDerivadaId)
                                ->with('unidadDerivada:id,name'),
                        ])
                        ->firstOrFail();

                    $unidadDerivada = $productoAlmacenOrigen->unidadesDerivadas->first();
                    if (!$unidadDerivada) {
                        throw new \Exception('Unidad derivada no encontrada para el producto en el almacén origen');
                    }

                    $factor          = (float) $unidadDerivada->factor;
                    $cantidadFraccion = $factor * $cantidad;

                    $productoAlmacenDestino = ProductoAlmacen::firstOrCreate(
                        ['producto_id' => $productoId, 'almacen_id' => $almacenDestinoId],
                        ['stock_fraccion' => 0, 'costo' => $productoAlmacenOrigen->costo, 'ubicacion_id' => $ubicacionDestino->id]
                    );

                    // Copiar unidad derivada al destino si no existe
                    if (!$productoAlmacenDestino->unidadesDerivadas()->where('unidad_derivada_id', $unidadDerivadaId)->exists()) {
                        $productoAlmacenDestino->unidadesDerivadas()->create([
                            'unidad_derivada_id'  => $unidadDerivada->unidad_derivada_id,
                            'factor'              => $unidadDerivada->factor,
                            'precio_publico'      => $unidadDerivada->precio_publico ?? 0,
                            'precio_especial'     => $unidadDerivada->precio_especial ?? 0,
                            'precio_minimo'       => $unidadDerivada->precio_minimo ?? 0,
                            'precio_ultimo'       => $unidadDerivada->precio_ultimo ?? 0,
                        ]);
                    }

                    $stockAnteriorOrigen  = (float) $productoAlmacenOrigen->stock_fraccion;
                    $stockAnteriorDestino = (float) $productoAlmacenDestino->stock_fraccion;
                    $stockNuevoOrigen     = $stockAnteriorOrigen  - $cantidadFraccion;
                    $stockNuevoDestino    = $stockAnteriorDestino + $cantidadFraccion;

                    $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                        ['name' => $unidadDerivada->unidadDerivada->name],
                        ['estado' => true]
                    );

                    // Mover stock vía ledger PEPS (igual que store): consumir lotes
                    // FIFO en el origen y crear los mismos lotes en el destino.
                    $resConsumo = $loteService->consumirLotes(
                        $productoAlmacenOrigen,
                        $cantidadFraccion,
                        ['tipo' => 'transferencia', 'id' => $transferencia->id]
                    );
                    foreach ($resConsumo['consumos'] as $tramo) {
                        $loteService->registrarLote(
                            $productoAlmacenDestino,
                            $tramo['costo'],
                            $tramo['cantidad'],
                            ['transferencia_stock_id' => $transferencia->id]
                        );
                    }
                    $productoAlmacenOrigen->refresh();
                    $productoAlmacenDestino->refresh();

                    ProductoTransferenciaStock::create([
                        'transferencia_stock_id'    => $transferencia->id,
                        'producto_almacen_origen_id'  => $productoAlmacenOrigen->id,
                        'producto_almacen_destino_id' => $productoAlmacenDestino->id,
                        'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                        'unidad_derivada_id'          => $unidadDerivadaId,
                        'factor'                      => $factor,
                        'cantidad'                    => $cantidad,
                        'costo'                       => $resConsumo['costo_promedio'],
                        'stock_anterior_origen'       => $stockAnteriorOrigen,
                        'stock_nuevo_origen'          => $stockNuevoOrigen,
                        'stock_anterior_destino'      => $stockAnteriorDestino,
                        'stock_nuevo_destino'         => $stockNuevoDestino,
                    ]);
                }

                // 5. Actualizar cabecera
                $transferencia->update([
                    'almacen_origen_id'  => $almacenOrigenId,
                    'almacen_destino_id' => $almacenDestinoId,
                    'fecha'              => $validated['fecha'] ?? $transferencia->fecha,
                    'descripcion'        => $validated['descripcion'] ?? null,
                ]);

                // 6. Invalidar cache
                $cacheService = app(ProductoCacheService::class);
                $cacheService->invalidateProductosAlmacen($almacenOrigenId);
                $cacheService->invalidateProductosAlmacen($almacenDestinoId);

                $result = TransferenciaStock::with([
                    'almacenOrigen:id,name',
                    'almacenDestino:id,name',
                    'user:id,name',
                    'productos.productoAlmacenOrigen.producto:id,name,cod_producto',
                    'productos.unidadDerivadaInmutable:id,name',
                ])->findOrFail($transferencia->id);

                return response()->json(['data' => $result]);
            }, 5);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/transferencias-stock/{id} (anular)
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $transferencia = TransferenciaStock::with('productos')
                ->where('estado', true)
                ->findOrFail($id);

            // Totales por producto (una transferencia podría tener 2 líneas del
            // mismo producto): validar y revertir UNA vez por producto_almacen.
            $totalPorOrigen = [];
            $totalPorDestino = [];
            foreach ($transferencia->productos as $detalle) {
                $cantidadFraccion = (float) $detalle->factor * (float) $detalle->cantidad;
                $totalPorOrigen[$detalle->producto_almacen_origen_id] =
                    ($totalPorOrigen[$detalle->producto_almacen_origen_id] ?? 0) + $cantidadFraccion;
                $totalPorDestino[$detalle->producto_almacen_destino_id] =
                    ($totalPorDestino[$detalle->producto_almacen_destino_id] ?? 0) + $cantidadFraccion;
            }

            // Validar que el destino tenga stock suficiente para revertir
            foreach ($totalPorDestino as $paDestinoId => $totalFr) {
                $paDestino = ProductoAlmacen::find($paDestinoId);
                if ($paDestino && (float) $paDestino->stock_fraccion < $totalFr) {
                    return response()->json([
                        'message' => 'No se puede anular: el almacén destino ya no tiene stock suficiente para revertir',
                    ], 400);
                }
            }

            // Revertir vía ledger PEPS: devolver el stock a los lotes EXACTOS del
            // origen (consumo registrado; fallback para transferencias previas al
            // ledger) y quitar del destino los lotes que esta transferencia creó.
            $loteService = app(\App\Services\Producto\ProductoLoteService::class);
            foreach ($totalPorOrigen as $paOrigenId => $totalFr) {
                $paOrigen = ProductoAlmacen::find($paOrigenId);
                if ($paOrigen) {
                    $loteService->revertirConsumoOReingresar($paOrigen, 'transferencia', $transferencia->id, (float) $totalFr);
                }
            }
            foreach ($totalPorDestino as $paDestinoId => $totalFr) {
                $paDestino = ProductoAlmacen::find($paDestinoId);
                if ($paDestino) {
                    $loteService->revertirLotesPorTransferencia($paDestino, $transferencia->id, (float) $totalFr);
                }
            }

            $transferencia->update(['estado' => false]);

            // Invalidar cache
            $cacheService = app(ProductoCacheService::class);
            $cacheService->invalidateProductosAlmacen($transferencia->almacen_origen_id);
            $cacheService->invalidateProductosAlmacen($transferencia->almacen_destino_id);

            return response()->json(['message' => 'Transferencia anulada correctamente']);
        }, 5);
    }
}
