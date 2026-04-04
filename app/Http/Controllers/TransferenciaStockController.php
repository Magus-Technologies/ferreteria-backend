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

                $factor = (float) $unidadDerivada->factor;
                $cantidadFraccion = $factor * $cantidad;

                // Validar stock suficiente en origen
                if ((float) $productoAlmacenOrigen->stock_fraccion < $cantidadFraccion) {
                    throw new \Exception('Stock insuficiente para el producto: ' . ($productoAlmacenOrigen->producto->name ?? $productoId) . '. Stock actual: ' . $productoAlmacenOrigen->stock_fraccion);
                }

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

                // Crear detalle
                ProductoTransferenciaStock::create([
                    'transferencia_stock_id' => $transferencia->id,
                    'producto_almacen_origen_id' => $productoAlmacenOrigen->id,
                    'producto_almacen_destino_id' => $productoAlmacenDestino->id,
                    'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                    'factor' => $factor,
                    'cantidad' => $cantidad,
                    'costo' => $productoAlmacenOrigen->costo,
                    'stock_anterior_origen' => $stockAnteriorOrigen,
                    'stock_nuevo_origen' => $stockNuevoOrigen,
                    'stock_anterior_destino' => $stockAnteriorDestino,
                    'stock_nuevo_destino' => $stockNuevoDestino,
                ]);

                // Actualizar stocks
                $productoAlmacenOrigen->update(['stock_fraccion' => $stockNuevoOrigen]);
                $productoAlmacenDestino->update([
                    'stock_fraccion' => $stockNuevoDestino,
                    'costo' => $productoAlmacenOrigen->costo,
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
     * DELETE /api/transferencias-stock/{id} (anular)
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $transferencia = TransferenciaStock::with('productos')
                ->where('estado', true)
                ->findOrFail($id);

            foreach ($transferencia->productos as $detalle) {
                $productoAlmacenOrigen = ProductoAlmacen::find($detalle->producto_almacen_origen_id);
                $productoAlmacenDestino = ProductoAlmacen::find($detalle->producto_almacen_destino_id);

                $cantidadFraccion = (float) $detalle->factor * (float) $detalle->cantidad;

                // Validar que el destino tenga stock suficiente para revertir
                if ($productoAlmacenDestino && (float) $productoAlmacenDestino->stock_fraccion < $cantidadFraccion) {
                    return response()->json([
                        'message' => 'No se puede anular: el almacén destino ya no tiene stock suficiente para revertir',
                    ], 400);
                }

                // Revertir: devolver al origen, quitar del destino
                if ($productoAlmacenOrigen) {
                    $productoAlmacenOrigen->update([
                        'stock_fraccion' => (float) $productoAlmacenOrigen->stock_fraccion + $cantidadFraccion,
                    ]);
                }
                if ($productoAlmacenDestino) {
                    $productoAlmacenDestino->update([
                        'stock_fraccion' => (float) $productoAlmacenDestino->stock_fraccion - $cantidadFraccion,
                    ]);
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
