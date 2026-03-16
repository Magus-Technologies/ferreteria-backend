<?php

namespace App\Http\Controllers;

use App\Models\ValeCompra;
use App\Models\ValeCompraAplicado;
use App\Models\ValeCompraHistorial;
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
            'modalidad' => [
                'required',
                Rule::in(['CANTIDAD_MINIMA', 'POR_CATEGORIA', 'POR_PRODUCTOS', 'MIXTO'])
            ],
            'cantidad_minima' => 'required|numeric|min:0.001',

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
                'min:0'
            ],

            // Para producto gratis
            'producto_gratis_id' => [
                'nullable',
                Rule::requiredIf($request->tipo_promocion === 'PRODUCTO_GRATIS'),
                'exists:producto,id'
            ],
            'cantidad_producto_gratis' => 'nullable|numeric|min:0.001',
            
            // Vigencia
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'fecha_validez_vale' => [
                'nullable',
                Rule::requiredIf($request->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA'),
                'date'
            ],
            
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
            'modalidad' => [
                'sometimes',
                Rule::in(['CANTIDAD_MINIMA', 'POR_CATEGORIA', 'POR_PRODUCTOS', 'MIXTO'])
            ],
            'cantidad_minima' => 'sometimes|numeric|min:0.001',
            'descuento_tipo' => ['nullable', Rule::in(['PORCENTAJE', 'MONTO_FIJO'])],
            'descuento_valor' => 'nullable|numeric|min:0',
            'producto_gratis_id' => 'nullable|exists:producto,id',
            'cantidad_producto_gratis' => 'nullable|numeric|min:0.001',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'nullable|date',
            'fecha_validez_vale' => 'nullable|date',
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
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'exists:producto,id',
        ]);

        DB::beginTransaction();

        try {
            $datosAnteriores = $vale->toArray();

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
     * Obtener vales aplicables para una venta
     */
    public function valesAplicables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cantidad_total' => 'required|numeric|min:0',
            'categoria_ids' => 'nullable|array',
            'categoria_ids.*' => 'integer',
            'producto_ids' => 'nullable|array',
            'producto_ids.*' => 'integer',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
        ]);

        $vales = ValeCompra::activos()
            ->vigentes()
            ->where('cantidad_minima', '<=', $validated['cantidad_total'])
            ->with(['productoGratis', 'categorias', 'productos'])
            ->get();

        // Filtrar por modalidad y restricciones
        $valesAplicables = $vales->filter(function($vale) use ($validated) {
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
     */
    public function verificarCodigoVale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50',
        ]);

        $codigo = $validated['codigo'];

        // 1. Buscar como código generado (VCC-...) en vales_compra_aplicados
        $valeGenerado = ValeCompraAplicado::where('codigo_vale_generado', $codigo)
            ->where('genera_vale_futuro', true)
            ->where('usado', false)
            ->first();

        if ($valeGenerado) {
            // Verificar vigencia
            if ($valeGenerado->fecha_validez_generado && $valeGenerado->fecha_validez_generado->isPast()) {
                return response()->json([
                    'valido' => false,
                    'message' => 'Este vale ha expirado.',
                ]);
            }

            $valeCompra = $valeGenerado->valeCompra;
            $valeCompra?->load(['productos:id,cod_producto,name', 'categorias:id,name']);

            $valeData = [
                'id' => $valeCompra?->id,
                'codigo' => $codigo,
                'nombre' => $valeCompra?->nombre ?? 'Vale de descuento',
                'tipo_promocion' => $valeCompra?->tipo_promocion,
                'descuento_tipo' => $valeCompra?->descuento_tipo ?? $valeGenerado->descuento_tipo,
                'descuento_valor' => $valeCompra?->descuento_valor ?? $valeGenerado->descuento_aplicado,
                'modalidad' => $valeCompra?->modalidad,
                'cantidad_minima' => $valeCompra?->cantidad_minima ?? 0,
                'fecha_inicio' => $valeCompra?->fecha_inicio,
                'fecha_fin' => $valeGenerado->fecha_validez_generado?->format('Y-m-d'),
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
                ],
                'message' => 'Vale valido. Se aplicara el descuento al crear la venta.',
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

        $vale->load(['productos:id,cod_producto,name', 'categorias:id,name']);

        $valeData = $vale->only([
            'id', 'codigo', 'nombre', 'tipo_promocion',
            'descuento_tipo', 'descuento_valor', 'modalidad',
            'cantidad_minima', 'fecha_inicio', 'fecha_fin',
        ]);

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
}
