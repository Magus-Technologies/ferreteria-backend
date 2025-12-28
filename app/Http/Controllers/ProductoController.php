<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenUnidadDerivada;
use App\Models\IngresoSalida;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\UnidadDerivadaInmutable;
use App\Models\TipoIngresoSalida;
use App\Models\Compra;
use App\Models\ProductoAlmacenCompra;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Required permission: PRODUCTO_LISTADO
     */
    public function index(Request $request): JsonResponse
    {
        // Validar que almacen_id sea requerido
        $request->validate([
            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        $almacenId = $request->almacen_id;

        $query = Producto::with([
            'marca:id,name',
            'categoria:id,name',
            'unidadMedida:id,name',
            'productoEnAlmacenes' => function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)
                    ->with([
                        'almacen:id,name',
                        'ubicacion:id,name',
                        'unidadesDerivadas.unidadDerivada:id,name',
                        'compras' => function ($cq) {
                            $cq->whereHas('compra', function ($sq) {
                                    $sq->where('estado_de_compra', 'Procesado');
                                })
                                ->with([
                                    'compra:id,fecha,proveedor_id,created_at',
                                    'compra.proveedor:id,razon_social',
                                    'unidadesDerivadas' => function ($udq) {
                                        $udq->select('id', 'producto_almacen_compra_id', 'cantidad', 'factor', 'unidad_derivada_inmutable_id')
                                            ->with('unidadDerivadaInmutable:id,name')
                                            ->take(1);
                                    }
                                ])
                                ->join('compra', 'productoalmacencompra.compra_id', '=', 'compra.id')
                                ->orderBy('compra.created_at', 'desc')
                                ->select('productoalmacencompra.*')
                                ->take(3);
                        }
                    ]);
            }
        ]);

        // Filtros opcionales
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cod_producto', 'like', "%{$search}%")
                  ->orWhere('cod_barra', 'like', "%{$search}%");
            });
        }

        if ($request->has('estado')) {
            // Convertir 'true'/'false' string a booleano
            $estadoValue = filter_var($request->estado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estadoValue !== null) {
                $query->where('estado', $estadoValue);
            }
        }

        if ($request->has('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->has('marca_id')) {
            $query->where('marca_id', $request->marca_id);
        }

        // Filtro por unidad de medida
        if ($request->has('unidad_medida_id')) {
            $query->where('unidad_medida_id', $request->unidad_medida_id);
        }

        // Filtro por acción técnica (búsqueda parcial)
        if ($request->has('accion_tecnica')) {
            $query->where('accion_tecnica', 'like', "%{$request->accion_tecnica}%");
        }

        // Filtro por ubicación (requiere que el producto esté en esa ubicación en el almacén especificado)
        if ($request->has('ubicacion_id')) {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId, $request) {
                $q->where('almacen_id', $almacenId)
                  ->where('ubicacion_id', $request->ubicacion_id);
            });
        }

        // Filtro por stock (con_stock, sin_stock)
        if ($request->has('cs_stock')) {
            $csStock = $request->cs_stock;

            if ($csStock === 'con_stock') {
                // Productos con stock > 0 en el almacén especificado
                $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId)
                      ->where('stock_fraccion', '>', 0);
                });
            } elseif ($csStock === 'sin_stock') {
                // Productos con stock <= 0 en el almacén especificado
                $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId)
                      ->where('stock_fraccion', '<=', 0);
                });
            }
            // Si es 'all' o cualquier otro valor, no aplicar filtro
        }

        // Filtro por comisión (con_comision, sin_comision)
        if ($request->has('cs_comision')) {
            $csComision = $request->cs_comision;

            if ($csComision === 'con_comision') {
                // Productos que tienen al menos una comisión > 0 en cualquiera de los 4 campos
                $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId)
                      ->whereHas('unidadesDerivadas', function ($udq) {
                          $udq->where(function ($orQuery) {
                              $orQuery->where('comision_publico', '>', 0)
                                      ->orWhere('comision_especial', '>', 0)
                                      ->orWhere('comision_minimo', '>', 0)
                                      ->orWhere('comision_ultimo', '>', 0);
                          });
                      });
                });
            } elseif ($csComision === 'sin_comision') {
                // Productos donde TODAS las comisiones son <= 0 o null
                $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId)
                      ->whereHas('unidadesDerivadas', function ($udq) {
                          $udq->where(function ($andQuery) {
                              $andQuery->where('comision_publico', '<=', 0)
                                       ->where('comision_especial', '<=', 0)
                                       ->where('comision_minimo', '<=', 0)
                                       ->where('comision_ultimo', '<=', 0);
                          });
                      });
                });
            }
            // Si es 'all' o cualquier otro valor, no aplicar filtro
        }

        // Paginación de 100 items por defecto (requerido para mi-almacen)
        $perPage = $request->get('per_page', 100);

        // Si hay búsqueda activa, ordenar alfabéticamente por nombre
        // De lo contrario, ordenar por fecha de creación (más reciente primero)
        if ($request->has('search') && !empty($request->search)) {
            $productos = $query->orderBy('name', 'asc')->paginate($perPage);
        } else {
            $productos = $query->latest()->paginate($perPage);
        }

        return response()->json($productos);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Crea producto completo con:
     * - Producto (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Precios (tabla productoalmacenunidadderivada)
     * - Ingreso inicial opcional (8 tablas si hay stock)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Campos de Producto
            'cod_producto' => 'nullable|string|unique:producto',
            'cod_barra' => 'nullable|string|unique:producto',
            'name' => 'required|string|unique:producto',
            'name_ticket' => 'required|string',
            'categoria_id' => 'required|exists:categoria,id',
            'marca_id' => 'required|exists:marca,id',
            'unidad_medida_id' => 'required|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'img' => 'nullable|string',
            'ficha_tecnica' => 'nullable|string',
            'stock_min' => 'required|numeric|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'unidades_contenidas' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'permitido' => 'nullable|boolean',

            // Contexto
            'almacen_id' => 'required|exists:almacen,id',

            // ProductoAlmacen
            'producto_almacen' => 'required|array',
            'producto_almacen.ubicacion_id' => 'required|exists:ubicacion,id',

            // Unidades Derivadas (Precios)
            'unidades_derivadas' => 'required|array|min:1',
            'unidades_derivadas.*.unidad_derivada_id' => 'required|exists:unidadderivada,id',
            'unidades_derivadas.*.factor' => 'required|numeric|min:0',
            'unidades_derivadas.*.precio_publico' => 'required|numeric|min:0',
            'unidades_derivadas.*.comision_publico' => 'nullable|numeric',
            'unidades_derivadas.*.precio_especial' => 'nullable|numeric',
            'unidades_derivadas.*.comision_especial' => 'nullable|numeric',
            'unidades_derivadas.*.activador_especial' => 'nullable|numeric',
            'unidades_derivadas.*.precio_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.precio_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.costo' => 'required|numeric|min:0',

            // Compra (Ingreso Inicial)
            'compra' => 'nullable|array',
            'compra.lote' => 'nullable|string',
            'compra.vencimiento' => 'nullable|date',
            'compra.stock_entero' => 'nullable|numeric|min:0',
            'compra.stock_fraccion' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            // PASO 1: Auto-generar cod_producto si no existe
            if (empty($validated['cod_producto'])) {
                $ultimoProducto = Producto::latest('id')->first();
                $validated['cod_producto'] = (string) ($ultimoProducto ? $ultimoProducto->id + 1 : 1);
            }

            // PASO 2: Crear Producto
            $producto = Producto::create([
                'cod_producto' => $validated['cod_producto'],
                'cod_barra' => $validated['cod_barra'] ?? null,
                'name' => $validated['name'],
                'name_ticket' => $validated['name_ticket'],
                'categoria_id' => $validated['categoria_id'],
                'marca_id' => $validated['marca_id'],
                'unidad_medida_id' => $validated['unidad_medida_id'],
                'accion_tecnica' => $validated['accion_tecnica'] ?? null,
                'img' => $validated['img'] ?? null,
                'ficha_tecnica' => $validated['ficha_tecnica'] ?? null,
                'stock_min' => $validated['stock_min'],
                'stock_max' => $validated['stock_max'] ?? null,
                'unidades_contenidas' => $validated['unidades_contenidas'],
                'estado' => $validated['estado'] ?? true,
                'permitido' => $validated['permitido'] ?? true,
            ]);

            // PASO 3: Crear ProductoAlmacen
            $unidadesDerivadas = $validated['unidades_derivadas'];
            $costoUnidad = count($unidadesDerivadas) > 0
                ? $unidadesDerivadas[0]['costo'] / $unidadesDerivadas[0]['factor']
                : 0;

            $productoAlmacen = ProductoAlmacen::create([
                'producto_id' => $producto->id,
                'almacen_id' => $validated['almacen_id'],
                'costo' => $costoUnidad,
                'ubicacion_id' => $validated['producto_almacen']['ubicacion_id'],
                'stock_fraccion' => 0,
            ]);

            // PASO 4: Crear múltiples ProductoAlmacenUnidadDerivada (Precios)
            $preciosData = array_map(function($item) use ($productoAlmacen) {
                return [
                    'producto_almacen_id' => $productoAlmacen->id,
                    'unidad_derivada_id' => $item['unidad_derivada_id'],
                    'factor' => $item['factor'],
                    'precio_publico' => $item['precio_publico'],
                    'comision_publico' => $item['comision_publico'] ?? 0,
                    'precio_especial' => $item['precio_especial'] ?? 0,
                    'comision_especial' => $item['comision_especial'] ?? 0,
                    'activador_especial' => $item['activador_especial'] ?? null,
                    'precio_minimo' => $item['precio_minimo'] ?? 0,
                    'comision_minimo' => $item['comision_minimo'] ?? 0,
                    'activador_minimo' => $item['activador_minimo'] ?? null,
                    'precio_ultimo' => $item['precio_ultimo'] ?? null,
                    'comision_ultimo' => $item['comision_ultimo'] ?? 0,
                    'activador_ultimo' => $item['activador_ultimo'] ?? null,
                ];
            }, $unidadesDerivadas);

            ProductoAlmacenUnidadDerivada::insert($preciosData);

            // PASO 5: Buscar unidad derivada base (factor === unidades_contenidas)
            $unidadBase = ProductoAlmacenUnidadDerivada::with('unidadDerivada')
                ->where('producto_almacen_id', $productoAlmacen->id)
                ->where('factor', $producto->unidades_contenidas)
                ->first();

            if (!$unidadBase) {
                $unidadBase = ProductoAlmacenUnidadDerivada::with('unidadDerivada')
                    ->where('producto_almacen_id', $productoAlmacen->id)
                    ->firstOrFail();
            }

            // PASO 6: Crear ingreso inicial (si hay stock)
            $compra = $validated['compra'] ?? null;
            if ($compra && (!empty($compra['stock_entero']) || !empty($compra['stock_fraccion']))) {
                // 6.1: Calcular cantidad total en fracciones
                $stockEntero = $compra['stock_entero'] ?? 0;
                $stockFraccion = $compra['stock_fraccion'] ?? 0;
                $compraCantidad = ($stockEntero * $producto->unidades_contenidas) + $stockFraccion;
                $compraCosto = $unidadesDerivadas[0]['costo'];

                // 6.2: Buscar tipo_ingreso "AJUSTE"
                $tipoIngreso = TipoIngresoSalida::where('name', 'AJUSTE')->firstOrFail();

                // 6.3: Generar número de ingreso
                $ultimoIngreso = IngresoSalida::where('tipo_documento', 'in')
                    ->latest('numero')
                    ->first();
                $numero = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

                // 6.4: Obtener usuario y serie
                $user = auth()->user();

                // 6.5: Crear IngresoSalida
                $ingresoSalida = IngresoSalida::create([
                    'tipo_ingreso_id' => $tipoIngreso->id,
                    'descripcion' => 'Ingreso por Creación de Producto',
                    'almacen_id' => $productoAlmacen->almacen_id,
                    'user_id' => $user->id,
                    'tipo_documento' => 'in',
                    'serie' => 1, // Serie por defecto
                    'fecha' => now(),
                    'numero' => $numero,
                    'estado' => true,
                ]);

                // 6.6: Crear ProductoAlmacenIngresoSalida
                $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
                    'ingreso_id' => $ingresoSalida->id,
                    'costo' => $compraCosto,
                    'producto_almacen_id' => $productoAlmacen->id,
                ]);

                // 6.7: Crear/Conectar UnidadDerivadaInmutable
                $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate([
                    'name' => $unidadBase->unidadDerivada->name,
                ]);

                // 6.8: Crear UnidadDerivadaInmutableIngresoSalida
                $factor = $unidadesDerivadas[0]['factor'];
                $unidadDerivadaInmutableIngresoSalida = UnidadDerivadaInmutableIngresoSalida::create([
                    'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                    'producto_almacen_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                    'factor' => $factor,
                    'cantidad' => $compraCantidad / $factor,
                    'cantidad_restante' => $compraCantidad / $factor,
                    'lote' => $compra['lote'] ?? null,
                    'vencimiento' => $compra['vencimiento'] ?? null,
                ]);

                // 6.9: Crear Historial
                HistorialUnidadDerivadaInmutableIngresoSalida::create([
                    'unidad_derivada_inmutable_ingreso_salida_id' => $unidadDerivadaInmutableIngresoSalida->id,
                    'stock_anterior' => 0,
                    'stock_nuevo' => $compraCantidad,
                ]);

                // 6.10: Actualizar stock en ProductoAlmacen
                $productoAlmacen->update([
                    'stock_fraccion' => $compraCantidad,
                    'costo' => $compraCosto / $factor,
                ]);
            }

            // Cargar relaciones para la respuesta
            $producto->load(['categoria', 'marca', 'unidadMedida']);

            return response()->json([
                'data' => $producto,
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $producto = Producto::with([
            'categoria',
            'marca',
            'unidadMedida',
            'productoEnAlmacenes.almacen',
            'productoEnAlmacenes.ubicacion',
            'productoEnAlmacenes.unidadesDerivadas',
        ])->findOrFail($id);

        return response()->json($producto);
    }

    /**
     * Get price details for a specific product in a warehouse.
     *
     * GET /api/productos/{id}/detalle-precios?almacen_id={id}
     * Required permission: PRODUCTO_LISTADO
     */
    public function detallePrecios(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        $almacenId = $request->almacen_id;

        $producto = Producto::with([
            'marca:id,name',
            'categoria:id,name',
            'unidadMedida:id,name',
            'productoEnAlmacenes' => function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)
                    ->with([
                        'almacen:id,name',
                        'ubicacion:id,name',
                        'unidadesDerivadas.unidadDerivada:id,name',
                    ]);
            }
        ])->findOrFail($id);

        // Extraer el producto_almacen específico
        $productoAlmacen = $producto->productoEnAlmacenes->first();

        if (!$productoAlmacen) {
            return response()->json([
                'message' => 'El producto no existe en el almacén especificado',
            ], 404);
        }

        return response()->json([
            'data' => [
                'producto' => [
                    'id' => $producto->id,
                    'name' => $producto->name,
                    'cod_producto' => $producto->cod_producto,
                    'marca' => $producto->marca,
                    'categoria' => $producto->categoria,
                    'unidad_medida' => $producto->unidadMedida,
                ],
                'producto_almacen' => [
                    'id' => $productoAlmacen->id,
                    'costo' => (float) $productoAlmacen->costo,
                    'stock_fraccion' => (float) $productoAlmacen->stock_fraccion,
                    'almacen' => $productoAlmacen->almacen,
                    'ubicacion' => $productoAlmacen->ubicacion,
                ],
                'unidades_derivadas' => $productoAlmacen->unidadesDerivadas->map(function ($ud) {
                    return [
                        'id' => $ud->id,
                        'producto_almacen_id' => $ud->producto_almacen_id,
                        'unidad_derivada_id' => $ud->unidad_derivada_id,
                        'factor' => (float) $ud->factor,
                        'precio_publico' => (float) $ud->precio_publico,
                        'comision_publico' => (float) $ud->comision_publico,
                        'precio_especial' => (float) $ud->precio_especial,
                        'comision_especial' => (float) $ud->comision_especial,
                        'activador_especial' => $ud->activador_especial ? (float) $ud->activador_especial : null,
                        'precio_minimo' => (float) $ud->precio_minimo,
                        'comision_minimo' => (float) $ud->comision_minimo,
                        'activador_minimo' => $ud->activador_minimo ? (float) $ud->activador_minimo : null,
                        'precio_ultimo' => $ud->precio_ultimo ? (float) $ud->precio_ultimo : null,
                        'comision_ultimo' => (float) $ud->comision_ultimo,
                        'activador_ultimo' => $ud->activador_ultimo ? (float) $ud->activador_ultimo : null,
                        'unidad_derivada' => $ud->unidadDerivada,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * Actualiza producto completo:
     * - Producto (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Precios (DELETE ALL + CREATE MANY en productoalmacenunidadderivada)
     *
     * NOTA: NO modifica stock (campo compra se ignora)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            // Campos de Producto
            'cod_producto' => 'nullable|string|unique:producto,cod_producto,' . $id,
            'cod_barra' => 'nullable|string|unique:producto,cod_barra,' . $id,
            'name' => 'required|string|unique:producto,name,' . $id,
            'name_ticket' => 'required|string',
            'categoria_id' => 'required|exists:categoria,id',
            'marca_id' => 'required|exists:marca,id',
            'unidad_medida_id' => 'required|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'img' => 'nullable|string',
            'ficha_tecnica' => 'nullable|string',
            'stock_min' => 'required|numeric|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'unidades_contenidas' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'permitido' => 'nullable|boolean',

            // Contexto
            'almacen_id' => 'required|exists:almacen,id',

            // ProductoAlmacen
            'producto_almacen' => 'required|array',
            'producto_almacen.ubicacion_id' => 'required|exists:ubicacion,id',

            // Unidades Derivadas (Precios)
            'unidades_derivadas' => 'required|array|min:1',
            'unidades_derivadas.*.unidad_derivada_id' => 'required|exists:unidadderivada,id',
            'unidades_derivadas.*.factor' => 'required|numeric|min:0',
            'unidades_derivadas.*.precio_publico' => 'required|numeric|min:0',
            'unidades_derivadas.*.comision_publico' => 'nullable|numeric',
            'unidades_derivadas.*.precio_especial' => 'nullable|numeric',
            'unidades_derivadas.*.comision_especial' => 'nullable|numeric',
            'unidades_derivadas.*.activador_especial' => 'nullable|numeric',
            'unidades_derivadas.*.precio_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.precio_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.costo' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            // PASO 1: Buscar producto
            $producto = Producto::findOrFail($id);

            // PASO 2: Auto-generar cod_producto si no existe
            if (empty($validated['cod_producto'])) {
                $ultimoProducto = Producto::latest('id')->first();
                $validated['cod_producto'] = (string) ($ultimoProducto ? $ultimoProducto->id + 1 : 1);
            }

            // PASO 3: Actualizar Producto (evitar unique constraint en campos sin cambios)
            $productoActual = Producto::find($id);
            $dataToUpdate = [
                'cod_producto' => $productoActual->cod_producto === $validated['cod_producto']
                    ? $productoActual->cod_producto
                    : $validated['cod_producto'],
                'cod_barra' => $validated['cod_barra'] ?? null,
                'name' => $productoActual->name === $validated['name']
                    ? $productoActual->name
                    : $validated['name'],
                'name_ticket' => $validated['name_ticket'],
                'categoria_id' => $validated['categoria_id'],
                'marca_id' => $validated['marca_id'],
                'unidad_medida_id' => $validated['unidad_medida_id'],
                'accion_tecnica' => $validated['accion_tecnica'] ?? null,
                'img' => $validated['img'] ?? null,
                'ficha_tecnica' => $validated['ficha_tecnica'] ?? null,
                'stock_min' => $validated['stock_min'],
                'stock_max' => $validated['stock_max'] ?? null,
                'unidades_contenidas' => $validated['unidades_contenidas'],
                'estado' => $validated['estado'] ?? true,
                'permitido' => $validated['permitido'] ?? true,
            ];

            $producto->update($dataToUpdate);

            // PASO 4: Actualizar ProductoAlmacen
            $unidadesDerivadas = $validated['unidades_derivadas'];
            $costoUnidad = count($unidadesDerivadas) > 0
                ? $unidadesDerivadas[0]['costo'] / $unidadesDerivadas[0]['factor']
                : 0;

            $productoAlmacen = ProductoAlmacen::where('producto_id', $id)
                ->where('almacen_id', $validated['almacen_id'])
                ->firstOrFail();

            $productoAlmacen->update([
                'costo' => $costoUnidad,
                'ubicacion_id' => $validated['producto_almacen']['ubicacion_id'],
            ]);

            // PASO 5: ELIMINAR TODOS los precios existentes
            ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacen->id)->delete();

            // PASO 6: CREAR NUEVOS precios (reemplazo completo)
            $preciosData = array_map(function($item) use ($productoAlmacen) {
                return [
                    'producto_almacen_id' => $productoAlmacen->id,
                    'unidad_derivada_id' => $item['unidad_derivada_id'],
                    'factor' => $item['factor'],
                    'precio_publico' => $item['precio_publico'],
                    'comision_publico' => $item['comision_publico'] ?? 0,
                    'precio_especial' => $item['precio_especial'] ?? 0,
                    'comision_especial' => $item['comision_especial'] ?? 0,
                    'activador_especial' => $item['activador_especial'] ?? null,
                    'precio_minimo' => $item['precio_minimo'] ?? 0,
                    'comision_minimo' => $item['comision_minimo'] ?? 0,
                    'activador_minimo' => $item['activador_minimo'] ?? null,
                    'precio_ultimo' => $item['precio_ultimo'] ?? null,
                    'comision_ultimo' => $item['comision_ultimo'] ?? 0,
                    'activador_ultimo' => $item['activador_ultimo'] ?? null,
                ];
            }, $unidadesDerivadas);

            ProductoAlmacenUnidadDerivada::insert($preciosData);

            // Cargar relaciones para la respuesta
            $producto->load(['categoria', 'marca', 'unidadMedida']);

            return response()->json([
                'data' => $producto,
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * Elimina producto con validaciones:
     * - Verifica que no tenga más de 1 compra
     * - Si tiene 1 compra, verifica que sea stock inicial
     * - Elimina la compra de stock inicial
     * - Elimina el producto (cascada elimina producto_almacen y precios)
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $producto = Producto::findOrFail($id);

            // PASO 1: Buscar compras relacionadas con el producto
            $compras = Compra::whereHas('productosPorAlmacen', function ($q) use ($id) {
                    $q->whereHas('productoAlmacen', function ($paq) use ($id) {
                        $paq->where('producto_id', $id);
                    });
                })
                ->orderBy('created_at', 'asc')
                ->select('id', 'descripcion')
                ->take(2)
                ->get();

            // PASO 2: Validar que no tenga más de 1 compra
            if ($compras->count() > 1) {
                return response()->json([
                    'message' => 'El producto tiene compras realizadas',
                ], 400);
            }

            // PASO 3: Si tiene 1 compra, verificar que sea el stock inicial
            if ($compras->count() === 1) {
                $compra = $compras->first();
                if ($compra->descripcion !== 'Stock inicial por creación de producto') {
                    return response()->json([
                        'message' => 'El producto tiene compras realizadas',
                    ], 400);
                }

                // Eliminar la compra de stock inicial
                $compra->delete();
            }

            // PASO 4: Eliminar el producto (cascada elimina producto_almacen y precios)
            $producto->delete();

            return response()->json([
                'message' => 'Producto eliminado exitosamente',
            ]);
        });
    }

    /**
     * Import products from Excel.
     *
     * POST /api/productos/import
     *
     * Importa productos en lotes de 50:
     * - Crea producto + producto_almacen
     * - Ajusta costo dividiéndolo por unidades_contenidas
     * - Retorna duplicados (productos que ya existen)
     */
    public function import(Request $request): JsonResponse
    {
        // Aumentar límites de tiempo y memoria
        set_time_limit(600); // 10 minutos
        ini_set('memory_limit', '512M');

        // Validación básica (sin validar cada item individualmente)
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $productos = $validated['data'];
        $duplicados = [];
        $totalProductos = count($productos);

        // OPTIMIZACIÓN 1: Pre-cargar todos los catálogos en memoria (categorías, marcas, unidades)
        $categoriasCache = [];
        $marcasCache = [];
        $unidadesMedidaCache = [];

        // Extraer todos los nombres únicos de categorías, marcas y unidades antes del loop
        $categoriasNombres = [];
        $marcasNombres = [];
        $unidadesMedidaNombres = [];

        foreach ($productos as $item) {
            // Categorías
            if (isset($item['categoria']['connectOrCreate']['where']['name'])) {
                $categoriasNombres[] = $item['categoria']['connectOrCreate']['where']['name'];
            }
            // Marcas
            if (isset($item['marca']['connectOrCreate']['where']['name'])) {
                $marcasNombres[] = $item['marca']['connectOrCreate']['where']['name'];
            }
            // Unidades de medida
            if (isset($item['unidad_medida']['connectOrCreate']['where']['name'])) {
                $unidadesMedidaNombres[] = $item['unidad_medida']['connectOrCreate']['where']['name'];
            }
        }

        // Obtener todas las categorías en una sola query
        $categoriasExistentes = Categoria::whereIn('name', $categoriasNombres)->get()->keyBy('name');
        foreach ($categoriasNombres as $nombre) {
            if (isset($categoriasExistentes[$nombre])) {
                $categoriasCache[$nombre] = $categoriasExistentes[$nombre]->id;
            } else {
                // Crear nueva categoría
                $nuevaCategoria = Categoria::create(['name' => $nombre]);
                $categoriasCache[$nombre] = $nuevaCategoria->id;
            }
        }

        // Obtener todas las marcas en una sola query
        $marcasExistentes = Marca::whereIn('name', $marcasNombres)->get()->keyBy('name');
        foreach ($marcasNombres as $nombre) {
            if (isset($marcasExistentes[$nombre])) {
                $marcasCache[$nombre] = $marcasExistentes[$nombre]->id;
            } else {
                // Crear nueva marca
                $nuevaMarca = Marca::create(['name' => $nombre, 'estado' => true]);
                $marcasCache[$nombre] = $nuevaMarca->id;
            }
        }

        // Obtener todas las unidades de medida en una sola query
        $unidadesMedidaExistentes = UnidadMedida::whereIn('name', $unidadesMedidaNombres)->get()->keyBy('name');
        foreach ($unidadesMedidaNombres as $nombre) {
            if (isset($unidadesMedidaExistentes[$nombre])) {
                $unidadesMedidaCache[$nombre] = $unidadesMedidaExistentes[$nombre]->id;
            } else {
                // Crear nueva unidad de medida
                $nuevaUnidad = UnidadMedida::create(['name' => $nombre, 'estado' => true]);
                $unidadesMedidaCache[$nombre] = $nuevaUnidad->id;
            }
        }

        // Dividir en lotes de 50 productos (ajustado para transacciones más pequeñas)
        $lotes = array_chunk($productos, 50);

        foreach ($lotes as $loteIndex => $lote) {
            try {
                DB::transaction(function () use ($lote, &$duplicados, $categoriasCache, $marcasCache, $unidadesMedidaCache) {
                    foreach ($lote as $item) {
                        try {
                            $productoEnAlmacenesCreate = $item['producto_en_almacenes']['create'];
                            unset($item['producto_en_almacenes']);

                            // Ajustar costo dividiendo por unidades_contenidas
                            $costoAjustado = $productoEnAlmacenesCreate['costo'] / $item['unidades_contenidas'];

                            // Extraer nombres de la estructura Prisma connectOrCreate
                            $categoriaNombre = null;
                            if (isset($item['categoria']['connectOrCreate']['where']['name'])) {
                                $categoriaNombre = $item['categoria']['connectOrCreate']['where']['name'];
                            } elseif (isset($item['categoria_id'])) {
                                $categoriaNombre = $item['categoria_id'];
                            }

                            $marcaNombre = null;
                            if (isset($item['marca']['connectOrCreate']['where']['name'])) {
                                $marcaNombre = $item['marca']['connectOrCreate']['where']['name'];
                            } elseif (isset($item['marca_id'])) {
                                $marcaNombre = $item['marca_id'];
                            }

                            $unidadMedidaNombre = null;
                            if (isset($item['unidad_medida']['connectOrCreate']['where']['name'])) {
                                $unidadMedidaNombre = $item['unidad_medida']['connectOrCreate']['where']['name'];
                            } elseif (isset($item['unidad_medida_id'])) {
                                $unidadMedidaNombre = $item['unidad_medida_id'];
                            }

                            // Resolver IDs usando el cache en lugar de firstOrCreate
                            $categoriaId = is_numeric($categoriaNombre)
                                ? $categoriaNombre
                                : ($categoriasCache[$categoriaNombre] ?? null);

                            $marcaId = is_numeric($marcaNombre)
                                ? $marcaNombre
                                : ($marcasCache[$marcaNombre] ?? null);

                            $unidadMedidaId = is_numeric($unidadMedidaNombre)
                                ? $unidadMedidaNombre
                                : ($unidadesMedidaCache[$unidadMedidaNombre] ?? null);

                            if (!$categoriaId || !$marcaId || !$unidadMedidaId) {
                                throw new \Exception('IDs de catálogo no encontrados');
                            }

                            // Crear producto
                            $producto = Producto::create([
                                'cod_producto' => $item['cod_producto'] ?? null,
                                'cod_barra' => $item['cod_barra'] ?? null,
                                'name' => $item['name'],
                                'name_ticket' => $item['name_ticket'],
                                'categoria_id' => $categoriaId,
                                'marca_id' => $marcaId,
                                'unidad_medida_id' => $unidadMedidaId,
                                'accion_tecnica' => $item['accion_tecnica'] ?? null,
                                'stock_min' => $item['stock_min'] ?? 0,
                                'stock_max' => $item['stock_max'] ?? null,
                                'unidades_contenidas' => $item['unidades_contenidas'],
                                'permitido' => $item['permitido'] ?? true, // Por defecto true (igual que CREATE/UPDATE manual)
                                'estado' => true,
                            ]);

                            // Crear producto_almacen
                            ProductoAlmacen::create([
                                'producto_id' => $producto->id,
                                'almacen_id' => $productoEnAlmacenesCreate['almacen_id'],
                                'stock_fraccion' => $productoEnAlmacenesCreate['stock_fraccion'],
                                'costo' => $costoAjustado,
                                'ubicacion_id' => $productoEnAlmacenesCreate['ubicacion_id'],
                            ]);

                        } catch (\Illuminate\Database\QueryException $e) {
                            // Error P2002 (unique constraint violation)
                            if ($e->getCode() === '23000') {
                                $duplicados[] = $item;
                                continue;
                            }
                            throw $e;
                        }
                    }
                }, 3); // 3 intentos en caso de deadlock

            } catch (\Exception $e) {
                // Lote cancelado por error crítico - continuar con el siguiente
                continue;
            }
        }

        $importados = $totalProductos - count($duplicados);

        return response()->json([
            'data' => $duplicados,
            'message' => "{$importados} productos importados, " . count($duplicados) . " duplicados",
        ]);
    }

    /**
     * Validar si un código de producto ya existe
     *
     * GET /api/productos/validar-codigo?cod_producto=ABC123
     */
    public function validarCodigo(Request $request): JsonResponse
    {
        $request->validate([
            'cod_producto' => 'required|string',
        ]);

        $producto = Producto::where('cod_producto', $request->cod_producto)
            ->select('cod_producto')
            ->first();

        return response()->json([
            'data' => $producto?->cod_producto,
        ]);
    }
}

