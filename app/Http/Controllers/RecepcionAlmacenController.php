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
            $query->whereHas('compra', function ($q) use ($request) {
                $q->where('almacen_id', $request->almacen_id);
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

        $query->orderBy('fecha', 'asc');

        $recepciones = $query->limit(100)->get();

        return response()->json(['data' => $this->toSnakeCase($recepciones)]);
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

        return response()->json(['data' => $this->toSnakeCase($recepcion)]);
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
            'compra_id' => 'required|string|exists:compra,id',
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
            'compra_id.required' => 'La compra es requerida',
            'compra_id.exists' => 'La compra no existe',
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

                // 2. Crear la recepción de almacén
                $recepcion = RecepcionAlmacen::create([
                    'numero' => $numero,
                    'compra_id' => $request->compra_id,
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
                ]);

                // 3. Procesar cada producto
                foreach ($request->productos_por_almacen as $productoData) {
                    $productoId = $productoData['producto_id'];
                    $almacenId = $productoData['almacen_id'];
                    $costo = $productoData['costo'];

                    // Obtener producto_almacen_id
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenId)
                        ->first();

                    if (!$productoAlmacen) {
                        throw new \Exception("No se encontró el producto {$productoId} en el almacén {$almacenId}");
                    }

                    // Crear ProductoAlmacenRecepcion
                    $productoRecepcion = ProductoAlmacenRecepcion::create([
                        'recepcion_id' => $recepcion->id,
                        'costo' => $costo,
                        'producto_almacen_id' => $productoAlmacen->id,
                    ]);

                    // Obtener ProductoAlmacenCompra para decrementar cantidad_pendiente
                    $productoCompra = ProductoAlmacenCompra::where('compra_id', $request->compra_id)
                        ->where('producto_almacen_id', $productoAlmacen->id)
                        ->first();

                    if (!$productoCompra) {
                        throw new \Exception("No se encontró el producto de la compra");
                    }

                    // Stock actual antes de procesar
                    $stockBase = (float) $productoAlmacen->stock_fraccion;
                    $acumulado = 0;

                    // 4. Procesar cada unidad derivada
                    foreach ($productoData['unidades_derivadas'] as $udData) {
                        // connectOrCreate: buscar o crear UnidadDerivadaInmutable
                        $unidadInmutable = UnidadDerivadaInmutable::firstOrCreate(
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
                        $udRecepcion = UnidadDerivadaInmutableRecepcion::create([
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

                        // Decrementar cantidad_pendiente en la compra
                        UnidadDerivadaInmutableCompra::where('producto_almacen_compra_id', $productoCompra->id)
                            ->where('unidad_derivada_inmutable_id', $unidadInmutable->id)
                            ->where('bonificacion', $bonificacion)
                            ->decrement('cantidad_pendiente', $cantidad);

                        // Crear historial de stock
                        $stockInicial = $stockBase + $acumulado;
                        $cantidadTotal = $cantidad * $factor;

                        HistorialUnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_recepcion_id' => $udRecepcion->id,
                            'stock_anterior' => $stockInicial,
                            'stock_nuevo' => $stockInicial + $cantidadTotal,
                        ]);

                        $acumulado += $cantidadTotal;
                    }

                    // 5. Actualizar stock y costo del producto
                    $cantidadTotalProducto = $acumulado;

                    // manejoDeCosto: si stock <= 0, actualizar costo
                    $nuevoCosto = null;
                    if ($stockBase <= 0) {
                        // Si todas las unidades son bonificación, costo = 0
                        $todasBonificacion = collect($productoData['unidades_derivadas'])
                            ->every(fn($ud) => $ud['bonificacion'] ?? false);
                        $nuevoCosto = $todasBonificacion ? 0 : $costo;
                    }

                    $updateData = [
                        'stock_fraccion' => DB::raw("stock_fraccion + {$cantidadTotalProducto}"),
                    ];
                    if ($nuevoCosto !== null) {
                        $updateData['costo'] = $nuevoCosto;
                    }

                    ProductoAlmacen::where('id', $productoAlmacen->id)
                        ->update($updateData);
                }

                // 6. Verificar si quedan productos pendientes en la compra
                $existePendiente = UnidadDerivadaInmutableCompra::whereHas('productoAlmacenCompra', function ($q) use ($request) {
                    $q->where('compra_id', $request->compra_id);
                })->where('cantidad_pendiente', '>', 0)->exists();

                // 7. Si no hay pendientes, marcar compra como Procesada
                if (!$existePendiente) {
                    DB::table('compra')
                        ->where('id', $request->compra_id)
                        ->update(['estado_de_compra' => 'pr']); // Procesado = 'pr'
                }

                return $recepcion;
            });

            // Cargar relaciones para la respuesta
            $result->load([
                'compra.proveedor',
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
                // 1. Verificar que no se haya usado en ventas
                $usados = UnidadDerivadaInmutableRecepcion::whereHas('productoAlmacenRecepcion', function ($q) use ($recepcion) {
                    $q->where('recepcion_id', $recepcion->id);
                })->get(['cantidad', 'cantidad_restante']);

                $tieneUsados = $usados->contains(function ($r) {
                    return (float) $r->cantidad_restante !== (float) $r->cantidad;
                });

                if ($tieneUsados) {
                    throw new \Exception('No se puede eliminar una recepción que ya fue usada en ventas');
                }

                // 2. Marcar como inactiva
                $recepcion->update(['estado' => false]);

                // 3. Revertir stock - obtener productos de la recepción
                $productosRecepcion = ProductoAlmacenRecepcion::where('recepcion_id', $recepcion->id)
                    ->with(['unidadesDerivadas', 'productoAlmacen'])
                    ->get();

                // Obtener productos de la compra para revertir cantidad_pendiente
                $productosCompra = ProductoAlmacenCompra::where('compra_id', $recepcion->compra_id)->get();
                $productosCompraMap = $productosCompra->keyBy('producto_almacen_id');

                foreach ($productosRecepcion as $productoRecepcion) {
                    $productoAlmacenId = $productoRecepcion->producto_almacen_id;
                    $productoCompra = $productosCompraMap->get($productoAlmacenId);

                    if (!$productoCompra) {
                        throw new \Exception('No se encontró el producto de la compra');
                    }

                    $stockBase = (float) $productoRecepcion->productoAlmacen->stock_fraccion;
                    $acumulado = 0;

                    foreach ($productoRecepcion->unidadesDerivadas as $unidadDerivada) {
                        $factor = (float) $unidadDerivada->factor;
                        $cantidad = (float) $unidadDerivada->cantidad;
                        $cantidadTotal = $cantidad * $factor;

                        // Incrementar cantidad_pendiente en la compra
                        UnidadDerivadaInmutableCompra::where('producto_almacen_compra_id', $productoCompra->id)
                            ->where('unidad_derivada_inmutable_id', $unidadDerivada->unidad_derivada_inmutable_id)
                            ->where('bonificacion', $unidadDerivada->bonificacion)
                            ->increment('cantidad_pendiente', $cantidad);

                        // Crear historial de reversión
                        $stockInicial = $stockBase - $acumulado;
                        HistorialUnidadDerivadaInmutableRecepcion::create([
                            'unidad_derivada_inmutable_recepcion_id' => $unidadDerivada->id,
                            'stock_anterior' => $stockInicial,
                            'stock_nuevo' => $stockInicial - $cantidadTotal,
                        ]);

                        $acumulado += $cantidadTotal;
                    }

                    // Decrementar stock
                    ProductoAlmacen::where('id', $productoAlmacenId)
                        ->update([
                            'stock_fraccion' => DB::raw("stock_fraccion - {$acumulado}"),
                        ]);
                }

                // 4. Marcar compra como Creado (ya no está completamente recepcionada)
                DB::table('compra')
                    ->where('id', $recepcion->compra_id)
                    ->update(['estado_de_compra' => 'cr']); // Creado = 'cr'

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
