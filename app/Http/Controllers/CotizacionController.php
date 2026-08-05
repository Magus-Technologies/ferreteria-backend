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
use App\Services\Producto\ComplementarioStockService;

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
        } else {
            // Por defecto excluir eliminadas de la vista
            $query->where('estado_cotizacion', '!=', 'el');
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

        if ($request->has('reservar_stock')) {
            $query->where('reservar_stock', filter_var($request->reservar_stock, FILTER_VALIDATE_BOOLEAN));
        }

        // Búsqueda por número o cliente (búsqueda global)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', '%' . $search . '%')
                  ->orWhereHas('cliente', function ($qc) use ($search) {
                      $qc->where('razon_social', 'like', '%' . $search . '%')
                         ->orWhere('nombres', 'like', '%' . $search . '%')
                         ->orWhere('apellidos', 'like', '%' . $search . '%')
                         ->orWhere('numero_documento', 'like', '%' . $search . '%');
                  });
            });
        }
 
        // Búsqueda específica por número
        if ($request->has('numero') && $request->numero) {
            $query->where('numero', 'like', '%' . $request->numero . '%');
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
            'fecha_vencimiento_reserva' => 'nullable|date',

            'almacen_id' => 'required|integer|exists:almacen,id',
            'recomendado_por_id' => 'nullable|integer|exists:cliente,id',
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
                'fecha_vencimiento_reserva' => $validated['fecha_vencimiento_reserva'] ?? null,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'ruc_dni' => $validated['ruc_dni'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'user_id' => auth()->id(),
                'vendedor' => $validated['vendedor'] ?? null,
                'forma_de_pago' => $validated['forma_de_pago'] ?? null,
                'almacen_id' => $validated['almacen_id'],
                'recomendado_por_id' => $validated['recomendado_por_id'] ?? null,
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
                // Registrar en kardex de facturación ANTES de descontar, para que capture
                // el stock_anterior correcto (registrar() lee el stock físico en vivo).
                app(\App\Services\Kardex\KardexFacturacionService::class)->registrarReservaCotizacion(
                    $cotizacion,
                    $productoAlmacen->load('producto'),
                    [
                        'unidad_derivada_inmutable_name' => $nombreUnidad,
                        'cantidad' => $productoData['cantidad'],
                        'factor' => $productoData['unidad_derivada_factor'],
                        'precio' => $productoData['precio_venta'] ?? 0,
                    ],
                    $productoAlmacen->costo
                );

                $cantidadEnFraccion = $productoData['cantidad'] * $productoData['unidad_derivada_factor'];
                app(\App\Services\Producto\ProductoLoteService::class)->consumirLotes(
                    $productoAlmacen,
                    $cantidadEnFraccion,
                    ['tipo' => 'cotizacion', 'id' => $cotizacion->id]
                );

                // Descontar stock del producto complementario
                ComplementarioStockService::procesarComplementarioPorFactor(
                    $productoAlmacen->id,
                    $productoData['unidad_derivada_factor'],
                    $productoData['cantidad'],
                    $almacenId,
                    false // salida
                );
            }
        }
    }

    /**
     * Reserva stock para UNA unidad derivada de una cotización: registra el kardex
     * "RESERVA COTIZACIÓN" y consume de los lotes PEPS reales (no solo decrementa
     * `stock_fraccion` directamente) para que la reserva quede sincronizada con el
     * inventario real — así, si la cotización se convierte en venta con una cantidad
     * distinta a la reservada, `VentaController::store()` puede calcular el excedente
     * de forma confiable. Seguro de llamar varias veces (una por unidad derivada) para
     * el mismo producto/cotización: cada llamada solo SUMA consumo, no lo reemplaza.
     */
    private function reservarStockLinea(
        Cotizacion $cotizacion,
        ProductoAlmacen $productoAlmacen,
        array $unidad,
        \App\Services\Kardex\KardexFacturacionService $kardexService
    ): void {
        $kardexService->registrarReservaCotizacion(
            $cotizacion,
            $productoAlmacen->load('producto'),
            $unidad,
            $productoAlmacen->costo
        );

        $cantidadEnFraccion = (float) $unidad['cantidad'] * (float) $unidad['factor'];
        app(\App\Services\Producto\ProductoLoteService::class)->consumirLotes(
            $productoAlmacen,
            $cantidadEnFraccion,
            ['tipo' => 'cotizacion', 'id' => $cotizacion->id]
        );

        ComplementarioStockService::procesarComplementarioPorFactor(
            $productoAlmacen->id,
            (float) $unidad['factor'],
            (float) $unidad['cantidad'],
            $productoAlmacen->almacen_id,
            false // salida
        );
    }

    /**
     * Libera TODA la reserva de un producto de la cotización (todas sus unidades
     * derivadas): registra un kardex "RESERVA LIBERADA" por unidad y revierte el
     * consumo de lotes PEPS registrado al reservar (o reingresa al lote más nuevo si
     * es una reserva legacy sin registro de consumo). IMPORTANTE: revierte el consumo
     * de lotes UNA SOLA VEZ por producto (con el total de todas sus unidades), no por
     * unidad — `ProductoLoteService::revertirConsumoOReingresar` opera a nivel de
     * (producto_almacen, origen), así que llamarla más de una vez por producto
     * duplicaría la reversión.
     */
    private function liberarStockProducto(
        Cotizacion $cotizacion,
        ProductoAlmacenCotizacion $productoAlmacenCotizacion,
        \App\Services\Kardex\KardexFacturacionService $kardexService,
        string $motivo
    ): void {
        $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);
        if (! $productoAlmacen) {
            return;
        }

        $totalFraccion = 0.0;
        foreach ($productoAlmacenCotizacion->unidadesDerivadas as $ud) {
            $unidad = [
                'unidad_derivada_inmutable_name' => $ud->unidadDerivadaInmutable->name ?? 'UNIDAD',
                'cantidad' => $ud->cantidad,
                'factor' => $ud->factor,
                'precio' => $ud->precio,
            ];

            $kardexService->registrarLiberacionReservaCotizacion(
                $cotizacion,
                $productoAlmacen->load('producto'),
                $unidad,
                $productoAlmacen->costo,
                $motivo
            );

            $totalFraccion += (float) $ud->cantidad * (float) $ud->factor;

            ComplementarioStockService::procesarComplementarioPorFactor(
                $productoAlmacen->id,
                (float) $ud->factor,
                (float) $ud->cantidad,
                $productoAlmacen->almacen_id,
                true // ingreso (devolver)
            );
        }

        app(\App\Services\Producto\ProductoLoteService::class)->revertirConsumoOReingresar(
            $productoAlmacen,
            'cotizacion',
            $cotizacion->id,
            $totalFraccion
        );
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
                    $q->select('id', 'ruc', 'razon_social', 'celular', 'email', 'logo')
                      ->with('direcciones');
                }]);
            },
            'almacen',
            'recomendadoPor',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            // Cargar las unidades derivadas DISPONIBLES del producto en almacén
            // (con todos sus precios/comisiones). Sin esto, los selects de
            // tipo_precio y unidad_derivada en la pantalla de editar quedan
            // sin opciones porque el store del front solo se llena al agregar
            // un producto manualmente.
            'productosPorAlmacen.productoAlmacen.unidadesDerivadas.unidadDerivada',
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
            'fecha_proforma' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'vigencia_dias' => 'sometimes|integer|min:1',
            'tipo_moneda' => 'sometimes|in:s,d',
            'tipo_de_cambio' => 'sometimes|numeric|min:0',
            'observaciones' => 'nullable|string',
            'reservar_stock' => 'sometimes|boolean',
            'fecha_vencimiento_reserva' => 'nullable|date',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'ruc_dni' => 'nullable|string',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'tipo_documento' => 'nullable|string',
            'vendedor' => 'nullable|string|max:191',
            'forma_de_pago' => 'nullable|string|max:50',
            'almacen_id' => 'sometimes|integer|exists:almacen,id',
            'recomendado_por_id' => 'nullable|integer|exists:cliente,id',
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
            $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
            // Construye el array que esperan registrarReservaCotizacion/
            // registrarLiberacionReservaCotizacion a partir de un modelo
            // UnidadDerivadaInmutableCotizacion (los productos ya guardados en la cotización).
            $unidadArray = fn ($unidad) => [
                'unidad_derivada_inmutable_name' => $unidad->unidadDerivadaInmutable->name ?? 'UNIDAD',
                'cantidad' => $unidad->cantidad,
                'factor' => $unidad->factor,
                'precio' => $unidad->precio,
            ];

            // Si cambia reservar_stock de true a false, devolver stock
            if ($reservarStockAnterior && isset($validated['reservar_stock']) && !$validated['reservar_stock']) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $this->liberarStockProducto($cotizacion, $productoAlmacenCotizacion, $kardexFacturacionService, 'reserva desactivada');
                }
            }

            // Si cambia reservar_stock de false a true, reservar stock
            $reservarStockNuevo = $validated['reservar_stock'] ?? $cotizacion->reservar_stock;
            if (!$reservarStockAnterior && $reservarStockNuevo) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);
                    if ($productoAlmacen) {
                        foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidad) {
                            $this->reservarStockLinea($cotizacion, $productoAlmacen, $unidadArray($unidad), $kardexFacturacionService);
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
                        $this->liberarStockProducto($cotizacion, $productoAlmacenCotizacion, $kardexFacturacionService, 'producto reemplazado');
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
                            $this->reservarStockLinea($cotizacion, $productoAlmacen, [
                                'unidad_derivada_inmutable_name' => $nombreUnidad,
                                'cantidad' => $productoData['cantidad'],
                                'factor' => $productoData['unidad_derivada_factor'],
                                'precio' => $productoData['precio_venta'] ?? 0,
                            ], $kardexFacturacionService);
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
                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $this->liberarStockProducto($cotizacion, $productoAlmacenCotizacion, $kardexFacturacionService, 'cotización cancelada');
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

        // ── Transformar datos de la cotización al formato que VentaController::store espera ──
        $productosPorAlmacen = $cotizacion->productosPorAlmacen->map(function ($pac) {
            return [
                'costo' => $pac->costo,
                'producto_almacen_id' => $pac->producto_almacen_id,
                'unidades_derivadas' => $pac->unidadesDerivadas->map(function ($ud) {
                    return [
                        'unidad_derivada_inmutable_id' => $ud->unidad_derivada_inmutable_id,
                        'factor' => $ud->factor,
                        'cantidad' => $ud->cantidad,
                        // Toda la cantidad ya fue reservada por la cotización:
                        // store resta esto y no descuenta stock de nuevo.
                        'cantidad_ya_aplicada' => $ud->cantidad,
                        'cantidad_pendiente' => $ud->cantidad, // Todas pendientes al convertir
                        'precio' => $ud->precio,
                        'recargo' => $ud->recargo ?? 0,
                        'descuento_tipo' => $ud->descuento_tipo ?? 'm',
                        'descuento' => $ud->descuento ?? 0,
                        'comision' => 0,
                    ];
                })->toArray(),
            ];
        })->toArray();

        $payload = [
            'tipo_documento'   => $cotizacion->tipo_documento ?? '03',
            'forma_de_pago'    => $cotizacion->forma_de_pago ?? 'co',
            'tipo_moneda'      => $cotizacion->tipo_moneda instanceof \BackedEnum
                ? $cotizacion->tipo_moneda->value
                : $cotizacion->tipo_moneda,
            'tipo_de_cambio'   => $cotizacion->tipo_de_cambio ?? 1,
            'fecha'            => now()->toDateString(),
            'estado_de_venta'  => 'cr', // Creado — no 'pe' (pendiente)
            'cliente_id'       => $cotizacion->cliente_id,
            'recomendado_por_id' => $cotizacion->recomendado_por_id,
            'user_id'          => auth()->id(),
            'almacen_id'       => $cotizacion->almacen_id,
            'descripcion'      => "Convertida desde cotización {$cotizacion->numero}",
            // Si la cotización ya reservó stock, avisar a store para no descontar de nuevo
            // (fallback; store() prioriza `cotizacion_id` de abajo y verifica en BD).
            'stock_ya_aplicado' => (bool) $cotizacion->reservar_stock,
            // store() usa esto para verificar reservar_stock directo en la BD y para
            // liberar la reserva (reservar_stock=false) una vez que la venta la consume.
            'cotizacion_id'    => $cotizacion->id,
            // En Tienda por defecto; la entrega se auto-crea en store()
            'tipo_despacho'    => 'et',
            'productos_por_almacen' => $productosPorAlmacen,
        ];

        // Delegar toda la lógica a VentaController::store (PEPS, stock, entrega, comprobante, kardex, vales)
        /** @var \App\Http\Controllers\VentaController $ventaCtrl */
        $ventaCtrl = app(\App\Http\Controllers\VentaController::class);
        $fakeRequest = \Illuminate\Http\Request::create('/api/ventas', 'POST', $payload);
        $response = $ventaCtrl->store($fakeRequest);

        if ($response->getStatusCode() !== 201) {
            return $response; // Propagar error de validación
        }

        $responseData = json_decode($response->getContent(), true);
        $ventaId = $responseData['venta_id'] ?? $responseData['data']['id'] ?? null;

        if (!$ventaId) {
            return response()->json(['message' => 'Venta creada pero no se pudo obtener el ID'], 500);
        }

        // Vincular la cotización con la venta
        $cotizacion->update([
            'venta_id' => $ventaId,
            'estado_cotizacion' => 'co', // Convertida
        ]);

        return response()->json([
            'data' => \App\Models\Venta::with([
                'cliente',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ])->find($ventaId),
            'message' => 'Cotización convertida a venta exitosamente',
            'venta_id' => $ventaId,
        ], 201);
    }

    /**
     * Eliminar lógicamente una cotización (se queda visible en tabla como eliminada).
     * POST /api/cotizaciones/{id}/eliminar
     */
    public function eliminar(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::with([
                'productosPorAlmacen.productoAlmacen',
                'productosPorAlmacen.unidadesDerivadas',
            ])->findOrFail($id);

            if ($cotizacion->estado_cotizacion === 'el') {
                return response()->json(['message' => 'La cotización ya está eliminada'], 400);
            }

            // Devolver stock reservado si aplica
            if ($cotizacion->reservar_stock) {
                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                foreach ($cotizacion->productosPorAlmacen as $pac) {
                    $this->liberarStockProducto($cotizacion, $pac, $kardexFacturacionService, 'cotización eliminada');
                }
            }

            $cotizacion->update(['estado_cotizacion' => 'el', 'reservar_stock' => false]);

            DB::commit();

            return response()->json(['message' => 'Cotización eliminada']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vincular una cotización a una venta ya creada (flujo manual desde el formulario).
     * POST /api/cotizaciones/{id}/vincular-venta
     */
    public function vincularVenta(string $id, Request $request): JsonResponse
    {
        $request->validate(['venta_id' => 'required|string|exists:venta,id']);

        $cotizacion = Cotizacion::with([
            'productosPorAlmacen.productoAlmacen.producto',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
        ])->findOrFail($id);

        if ($cotizacion->venta_id) {
            return response()->json(['message' => 'Esta cotización ya está vinculada a una venta'], 400);
        }

        try {
            DB::beginTransaction();

            // Si la cotización reservó stock y la venta lleva MENOS de lo reservado,
            // devolver el excedente: esa parte se reservó pero nunca se vendió.
            if ($cotizacion->reservar_stock) {
                $venta = \App\Models\Venta::with([
                    'productosPorAlmacen.unidadesDerivadas',
                ])->find($request->venta_id);

                // Fracción vendida por producto_almacen (todas las unidades de la venta)
                $vendidoPorAlmacen = [];
                if ($venta) {
                    foreach ($venta->productosPorAlmacen as $productoAlmacenVenta) {
                        $fraccion = 0;
                        foreach ($productoAlmacenVenta->unidadesDerivadas as $unidadDerivadaVenta) {
                            $fraccion += (float) $unidadDerivadaVenta->cantidad * (float) $unidadDerivadaVenta->factor;
                        }
                        $vendidoPorAlmacen[$productoAlmacenVenta->producto_almacen_id] =
                            ($vendidoPorAlmacen[$productoAlmacenVenta->producto_almacen_id] ?? 0) + $fraccion;
                    }
                }

                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);

                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    // Fracción reservada por la cotización
                    $reservado = 0;
                    foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidadDerivada) {
                        $reservado += (float) $unidadDerivada->cantidad * (float) $unidadDerivada->factor;
                    }

                    $vendido = (float) ($vendidoPorAlmacen[$productoAlmacenCotizacion->producto_almacen_id] ?? 0);
                    $excedente = $reservado - $vendido;
                    if ($excedente <= 0) {
                        continue;
                    }

                    $productoAlmacen = $productoAlmacenCotizacion->productoAlmacen;

                    // Registrar en kardex ANTES de incrementar, para capturar el
                    // stock_anterior correcto. cantidad=fracción excedente, factor=1.
                    $unidadReferencia = $productoAlmacenCotizacion->unidadesDerivadas->first();
                    $kardexFacturacionService->registrarLiberacionReservaCotizacion(
                        $cotizacion,
                        $productoAlmacen->load('producto'),
                        [
                            'unidad_derivada_inmutable_name' => $unidadReferencia?->unidadDerivadaInmutable->name ?? 'UNIDAD',
                            'cantidad' => $excedente,
                            'factor' => 1,
                            'precio' => $unidadReferencia?->precio ?? 0,
                        ],
                        $productoAlmacen->costo,
                        'excedente venta vinculada'
                    );

                    $productoAlmacen->increment('stock_fraccion', $excedente);

                    // Devolver stock del producto complementario
                    ComplementarioStockService::procesarComplementarioPorFactor(
                        $productoAlmacenCotizacion->producto_almacen_id,
                        1,
                        $excedente,
                        $productoAlmacen->almacen_id,
                        true // ingreso (devolver)
                    );
                }
            }

            $cotizacion->update([
                'venta_id' => $request->venta_id,
                'estado_cotizacion' => 'co',
                // El stock reservado por esta cotización ya es propiedad de la venta
                // recién vinculada (haya descontado de nuevo o no): la reserva queda
                // cerrada. Evita que el cron `reservas:liberar-expiradas` la devuelva.
                'reservar_stock' => false,
            ]);

            // Actualizar la descripción de la venta para que muestre el número de cotización
            \App\Models\Venta::where('id', $request->venta_id)->update([
                'descripcion' => "Convertida desde cotización {$cotizacion->numero}",
            ]);

            DB::commit();

            return response()->json(['message' => 'Cotización vinculada a venta exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al vincular la cotización a la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicar una cotización (crea una copia en estado pendiente con nuevo número).
     * POST /api/cotizaciones/{id}/duplicar
     */
    public function duplicar(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $original = Cotizacion::with([
                'productosPorAlmacen.productoAlmacen',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ])->findOrFail($id);

            $nuevaId = 'cot' . Str::random(10);
            $nuevoNumero = $this->generarNumeroCotizacion();
            $vigenciaDias = $original->vigencia_dias ?? 7;

            $nueva = Cotizacion::create([
                'id' => $nuevaId,
                'numero' => $nuevoNumero,
                'fecha' => now(),
                'fecha_proforma' => now(),
                'vigencia_dias' => $vigenciaDias,
                'fecha_vencimiento' => now()->addDays($vigenciaDias)->format('Y-m-d H:i:s'),
                'tipo_moneda' => $original->tipo_moneda,
                'tipo_de_cambio' => $original->tipo_de_cambio,
                'observaciones' => $original->observaciones,
                'estado_cotizacion' => 'pe',
                'reservar_stock' => false,
                'cliente_id' => $original->cliente_id,
                'ruc_dni' => $original->ruc_dni,
                'telefono' => $original->telefono,
                'direccion' => $original->direccion,
                'tipo_documento' => $original->tipo_documento,
                'user_id' => auth()->id(),
                'vendedor' => $original->vendedor,
                'forma_de_pago' => $original->forma_de_pago,
                'almacen_id' => $original->almacen_id,
                'recomendado_por_id' => $original->recomendado_por_id,
            ]);

            foreach ($original->productosPorAlmacen as $pac) {
                $nuevoPac = \App\Models\ProductoAlmacenCotizacion::create([
                    'cotizacion_id' => $nueva->id,
                    'producto_almacen_id' => $pac->producto_almacen_id,
                    'costo' => $pac->costo,
                ]);

                foreach ($pac->unidadesDerivadas as $ud) {
                    \App\Models\UnidadDerivadaInmutableCotizacion::create([
                        'unidad_derivada_inmutable_id' => $ud->unidad_derivada_inmutable_id,
                        'producto_almacen_cotizacion_id' => $nuevoPac->id,
                        'factor' => $ud->factor,
                        'cantidad' => $ud->cantidad,
                        'precio' => $ud->precio,
                        'recargo' => $ud->recargo,
                        'descuento_tipo' => $ud->descuento_tipo,
                        'descuento' => $ud->descuento,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'data' => ['id' => $nueva->id, 'numero' => $nuevoNumero],
                'message' => "Cotización duplicada como N° {$nuevoNumero}",
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al duplicar: ' . $e->getMessage()], 500);
        }
    }
}
