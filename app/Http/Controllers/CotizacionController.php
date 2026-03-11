<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCotizacion;
use App\Models\UnidadDerivadaInmutableCotizacion;
use App\Models\UnidadDerivadaInmutable;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CotizacionController extends Controller
{
    /**
     * Listar todas las cotizaciones
     */
 public function index(Request $request): JsonResponse
{
    $query = Cotizacion::with([
      'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
      'cliente.direcciones',
        'user:id,name',
        'almacen:id,name',
        'productosPorAlmacen.productoAlmacen.producto.marca',
        'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
    ]);

        // Filtros opcionales
        if ($request->has('estado_cotizacion')) {
            $query->where('estado_cotizacion', $request->estado_cotizacion);
        }

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        // Búsqueda por número
        if ($request->has('search')) {
            $query->where('numero', 'like', '%' . $request->search . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $cotizaciones = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($cotizaciones);
    }

    /**
     * Crear una nueva cotización
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer',
            'productos.*.unidad_derivada_factor' => 'required|numeric|min:0',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.precio_venta' => 'required|numeric|min:0',
            'productos.*.recargo' => 'nullable|numeric|min:0',
            'productos.*.descuento_tipo' => 'nullable|in:%,m',
            'productos.*.descuento' => 'nullable|numeric|min:0',
            
            'fecha' => 'required|date',
            'fecha_proforma' => 'nullable|date',
            'tipo_moneda' => 'required|in:s,d',
            'tipo_de_cambio' => 'nullable|numeric|min:0',
            'vigencia_dias' => 'nullable|integer|min:1',
            'fecha_vencimiento' => 'nullable|date',
            
            'vendedor' => 'nullable|string|max:191',
            'forma_de_pago' => 'nullable|string|max:50',
            'ruc_dni' => 'nullable|string|max:20',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
            'tipo_documento' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string',
            'reservar_stock' => 'nullable|boolean',
            
            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        try {
            DB::beginTransaction();

            // Generar ID y número de cotización
            $cotizacionId = 'cot' . Str::random(10);
            $numero = $this->generarNumeroCotizacion();

            // Calcular fecha de vencimiento si no se proporciona
            $vigenciaDias = $validated['vigencia_dias'] ?? 7;
            $fechaVencimiento = $validated['fecha_vencimiento'] ?? 
                now()->addDays($vigenciaDias)->format('Y-m-d H:i:s');

            // Crear la cotización
            $cotizacion = Cotizacion::create([
                'id' => $cotizacionId,
                'numero' => $numero,
                'fecha' => $validated['fecha'],
                'fecha_proforma' => $validated['fecha_proforma'] ?? $validated['fecha'],
                'vigencia_dias' => $vigenciaDias,
                'fecha_vencimiento' => $fechaVencimiento,
                'tipo_moneda' => $validated['tipo_moneda'],
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? 1.0000,
                'observaciones' => $validated['observaciones'] ?? null,
                'estado_cotizacion' => 'pe', // Pendiente
                'reservar_stock' => $validated['reservar_stock'] ?? false,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'ruc_dni' => $validated['ruc_dni'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'user_id' => auth()->id(),
                'vendedor' => $validated['vendedor'] ?? null,
                'forma_de_pago' => $validated['forma_de_pago'] ?? null,
                'almacen_id' => $validated['almacen_id'],
            ]);

            // Procesar productos - Agrupar por producto_id para evitar duplicados
            $productosAgrupados = collect($validated['productos'])->groupBy('producto_id');
            
            foreach ($productosAgrupados as $productoId => $unidadesDelProducto) {
                $this->agregarProductoACotizacion(
                    $cotizacion,
                    $unidadesDelProducto->toArray(),
                    $validated['almacen_id'],
                    $validated['reservar_stock'] ?? false
                );
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $cotizacion->load([
                'cliente',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ]);

            return response()->json([
                'data' => $cotizacion,
                'message' => 'Cotización creada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agregar un producto a la cotización con múltiples unidades derivadas
     */
    private function agregarProductoACotizacion(
        Cotizacion $cotizacion,
        array $unidadesDelProducto,
        int $almacenId,
        bool $reservarStock
    ): void {
        // Tomar el primer elemento para obtener el producto_id
        $primerUnidad = $unidadesDelProducto[0];
        
        // Buscar o crear ProductoAlmacen
        $productoAlmacen = ProductoAlmacen::firstOrCreate(
            [
                'producto_id' => $primerUnidad['producto_id'],
                'almacen_id' => $almacenId,
            ],
            [
                'stock_fraccion' => 0,
                'costo' => 0,
                'ubicacion_id' => 1, // Ubicación por defecto
            ]
        );

        // Crear UN SOLO ProductoAlmacenCotizacion para este producto
        $productoAlmacenCotizacion = ProductoAlmacenCotizacion::create([
            'cotizacion_id' => $cotizacion->id,
            'producto_almacen_id' => $productoAlmacen->id,
            'costo' => $productoAlmacen->costo,
        ]);

        // Crear una UnidadDerivadaInmutableCotizacion por cada unidad derivada
        foreach ($unidadesDelProducto as $productoData) {
            // Buscar o crear UnidadDerivadaInmutable
            $unidadDerivada = \App\Models\UnidadDerivada::find($productoData['unidad_derivada_id']);
            $nombreUnidad = $unidadDerivada ? $unidadDerivada->name : 'UNIDAD';

            $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                ['name' => $nombreUnidad]
            );

            // Crear UnidadDerivadaInmutableCotizacion
            UnidadDerivadaInmutableCotizacion::create([
                'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                'producto_almacen_cotizacion_id' => $productoAlmacenCotizacion->id,
                'factor' => $productoData['unidad_derivada_factor'],
                'cantidad' => $productoData['cantidad'],
                'precio' => $productoData['precio_venta'],
                'recargo' => $productoData['recargo'] ?? 0,
                'descuento_tipo' => $productoData['descuento_tipo'] ?? 'm',
                'descuento' => $productoData['descuento'] ?? 0,
            ]);

            // Si se debe reservar stock, descontarlo
            if ($reservarStock) {
                $cantidadEnFraccion = $productoData['cantidad'] * $productoData['unidad_derivada_factor'];
                $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);
            }
        }
    }

    /**
     * Obtener el siguiente número de cotización (sin crear la cotización)
     */
    public function siguienteNumero(): JsonResponse
    {
        $siguienteNumero = $this->generarNumeroCotizacion();

        return response()->json([
            'numero' => $siguienteNumero,
        ]);
    }

    /**
     * Generar número de cotización único
     */
    private function generarNumeroCotizacion(): string
    {
        $year = date('Y');
        $lastCotizacion = Cotizacion::where('numero', 'like', "COT-{$year}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastCotizacion) {
            $lastNumber = (int) substr($lastCotizacion->numero, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('COT-%s-%03d', $year, $newNumber);
    }

    /**
     * Mostrar una cotización específica
     */
    public function show(string $id): JsonResponse
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'user' => function ($query) {
                $query->with(['empresa' => function ($q) {
                    $q->select('id', 'ruc', 'razon_social', 'direccion', 'distrito', 'celular', 'email', 'logo');
                }]);
            },
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
        ])->findOrFail($id);

        return response()->json(['data' => $cotizacion]);
    }

    /**
     * Actualizar una cotización
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cotizacion = Cotizacion::with([
            'productosPorAlmacen.unidadesDerivadas'
        ])->findOrFail($id);

        // No permitir editar cotizaciones convertidas a venta, vendidas o canceladas
        if (in_array($cotizacion->estado_cotizacion, ['co', 've', 'ca'])) {
            return response()->json([
                'message' => 'No se puede editar una cotización confirmada, vendida o cancelada'
            ], 422);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|date',
            'vigencia_dias' => 'sometimes|integer|min:1',
            'tipo_moneda' => 'sometimes|in:Soles,Dólares',
            'tipo_de_cambio' => 'sometimes|numeric|min:0',
            'observaciones' => 'nullable|string',
            'reservar_stock' => 'sometimes|boolean',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'ruc_dni' => 'nullable|string',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'tipo_documento' => 'nullable|string',
            'forma_de_pago' => 'nullable|in:Contado,Crédito',
            'almacen_id' => 'sometimes|integer|exists:almacen,id',
            'productos' => 'sometimes|array',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer|exists:unidadderivada,id',
            'productos.*.unidad_derivada_factor' => 'required|numeric|min:0.001',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.precio_venta' => 'required|numeric|min:0',
            'productos.*.recargo' => 'nullable|numeric|min:0',
            'productos.*.descuento_tipo' => 'nullable|in:m,p,%',
            'productos.*.descuento' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $reservarStockAnterior = $cotizacion->reservar_stock;
            $almacenIdAnterior = $cotizacion->almacen_id;

            // Si cambia reservar_stock de true a false, devolver stock
            if ($reservarStockAnterior && isset($validated['reservar_stock']) && !$validated['reservar_stock']) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);
                    if ($productoAlmacen) {
                        foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidad) {
                            $cantidadEnFraccion = $unidad->cantidad * $unidad->factor;
                            $productoAlmacen->increment('stock_fraccion', $cantidadEnFraccion);
                        }
                    }
                }
            }

            // Si cambia reservar_stock de false a true, reservar stock
            $reservarStockNuevo = $validated['reservar_stock'] ?? $cotizacion->reservar_stock;
            if (!$reservarStockAnterior && $reservarStockNuevo) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);
                    if ($productoAlmacen) {
                        foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidad) {
                            $cantidadEnFraccion = $unidad->cantidad * $unidad->factor;
                            $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);
                        }
                    }
                }
            }

            // Actualizar datos básicos de la cotización
            $cotizacion->update(array_filter($validated, function($key) {
                return !in_array($key, ['productos']);
            }, ARRAY_FILTER_USE_KEY));

            // Si se cambian los productos, eliminar los anteriores y crear nuevos
            if (isset($validated['productos'])) {
                // Primero devolver stock de productos anteriores si estaba reservado
                if ($cotizacion->reservar_stock) {
                    foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                        $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);
                        if ($productoAlmacen) {
                            foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidad) {
                                $cantidadEnFraccion = $unidad->cantidad * $unidad->factor;
                                $productoAlmacen->increment('stock_fraccion', $cantidadEnFraccion);
                            }
                        }
                    }
                }

                // Eliminar productos anteriores
                ProductoAlmacenCotizacion::where('cotizacion_id', $cotizacion->id)->delete();

                // Crear nuevos productos
                $productosAgrupados = collect($validated['productos'])->groupBy('producto_id');

                foreach ($productosAgrupados as $productoId => $unidadesDelProducto) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $productoId)
                        ->where('almacen_id', $cotizacion->almacen_id)
                        ->first();

                    if (!$productoAlmacen) {
                        throw new \Exception("Producto ID {$productoId} no encontrado en almacén {$cotizacion->almacen_id}");
                    }

                    $productoAlmacenCotizacion = ProductoAlmacenCotizacion::create([
                        'cotizacion_id' => $cotizacion->id,
                        'producto_almacen_id' => $productoAlmacen->id,
                        'costo' => $productoAlmacen->costo,
                    ]);

                    foreach ($unidadesDelProducto as $productoData) {
                        $unidadDerivada = \App\Models\UnidadDerivada::find($productoData['unidad_derivada_id']);
                        $nombreUnidad = $unidadDerivada ? $unidadDerivada->name : 'UNIDAD';

                        $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                            ['name' => $nombreUnidad]
                        );

                        UnidadDerivadaInmutableCotizacion::create([
                            'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                            'producto_almacen_cotizacion_id' => $productoAlmacenCotizacion->id,
                            'factor' => $productoData['unidad_derivada_factor'],
                            'cantidad' => $productoData['cantidad'],
                            'precio' => $productoData['precio_venta'],
                            'recargo' => $productoData['recargo'] ?? 0,
                            'descuento_tipo' => $productoData['descuento_tipo'] ?? 'm',
                            'descuento' => $productoData['descuento'] ?? 0,
                        ]);

                        // Reservar stock de nuevos productos si aplica
                        if ($cotizacion->reservar_stock) {
                            $cantidadEnFraccion = $productoData['cantidad'] * $productoData['unidad_derivada_factor'];
                            $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);
                        }
                    }
                }
            }

            // Recalcular fecha de vencimiento si cambia vigencia_dias o fecha
            if (isset($validated['vigencia_dias']) || isset($validated['fecha'])) {
                $fecha = $validated['fecha'] ?? $cotizacion->fecha;
                $vigenciaDias = $validated['vigencia_dias'] ?? $cotizacion->vigencia_dias;
                $cotizacion->fecha_vencimiento = \Carbon\Carbon::parse($fecha)->addDays($vigenciaDias);
                $cotizacion->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Cotización actualizada exitosamente',
                'data' => $cotizacion->load([
                    'cliente',
                    'user',
                    'almacen',
                    'productosPorAlmacen.productoAlmacen.producto',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                ])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al actualizar cotización: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar la cotización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar una cotización (devolver stock si estaba reservado)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::findOrFail($id);

            // Si tenía stock reservado, devolverlo
            if ($cotizacion->reservar_stock) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidadDerivada) {
                        $cantidadEnFraccion = $unidadDerivada->cantidad * $unidadDerivada->factor;
                        $productoAlmacenCotizacion->productoAlmacen->increment('stock_fraccion', $cantidadEnFraccion);
                    }
                }
            }

            // Cambiar estado a cancelado
            $cotizacion->update(['estado_cotizacion' => 'ca']);

            DB::commit();

            return response()->json([
                'message' => 'Cotización cancelada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cancelar la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir cotización a venta
     * POST /api/cotizaciones/{id}/convertir-a-venta
     */
    public function convertirAVenta(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::with([
                'productosPorAlmacen.productoAlmacen',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ])->findOrFail($id);

            // Validar que no esté ya convertida
            if ($cotizacion->venta_id) {
                return response()->json([
                    'message' => 'Esta cotización ya fue convertida a venta',
                    'venta_id' => $cotizacion->venta_id,
                ], 400);
            }

            // Generar ID y número de venta
            $ventaId = 'ven' . Str::random(10);
            
            // Obtener el siguiente número de serie
            $ultimaVenta = \App\Models\Venta::where('tipo_documento', $cotizacion->tipo_documento ?? '03')
                ->orderBy('numero', 'desc')
                ->first();
            
            $numero = $ultimaVenta ? $ultimaVenta->numero + 1 : 1;
            $serie = $ultimaVenta ? $ultimaVenta->serie : '001';

            // Crear la venta
            $venta = \App\Models\Venta::create([
                'id' => $ventaId,
                'tipo_documento' => $cotizacion->tipo_documento ?? '03', // Boleta por defecto
                'serie' => $serie,
                'numero' => $numero,
                'descripcion' => "Convertida desde cotización {$cotizacion->numero}",
                'forma_de_pago' => $cotizacion->forma_de_pago ?? 'co', // Contado por defecto
                'tipo_moneda' => $cotizacion->tipo_moneda,
                'tipo_de_cambio' => $cotizacion->tipo_de_cambio,
                'fecha' => now(),
                'estado_de_venta' => 'pe', // Pendiente
                'cliente_id' => $cotizacion->cliente_id,
                'recomendado_por_id' => null,
                'user_id' => auth()->id(),
                'almacen_id' => $cotizacion->almacen_id,
            ]);

            // Copiar productos de la cotización a la venta
            foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                $productoAlmacenVenta = \App\Models\ProductoAlmacenVenta::create([
                    'venta_id' => $venta->id,
                    'producto_almacen_id' => $productoAlmacenCotizacion->producto_almacen_id,
                    'costo' => $productoAlmacenCotizacion->costo,
                ]);

                foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidadDerivada) {
                    \App\Models\UnidadDerivadaInmutableVenta::create([
                        'unidad_derivada_inmutable_id' => $unidadDerivada->unidad_derivada_inmutable_id,
                        'producto_almacen_venta_id' => $productoAlmacenVenta->id,
                        'factor' => $unidadDerivada->factor,
                        'cantidad' => $unidadDerivada->cantidad,
                        'precio' => $unidadDerivada->precio,
                        'recargo' => $unidadDerivada->recargo,
                        'descuento_tipo' => $unidadDerivada->descuento_tipo,
                        'descuento' => $unidadDerivada->descuento,
                    ]);

                    // Si la cotización NO tenía stock reservado, descontarlo ahora
                    if (!$cotizacion->reservar_stock) {
                        $cantidadEnFraccion = $unidadDerivada->cantidad * $unidadDerivada->factor;
                        $productoAlmacenCotizacion->productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);
                    }
                }
            }

            // Actualizar la cotización con el ID de la venta
            $cotizacion->update([
                'venta_id' => $venta->id,
                'estado_cotizacion' => 'co', // Convertida
            ]);

            DB::commit();

            // Cargar relaciones para la respuesta
            $venta->load([
                'cliente',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ]);

            return response()->json([
                'data' => $venta,
                'message' => 'Cotización convertida a venta exitosamente',
                'venta_id' => $venta->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al convertir la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }
}
