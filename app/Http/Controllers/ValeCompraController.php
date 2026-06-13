<?php

namespace App\Http\Controllers;

use App\Models\ValeCompra;
use App\Models\ValeCompraAplicado;
use App\Models\ValeCompraHistorial;
use App\Services\ValeCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ValeCompraController extends Controller
{
    /**
     * Listar todos los vales de compra
     */
    public function index(Request $request): JsonResponse
    {
        $query = ValeCompra::with([
            'productoGratis:id,name,cod_producto',
            'categorias:id,name',
            'productos:id,name,cod_producto',
            'creador:id,name',
            'editor:id,name',
        ]);

        // Filtros opcionales
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_promocion')) {
            $query->where('tipo_promocion', $request->tipo_promocion);
        }

        if ($request->has('modalidad')) {
            $query->where('modalidad', $request->modalidad);
        }

        if ($request->has('vigentes')) {
            if ($request->vigentes) {
                $query->vigentes();
            }
        }

        if ($request->has('activos')) {
            if ($request->activos) {
                $query->activos();
            }
        }

        // Filtro por rango de fechas
        if ($request->has('desde')) {
            $query->where('fecha_inicio', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->where('fecha_inicio', '<=', $request->hasta);
        }

        // Búsqueda por código o nombre
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo', 'like', '%' . $search . '%')
                  ->orWhere('nombre', 'like', '%' . $search . '%');
            });
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $vales = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($vales);
    }

    /**
     * Obtener un vale de compra específico
     */
    public function show(int $id): JsonResponse
    {
        $vale = ValeCompra::with([
            'productoGratis:id,name,cod_producto',
            'categorias:id,name',
            'productos:id,name,cod_producto',
            'creador:id,name',
            'editor:id,name',
            'aplicaciones' => function($query) {
                $query->with(['venta:id,numero,fecha', 'cliente:id,nombres,apellidos,razon_social'])
                      ->latest('fecha_aplicacion')
                      ->limit(10);
            },
            'historial' => function($query) {
                $query->with('usuario:id,name')
                      ->latest('fecha')
                      ->limit(20);
            },
        ])->findOrFail($id);

        // Nombres de los productos del descuento (descuento_producto_ids es un
        // JSON de ids sin relación): el form de edición los necesita para
        // mostrar nombres en vez de ids.
        $descuentoIds = $vale->descuento_producto_ids ?? [];
        $vale->setAttribute(
            'descuento_productos',
            ! empty($descuentoIds)
                ? \App\Models\Producto::whereIn('id', $descuentoIds)->get(['id', 'name', 'cod_producto'])
                : []
        );

        return response()->json($vale);
    }

    /**
     * Crear un nuevo vale de compra
     */
    public function store(Request $request): JsonResponse
    {
        \Log::info('📦 Request recibido en store:', [
            'all' => $request->all(),
            'json' => $request->json()->all(),
            'input' => $request->input(),
            'method' => $request->method(),
            'content-type' => $request->header('Content-Type'),
        ]);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_promocion' => [
                'required',
                Rule::in(['SORTEO', 'DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA', 'PRODUCTO_GRATIS', 'DOS_POR_UNO'])
            ],
            // Cuándo se aplica el beneficio (independiente del tipo_promocion).
            // Permite que PRODUCTO_GRATIS / DOS_POR_UNO / SORTEO también se entreguen
            // como código para canjear en una compra posterior.
            'momento_aplicacion' => [
                'nullable',
                Rule::in(['MISMA_COMPRA', 'PROXIMA_COMPRA']),
            ],
            'modalidad' => [
                'required',
                Rule::in(['CANTIDAD_MINIMA', 'POR_CATEGORIA', 'POR_PRODUCTOS', 'MIXTO'])
            ],
            'cantidad_minima' => [
                'nullable',
                Rule::requiredIf($request->tipo_promocion !== 'SORTEO'),
                'numeric',
                'min:0',
            ],
            // Cómo se interpreta `cantidad_minima`: MONTO (soles) o CANTIDAD (unidades).
            // Lo elige el usuario en el formulario. Para PRODUCTO_GRATIS / DOS_POR_UNO
            // siempre es CANTIDAD (se normaliza más abajo).
            'tipo_umbral' => [
                'nullable',
                Rule::in(['MONTO', 'CANTIDAD', 'NINGUNO']),
            ],
            'max_vales_por_venta' => ['nullable', 'integer', 'min:1'],

            // Para descuentos
            'descuento_tipo' => [
                'nullable',
                Rule::requiredIf(in_array($request->tipo_promocion, ['DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA'])),
                Rule::in(['PORCENTAJE', 'MONTO_FIJO'])
            ],
            'descuento_valor' => [
                'nullable',
                Rule::requiredIf(in_array($request->tipo_promocion, ['DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA'])),
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->descuento_tipo === 'PORCENTAJE' && $value > 100) {
                        $fail('El descuento porcentual no puede ser mayor a 100%.');
                    }
                    // Solo comparar monto contra cantidad_minima cuando el umbral es PRECIO (S/).
                    // El umbral es por unidades si el tipo lo exige (PRODUCTO_GRATIS / DOS_POR_UNO)
                    // o si el usuario eligió CANTIDAD (fallback: modalidad POR_PRODUCTOS / MIXTO).
                    $umbralEsUnidades = in_array($request->tipo_promocion, ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)
                        || ($request->tipo_umbral
                            ? $request->tipo_umbral === 'CANTIDAD'
                            : in_array($request->modalidad, ['POR_PRODUCTOS', 'MIXTO'], true));
                    if (
                        $request->descuento_tipo === 'MONTO_FIJO'
                        && !$umbralEsUnidades
                        && $request->cantidad_minima
                        && $value > $request->cantidad_minima
                    ) {
                        $fail('El descuento en monto no puede exceder la compra mínima.');
                    }
                },
            ],

            // DESTINO del descuento (recompensa, independiente de la condición del PASO 3).
            'descuento_alcance' => ['nullable', Rule::in(['VENTA', 'PRODUCTOS', 'CATEGORIAS'])],
            'descuento_producto_ids' => ['nullable', 'array'],
            'descuento_producto_ids.*' => ['integer', 'exists:producto,id'],
            'descuento_categoria_ids' => ['nullable', 'array'],
            'descuento_categoria_ids.*' => ['integer', 'exists:categoria,id'],
            // Filtro opcional por marca del DESTINO del descuento (PASO 4).
            'descuento_marca_ids' => ['nullable', 'array'],
            'descuento_marca_ids.*' => ['integer', 'exists:marca,id'],

            // Para producto gratis y SORTEO con producto
            'producto_gratis_id' => [
                'nullable',
                Rule::requiredIf($request->tipo_promocion === 'PRODUCTO_GRATIS'),
                Rule::requiredIf($request->input('sorteo_incluye_producto') === true || $request->input('sorteo_incluye_producto') === 'true'),
                'exists:producto,id'
            ],
            'cantidad_producto_gratis' => 'nullable|numeric|min:0.001',
            'dos_por_uno_cantidad_compra' => ['nullable', 'numeric', 'min:1'],
            
            // Para SORTEO (default false en la migración, no hace falta requerirlo)
            'sorteo_incluye_producto' => 'nullable|boolean',
            
            // Vigencia del vale en sí (hasta cuándo puede generar/aplicarse).
            'fecha_inicio' => 'required|date',
            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],
            // Fecha límite (fija) del código generado al cliente (PROXIMA_COMPRA).
            // El cliente puede canjear su código hasta esta fecha.
            'fecha_validez_vale' => [
                'nullable',
                Rule::requiredIf($request->momento_aplicacion === 'PROXIMA_COMPRA'),
                'date',
                'after_or_equal:fecha_inicio',
            ],
            // Días de validez (legado / compatibilidad con vales antiguos).
            'dias_validez_vale' => ['nullable', 'integer', 'min:1'],
            
            // Restricciones
            'usa_limite_por_cliente' => 'boolean',
            'limite_usos_cliente' => 'nullable|integer|min:1',
            'usa_limite_stock' => 'boolean',
            'stock_disponible' => 'nullable|integer|min:1',
            
            // Aplicable a precios
            'aplica_precio_publico' => 'boolean',
            'aplica_precio_especial' => 'boolean',
            'aplica_precio_minimo' => 'boolean',
            'aplica_precio_ultimo' => 'boolean',
            
            // Relaciones
            'categoria_ids' => [
                'nullable',
                Rule::requiredIf(in_array($request->modalidad, ['POR_CATEGORIA', 'MIXTO'])),
                'array'
            ],
            'categoria_ids.*' => 'exists:categoria,id',
            // Filtro opcional por marca de la CONDICIÓN para ganar (PASO 3).
            'marca_ids' => ['nullable', 'array'],
            'marca_ids.*' => 'exists:marca,id',
            'producto_ids' => [
                'nullable',
                Rule::requiredIf(in_array($request->modalidad, ['POR_PRODUCTOS', 'MIXTO'])),
                'array'
            ],
            'producto_ids.*' => 'exists:producto,id',
        ]);

        DB::beginTransaction();

        try {
            // Generar código único
            $codigo = ValeCompra::generarNuevoCodigo();

            // Normalizar tipo_umbral SOLO si el form no lo envió: se respeta la
            // elección explícita del usuario (incluido NINGUNO, válido para todos
            // los tipos). Sin umbral elegido, PRODUCTO_GRATIS/DOS_POR_UNO van por
            // unidades y el resto se infiere por modalidad (compatibilidad).
            if (empty($validated['tipo_umbral'])) {
                $validated['tipo_umbral'] = in_array($validated['tipo_promocion'], ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)
                    || in_array($validated['modalidad'], ['POR_PRODUCTOS', 'MIXTO'], true)
                    ? 'CANTIDAD' : 'MONTO';
            }

            $vale = ValeCompra::create([
                ...$validated,
                'codigo' => $codigo,
                'estado' => 'ACTIVO',
                'created_by' => auth()->id(),
            ]);

            // Asociar categorías si aplica
            if (isset($validated['categoria_ids']) && !empty($validated['categoria_ids'])) {
                $vale->categorias()->sync($validated['categoria_ids']);
            }

            // Asociar productos si aplica
            if (isset($validated['producto_ids']) && !empty($validated['producto_ids'])) {
                $vale->productos()->sync($validated['producto_ids']);
            }

            // Registrar en historial
            ValeCompraHistorial::registrar(
                $vale->id,
                'CREADO',
                'Vale de compra creado',
                null,
                $vale->toArray(),
                auth()->id()
            );

            DB::commit();

            // Cargar relaciones
            $vale->load(['productoGratis', 'categorias', 'productos', 'creador']);

            return response()->json([
                'message' => 'Vale de compra creado exitosamente',
                'data' => $vale
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear vale de compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un vale de compra
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $vale = ValeCompra::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_promocion' => [
                'sometimes',
                Rule::in(['SORTEO', 'DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA', 'PRODUCTO_GRATIS', 'DOS_POR_UNO'])
            ],
            'momento_aplicacion' => [
                'sometimes',
                'nullable',
                Rule::in(['MISMA_COMPRA', 'PROXIMA_COMPRA']),
            ],
            'modalidad' => [
                'sometimes',
                Rule::in(['CANTIDAD_MINIMA', 'POR_CATEGORIA', 'POR_PRODUCTOS', 'MIXTO'])
            ],
            'cantidad_minima' => 'sometimes|numeric|min:0',
            'tipo_umbral' => ['sometimes', 'nullable', Rule::in(['MONTO', 'CANTIDAD', 'NINGUNO'])],
            'max_vales_por_venta' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'descuento_tipo' => ['nullable', Rule::in(['PORCENTAJE', 'MONTO_FIJO'])],
            'descuento_valor' => 'nullable|numeric|min:0',
            'descuento_alcance' => ['sometimes', 'nullable', Rule::in(['VENTA', 'PRODUCTOS', 'CATEGORIAS'])],
            'descuento_producto_ids' => ['sometimes', 'nullable', 'array'],
            'descuento_producto_ids.*' => ['integer', 'exists:producto,id'],
            'descuento_categoria_ids' => ['sometimes', 'nullable', 'array'],
            'descuento_categoria_ids.*' => ['integer', 'exists:categoria,id'],
            'descuento_marca_ids' => ['sometimes', 'nullable', 'array'],
            'descuento_marca_ids.*' => ['integer', 'exists:marca,id'],
            'producto_gratis_id' => 'nullable|exists:producto,id',
            'cantidad_producto_gratis' => 'nullable|numeric|min:0.001',
            'dos_por_uno_cantidad_compra' => ['nullable', 'numeric', 'min:1'],
            'sorteo_incluye_producto' => 'nullable|boolean',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'nullable|date',
            'fecha_validez_vale' => 'nullable|date',
            'dias_validez_vale' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'usa_limite_por_cliente' => 'boolean',
            'limite_usos_cliente' => 'nullable|integer|min:1',
            'usa_limite_stock' => 'boolean',
            'stock_disponible' => 'nullable|integer|min:1',
            'aplica_precio_publico' => 'boolean',
            'aplica_precio_especial' => 'boolean',
            'aplica_precio_minimo' => 'boolean',
            'aplica_precio_ultimo' => 'boolean',
            'categoria_ids' => 'nullable|array',
            'categoria_ids.*' => 'exists:categoria,id',
            'marca_ids' => ['sometimes', 'nullable', 'array'],
            'marca_ids.*' => 'exists:marca,id',
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'exists:producto,id',
        ]);

        DB::beginTransaction();

        try {
            $datosAnteriores = $vale->toArray();

            // Normalizar tipo_umbral SOLO si vino vacío en el request: se respeta la
            // elección explícita del usuario (incluido NINGUNO, válido para todos los tipos).
            $tipoPromo = $validated['tipo_promocion'] ?? $vale->tipo_promocion;
            $modalidadFinal = $validated['modalidad'] ?? $vale->modalidad;
            if (array_key_exists('tipo_umbral', $validated) && empty($validated['tipo_umbral'])) {
                $validated['tipo_umbral'] = in_array($tipoPromo, ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)
                    || in_array($modalidadFinal, ['POR_PRODUCTOS', 'MIXTO'], true)
                    ? 'CANTIDAD' : 'MONTO';
            }

            $vale->update([
                ...$validated,
                'updated_by' => auth()->id(),
            ]);

            // Actualizar relaciones si se proporcionan
            if (isset($validated['categoria_ids'])) {
                $vale->categorias()->sync($validated['categoria_ids']);
            }

            if (isset($validated['producto_ids'])) {
                $vale->productos()->sync($validated['producto_ids']);
            }

            // Registrar en historial
            ValeCompraHistorial::registrar(
                $vale->id,
                'MODIFICADO',
                'Vale de compra actualizado',
                $datosAnteriores,
                $vale->fresh()->toArray(),
                auth()->id()
            );

            DB::commit();

            $vale->load(['productoGratis', 'categorias', 'productos', 'editor']);

            return response()->json([
                'message' => 'Vale de compra actualizado exitosamente',
                'data' => $vale
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar vale de compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del vale
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $vale = ValeCompra::findOrFail($id);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['ACTIVO', 'PAUSADO', 'FINALIZADO'])],
        ]);

        $estadoAnterior = $vale->estado;

        $vale->update([
            'estado' => $validated['estado'],
            'updated_by' => auth()->id(),
        ]);

        // Registrar en historial
        $accion = match($validated['estado']) {
            'ACTIVO' => 'ACTIVADO',
            'PAUSADO' => 'PAUSADO',
            'FINALIZADO' => 'FINALIZADO',
        };

        ValeCompraHistorial::registrar(
            $vale->id,
            $accion,
            "Estado cambiado de {$estadoAnterior} a {$validated['estado']}",
            ['estado' => $estadoAnterior],
            ['estado' => $validated['estado']],
            auth()->id()
        );

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'data' => $vale
        ]);
    }

    /**
     * Eliminar un vale de compra
     */
    public function destroy(int $id): JsonResponse
    {
        $vale = ValeCompra::findOrFail($id);

        // Verificar si tiene aplicaciones
        if ($vale->aplicaciones()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un vale que ya ha sido aplicado'
            ], 422);
        }

        $vale->delete();

        return response()->json([
            'message' => 'Vale de compra eliminado exitosamente'
        ]);
    }

    /**
     * Obtener el precio público máximo de una lista de productos (por id).
     * Lo usa el formulario de creación de vale para validar que el "Precio Mínimo"
     * sea mayor al precio público del producto seleccionado.
     */
    public function preciosProductos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'producto_ids' => 'required|array',
            'producto_ids.*' => 'integer',
        ]);

        // Trae el precio público MAX entre todas las unidades derivadas del producto
        // en cualquiera de sus almacenes. Usamos MAX porque "precio publico" puede
        // variar por unidad (la unidad base suele tener el precio más alto).
        $rows = \DB::table('producto_almacen_unidad_derivada as udd')
            ->join('producto_almacen as pa', 'pa.id', '=', 'udd.producto_almacen_id')
            ->whereIn('pa.producto_id', $validated['producto_ids'])
            ->select('pa.producto_id as id', \DB::raw('MAX(udd.precio_publico) as precio'))
            ->groupBy('pa.producto_id')
            ->get();

        $precios = [];
        foreach ($rows as $r) {
            $precios[(int) $r->id] = (float) $r->precio;
        }

        return response()->json(['data' => $precios]);
    }

    /**
     * Obtener el stock total (sumando todos los almacenes) de uno o varios productos.
     * Solo informativo: lo usa el formulario de vales para mostrar cuánto stock hay
     * del producto que se regalará. NO se valida contra el stock del vale.
     */
    public function stockProductos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'producto_ids' => 'required|array',
            'producto_ids.*' => 'integer',
        ]);

        $rows = \App\Models\ProductoAlmacen::whereIn('producto_id', $validated['producto_ids'])
            ->selectRaw('producto_id, SUM(stock_fraccion) as stock')
            ->groupBy('producto_id')
            ->get();

        $stocks = [];
        foreach ($rows as $r) {
            $stocks[(int) $r->producto_id] = (float) $r->stock;
        }

        return response()->json(['data' => $stocks]);
    }

    /**
     * Obtener vales aplicables para una venta
     */
    public function valesAplicables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // `precio_total` = monto total de la venta en S/ (suma de precio × cantidad por línea).
            // `cantidad_total` = suma de unidades. Se usa cuando el umbral del vale es por unidades
            // (PRODUCTO_GRATIS, DOS_POR_UNO o modalidad POR_PRODUCTOS / MIXTO).
            'precio_total' => 'required|numeric|min:0',
            'cantidad_total' => 'nullable|numeric|min:0',
            'categoria_ids' => 'nullable|array',
            'categoria_ids.*' => 'integer',
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'integer',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            // Detalle por línea (producto_id, categoria_id, cantidad, precio_total).
            // Si se envía, el umbral se mide solo sobre los productos/categoría del vale
            // (correcto para POR_PRODUCTOS / POR_CATEGORIA / MIXTO). Si no, se usa el
            // total de la venta (compatibilidad con clientes antiguos).
            'detalles' => 'nullable|array',
            'detalles.*.producto_id' => 'nullable|integer',
            'detalles.*.categoria_id' => 'nullable|integer',
            'detalles.*.cantidad' => 'nullable|numeric',
            'detalles.*.precio_total' => 'nullable|numeric',
        ]);

        // Si el frontend no envió categoria_ids, derivarlas de producto_ids.
        // Sin esto, los vales POR_CATEGORIA y MIXTO nunca se detectarían en el carrito.
        if (empty($validated['categoria_ids']) && !empty($validated['producto_ids'])) {
            $validated['categoria_ids'] = \App\Models\Producto::whereIn('id', $validated['producto_ids'])
                ->pluck('categoria_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        $cantidadTotal = (float) ($validated['cantidad_total'] ?? 0);
        $precioTotal = (float) $validated['precio_total'];

        \Log::info('🔍 valesAplicables called', [
            'precio_total' => $precioTotal,
            'cantidad_total' => $cantidadTotal,
            'producto_ids' => $validated['producto_ids'] ?? [],
            'cliente_id' => $validated['cliente_id'] ?? null,
        ]);

        $detalles = $validated['detalles'] ?? [];

        // Traemos todos los activos+vigentes y filtramos el umbral en PHP según tipo/modalidad.
        $vales = ValeCompra::activos()
            ->vigentes()
            ->with(['productoGratis', 'categorias', 'productos'])
            ->get()
            ->filter(function (ValeCompra $vale) use ($precioTotal, $cantidadTotal, $detalles) {
                // Si hay detalle por línea, medir el umbral solo sobre los productos/
                // categoría del vale (no toda la venta).
                if (!empty($detalles)) {
                    return \App\Services\ValeCompraService::cumpleUmbralScopedStatic($vale, $detalles);
                }
                $umbral = \App\Services\ValeCompraService::esUmbralPorUnidadesStatic($vale)
                    ? $cantidadTotal
                    : $precioTotal;
                return (float) $vale->cantidad_minima <= $umbral;
            })
            ->values();

        // Filtrar por modalidad y restricciones
        $valesAplicables = $vales->filter(function($vale) use ($validated) {
            // Solo MISMA_COMPRA aparece en auto-detección. PROXIMA_COMPRA es manual vía modal.
            if ($vale->momento_aplicacion === 'PROXIMA_COMPRA') {
                \Log::info('🔇 valesAplicables: EXCLUIDO por PROXIMA_COMPRA', ['id' => $vale->id, 'codigo' => $vale->codigo]);
                return false;
            }

            // Verificar stock
            if (!$vale->tieneStockDisponible()) {
                return false;
            }

            // Verificar límite por cliente
            if (isset($validated['cliente_id']) && !$vale->clientePuedeUsar($validated['cliente_id'])) {
                return false;
            }

            // Verificar modalidad
            switch ($vale->modalidad) {
                case 'CANTIDAD_MINIMA':
                    return true;

                case 'POR_CATEGORIA':
                    if (!isset($validated['categoria_ids'])) return false;
                    $categoriasVale = $vale->categorias->pluck('id')->toArray();
                    return count(array_intersect($validated['categoria_ids'], $categoriasVale)) > 0;

                case 'POR_PRODUCTOS':
                    if (!isset($validated['producto_ids'])) return false;
                    $productosVale = $vale->productos->pluck('id')->toArray();
                    return count(array_intersect($validated['producto_ids'], $productosVale)) > 0;

                case 'MIXTO':
                    if (!isset($validated['categoria_ids']) || !isset($validated['producto_ids'])) return false;
                    $categoriasVale = $vale->categorias->pluck('id')->toArray();
                    $productosVale = $vale->productos->pluck('id')->toArray();
                    $tieneCategoria = count(array_intersect($validated['categoria_ids'], $categoriasVale)) > 0;
                    $tieneProducto = count(array_intersect($validated['producto_ids'], $productosVale)) > 0;
                    return $tieneCategoria && $tieneProducto;
            }

            return false;
        })->values();

        \Log::info('✅ valesAplicables result', [
            'count' => $valesAplicables->count(),
            'codigos' => $valesAplicables->pluck('codigo')->toArray(),
        ]);

        return response()->json([
            'data' => $valesAplicables,
            'count' => $valesAplicables->count()
        ]);
    }

    /**
     * Obtener historial de aplicaciones
     */
    public function historialAplicaciones(int $id): JsonResponse
    {
        $vale = ValeCompra::findOrFail($id);

        $aplicaciones = ValeCompraAplicado::where('vale_compra_id', $id)
            ->with([
                'venta:id,numero,fecha',
                'cliente:id,nombres,apellidos,razon_social',
                'aplicadoPor:id,name'
            ])
            ->orderBy('fecha_aplicacion', 'desc')
            ->paginate(20);

        return response()->json($aplicaciones);
    }

    /**
     * Obtener vales aplicados a una venta específica
     */
    /**
     * Verificar si un código de vale es válido.
     * Busca tanto en códigos de promoción (VC-...) como en códigos generados (VCC-...).
     * Para vales regulares, también valida condiciones contra la venta si se proporcionan datos.
     */
    public function verificarCodigoVale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50',
            // Datos opcionales de la venta para validar condiciones
            'precio_total' => 'nullable|numeric|min:0',
            'cantidad_total' => 'nullable|numeric|min:0',
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'integer',
            'cliente_id' => 'nullable|integer',
            'tipos_precio' => 'nullable|array',
            'tipos_precio.*' => 'string',
            // Detalle por línea para medir el umbral solo sobre los productos/categoría
            // del vale (igual que en la aplicación real). Opcional.
            'detalles' => 'nullable|array',
            'detalles.*.producto_id' => 'nullable|integer',
            'detalles.*.categoria_id' => 'nullable|integer',
            'detalles.*.cantidad' => 'nullable|numeric',
            'detalles.*.precio_total' => 'nullable|numeric',
        ]);

        $codigo = $validated['codigo'];

        // 1. Buscar como código generado en vales_compra_aplicados.
        $valeGenerado = ValeCompraAplicado::where('codigo_vale_generado', $codigo)
            ->where('usado', false)
            ->first();

        if ($valeGenerado) {
            // fecha_validez_generado es DATE: isPast() lo daría por vencido desde la
            // medianoche del día límite; debe poder canjearse hasta ese mismo día.
            if ($valeGenerado->fecha_validez_generado && $valeGenerado->fecha_validez_generado->lt(today())) {
                return response()->json([
                    'valido' => false,
                    'message' => 'Este vale ha expirado.',
                ]);
            }

            $valeCompra = $valeGenerado->valeCompra;
            $valeCompra?->load(['productos:id,cod_producto,name', 'categorias:id,name', 'productoGratis:id,cod_producto,name']);

            $esSorteo = $valeCompra?->tipo_promocion === 'SORTEO';

            $valeData = [
                'id' => $valeCompra?->id,
                'codigo' => $codigo,
                'nombre' => $valeCompra?->nombre ?? ($esSorteo ? 'Sorteo' : 'Vale de descuento'),
                'tipo_promocion' => $valeCompra?->tipo_promocion,
                'momento_aplicacion' => $valeCompra?->momento_aplicacion,
                'descuento_tipo' => $valeCompra?->descuento_tipo ?? $valeGenerado->descuento_tipo,
                'descuento_valor' => $valeCompra?->descuento_valor ?? $valeGenerado->descuento_aplicado,
                'modalidad' => $valeCompra?->modalidad,
                'cantidad_minima' => $valeCompra?->cantidad_minima ?? 0,
                'tipo_umbral' => $valeCompra?->tipo_umbral,
                'fecha_inicio' => $valeCompra?->fecha_inicio,
                'fecha_fin' => $valeGenerado->fecha_validez_generado?->format('Y-m-d'),
                'producto_gratis' => $valeCompra?->productoGratis ? [
                    'id' => $valeCompra->productoGratis->id,
                    'nombre' => $valeCompra->productoGratis->name,
                ] : null,
                'cantidad_producto_gratis' => $valeCompra?->cantidad_producto_gratis,
                'productos' => $valeCompra?->productos?->map(fn($p) => [
                    'id' => $p->id,
                    'nombre' => $p->name,
                ])->values() ?? [],
                'categorias' => $valeCompra?->categorias?->map(fn($c) => [
                    'id' => $c->id,
                    'nombre' => $c->name,
                ])->values() ?? [],
            ];

            return response()->json([
                'valido' => true,
                'data' => [
                    'vale_compra' => $valeData,
                    'es_vale_generado' => true,
                    'es_sorteo' => $esSorteo,
                ],
                'message' => $esSorteo
                    ? 'Codigo de sorteo valido. Se registrara el canje al crear la venta.'
                    : 'Vale valido. Se aplicara el descuento al crear la venta.',
            ]);
        }

        // 2. Buscar como código de promoción (VC-...) en vales_compra
        $vale = ValeCompra::where('codigo', $codigo)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$vale) {
            return response()->json([
                'valido' => false,
                'message' => 'El codigo de vale no existe o no esta activo.',
            ]);
        }

        if (!$vale->esVigente()) {
            return response()->json([
                'valido' => false,
                'message' => 'El vale ha expirado o aun no esta vigente.',
            ]);
        }

        if (!$vale->tieneStockDisponible()) {
            return response()->json([
                'valido' => false,
                'message' => 'El vale ya no tiene stock disponible.',
            ]);
        }

        $vale->load(['productos:id,cod_producto,name', 'categorias:id,name', 'productoGratis:id,cod_producto,name']);

        // Validar condiciones contra la venta si se proporcionaron datos
        $condiciones = null;
        if (isset($validated['precio_total']) || isset($validated['cantidad_total']) || !empty($validated['detalles'])) {
            $categoriasVenta = collect($validated['producto_ids'] ?? [])
                ->map(fn($pid) => \App\Models\Producto::find($pid)?->categoria_id)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Si hay detalle por línea, el umbral se mide solo sobre los productos/
            // categoría del vale; si no, sobre el total de la venta (compatibilidad).
            $precioUmbral = (float) ($validated['precio_total'] ?? 0);
            $cantidadUmbral = (float) ($validated['cantidad_total'] ?? 0);
            if (!empty($validated['detalles'])) {
                $vale->loadMissing(['productos', 'categorias']);
                $scope = ValeCompraService::calcularUmbralScopedStatic($vale, $validated['detalles']);
                $precioUmbral = $scope['precio'];
                $cantidadUmbral = $scope['cantidad'];
            }

            $condiciones = app(ValeCompraService::class)->validarValeCondiciones(
                $vale,
                $precioUmbral,
                $cantidadUmbral,
                $categoriasVenta,
                $validated['producto_ids'] ?? [],
                $validated['tipos_precio'] ?? [],
                $validated['cliente_id'] ?? null
            );
        }

        $valeData = $vale->only([
            'id', 'codigo', 'nombre', 'tipo_promocion',
            'momento_aplicacion',
            'descuento_tipo', 'descuento_valor', 'modalidad',
            'cantidad_minima', 'tipo_umbral', 'fecha_inicio', 'fecha_fin',
        ]);

        $valeData['producto_gratis'] = $vale->productoGratis ? [
            'id' => $vale->productoGratis->id,
            'nombre' => $vale->productoGratis->name,
        ] : null;
        $valeData['cantidad_producto_gratis'] = $vale->cantidad_producto_gratis;
        $valeData['productos'] = $vale->productos->map(fn($p) => [
            'id' => $p->id,
            'nombre' => $p->name,
        ])->values();
        $valeData['categorias'] = $vale->categorias->map(fn($c) => [
            'id' => $c->id,
            'nombre' => $c->name,
        ])->values();

        return response()->json([
            'valido' => true,
            'data' => [
                'vale_compra' => $valeData,
                'es_vale_generado' => false,
                'condiciones' => $condiciones,
            ],
            'message' => 'Vale valido.',
        ]);
    }

    public function valesAplicadosVenta(string $ventaId): JsonResponse
    {
        $valesAplicados = ValeCompraAplicado::where('venta_id', $ventaId)
            ->with([
                'valeCompra:id,codigo,nombre,tipo_promocion,descuento_tipo,descuento_valor',
                'aplicadoPor:id,name'
            ])
            ->orderBy('fecha_aplicacion', 'desc')
            ->get();

        return response()->json([
            'data' => $valesAplicados,
            'count' => $valesAplicados->count()
        ]);
    }

    /**
     * Obtener vales generados pendientes de un cliente.
     * Son vales de tipo DESCUENTO_PROXIMA_COMPRA que aún no han sido canjeados.
     */
    public function valesPendientesCliente(int $clienteId): JsonResponse
    {
        $valesPendientes = ValeCompraAplicado::where('cliente_id', $clienteId)
            ->valesPendientes()
            ->with(['valeCompra:id,codigo,nombre,tipo_promocion,descuento_tipo,descuento_valor,descuento_alcance,descuento_producto_ids,descuento_categoria_ids'])
            ->get()
            ->map(function ($aplicado) {
                return [
                    'id' => $aplicado->id,
                    'codigo_vale_generado' => $aplicado->codigo_vale_generado,
                    'fecha_validez' => $aplicado->fecha_validez_generado?->format('Y-m-d'),
                    'descuento_tipo' => $aplicado->descuento_tipo ?? $aplicado->valeCompra?->descuento_tipo,
                    'descuento_aplicado' => $aplicado->descuento_aplicado ?? $aplicado->valeCompra?->descuento_valor,
                    'vale_compra' => $aplicado->valeCompra ? [
                        'id' => $aplicado->valeCompra->id,
                        'codigo' => $aplicado->valeCompra->codigo,
                        'nombre' => $aplicado->valeCompra->nombre,
                        'tipo_promocion' => $aplicado->valeCompra->tipo_promocion,
                        'descuento_tipo' => $aplicado->valeCompra->descuento_tipo,
                        'descuento_valor' => $aplicado->valeCompra->descuento_valor,
                        // Destino del descuento, para que el preview scopee bien al canjear.
                        'descuento_alcance' => $aplicado->valeCompra->descuento_alcance,
                        'descuento_producto_ids' => $aplicado->valeCompra->descuento_producto_ids,
                        'descuento_categoria_ids' => $aplicado->valeCompra->descuento_categoria_ids,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $valesPendientes,
            'count' => $valesPendientes->count(),
        ]);
    }
}
