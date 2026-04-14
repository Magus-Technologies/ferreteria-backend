<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDeCompra;
use App\Enums\EstadoDeCompraDefinitiva;
use App\Enums\FormaDePago;
use App\Enums\TipoMoneda;
use App\Models\Compra;
use App\Models\OrdenCompra;
use App\Models\DespliegueDePago;
use App\Models\EgresoDinero;
use App\Models\GastoExtra;
use App\Models\MetodoDePago;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCompra;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableCompra;
use App\Http\Resources\CompraResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\Interfaces\CompraReporteServiceInterface;

class CompraController extends Controller
{
    private ?CompraReporteServiceInterface $reporteService;

    public function __construct(CompraReporteServiceInterface $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    /**
     * Resumen mensual de compras (para gráfico)
     */
    public function resumenMensual(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'proveedor_id' => 'sometimes|integer',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta', 'proveedor_id']);
        $datos = $this->reporteService->obtenerResumenMensual($filtros);

        return response()->json(['data' => $datos]);
    }

    /**
     * Resumen de compras (para cards KPI)
     */
    public function resumenCompras(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'proveedor_id' => 'sometimes|integer',
            'forma_de_pago' => 'sometimes|string',
            'tipo_documento' => 'sometimes|string',
            'user_id' => 'sometimes|string',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta', 'proveedor_id', 'forma_de_pago', 'tipo_documento', 'user_id']);
        $resumen = $this->reporteService->obtenerResumenCompras($filtros);

        return response()->json(['data' => $resumen]);
    }

    /**
     * Reporte detallado de compras (para tabla/exportación)
     */
    public function reporteCompras(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'proveedor_id' => 'sometimes|integer',
            'forma_de_pago' => 'sometimes|string',
            'tipo_documento' => 'sometimes|string',
            'user_id' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:10000',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only(['almacen_id', 'desde', 'hasta', 'proveedor_id', 'forma_de_pago', 'tipo_documento', 'user_id']);
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->reporteService->obtenerReporteCompras($filtros, $perPage, $page);

        return response()->json($resultado);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Decodificar parámetros JSON si vienen como strings
        $estadoDeCompra = $request->input('estado_de_compra');
        if (is_string($estadoDeCompra) && (str_starts_with($estadoDeCompra, '{') || str_starts_with($estadoDeCompra, '['))) {
            $decoded = json_decode($estadoDeCompra, true);
            if ($decoded !== null) {
                $request->merge(['estado_de_compra' => $decoded]);
            }
        }
        
        $ordenCompraId = $request->input('orden_compra_id');
        if (is_string($ordenCompraId) && (str_starts_with($ordenCompraId, '{') || str_starts_with($ordenCompraId, '['))) {
            $decoded = json_decode($ordenCompraId, true);
            if ($decoded !== null) {
                $request->merge(['orden_compra_id' => $decoded]);
            }
        }

        // Validación flexible para manejar diferentes formatos
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'almacen_id' => 'sometimes|integer',
            'estado_de_cuenta' => 'sometimes|string|in:Pagado,Deuda',
            'proveedor_id' => 'sometimes|integer',
            'forma_de_pago' => 'sometimes|string',
            'tipo_documento' => 'sometimes|string',
            'user_id' => 'sometimes|string',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Compra::query()
            ->with([
                'proveedor:id,ruc,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
                'ordenCompra:id,codigo,estado',
            ])
            ->withCount([
                'recepcionesAlmacen as recepciones_almacen_count' => function ($query) {
                    $query->where('estado', true);
                },
                'pagosDeCompras as pagos_de_compras_count' => function ($query) {
                    $query->where('estado', true);
                },
            ])
            ->withSum([
                'pagosDeCompras as total_pagado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto');

        // Filter by almacen_id
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        // Filter by estado_de_compra
        if ($request->has('estado_de_compra')) {
            $estadoDeCompra = $request->input('estado_de_compra');
            
            // Handle array format: {in: ['Creado', 'Procesado']}
            if (is_array($estadoDeCompra) && isset($estadoDeCompra['in'])) {
                $query->whereIn('estado_de_compra', $estadoDeCompra['in']);
            } 
            // Handle string format: 'Creado'
            else if (is_string($estadoDeCompra)) {
                $estadoEnum = EstadoDeCompraDefinitiva::tryFrom($estadoDeCompra);
                if ($estadoEnum) {
                    $query->where('estado_de_compra', $estadoEnum->value);
                }
            }
        }

        // Filter by proveedor_id
        if ($request->has('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        // Filter by orden_compra_id
        if ($request->has('orden_compra_id')) {
            $ordenCompraFilter = $request->input('orden_compra_id');
            
            // Handle {not: null} format - solo compras que vengan de una orden
            if (is_array($ordenCompraFilter) && array_key_exists('not', $ordenCompraFilter) && $ordenCompraFilter['not'] === null) {
                $query->whereNotNull('orden_compra_id');
            }
        }

        // Filter by forma_de_pago
        if ($request->has('forma_de_pago')) {
            $formaPagoEnum = FormaDePago::tryFrom($request->forma_de_pago);
            if ($formaPagoEnum) {
                $query->where('forma_de_pago', $formaPagoEnum->value);
            }
        }

        // Filter by tipo_documento
        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by fecha range (desde/hasta)
        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Search by serie, numero, or proveedor razon_social
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'LIKE', "%{$search}%")
                    ->orWhere('numero', 'LIKE', "%{$search}%")
                    ->orWhereHas('proveedor', function ($q2) use ($search) {
                        $q2->where('razon_social', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = $request->input('per_page', 50);

        // Get all results first (we need to filter by estado_de_cuenta which requires calculation)
        $allCompras = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();

        // Filter by estado_de_cuenta if provided
        if ($request->has('estado_de_cuenta')) {
            $estadoDeCuenta = $request->input('estado_de_cuenta');
            
            $allCompras = $allCompras->filter(function ($compra) use ($estadoDeCuenta) {
                $totalCompra = $this->getTotalCompra($compra);
                $totalPagado = (float) ($compra->total_pagado ?? 0);
                $saldo = $totalCompra - $totalPagado;
                
                if ($estadoDeCuenta === 'Pagado') {
                    // Pagado: saldo <= 0.01 (considerando diferencias de redondeo)
                    return $saldo <= 0.01;
                } else if ($estadoDeCuenta === 'Deuda') {
                    // Deuda: saldo > 0.01
                    return $saldo > 0.01;
                }
                
                return true;
            });
        }

        if ($perPage === -1) {
            // Return all without pagination
            return response()->json([
                'data' => CompraResource::collection($allCompras->take(100)),
                'total' => $allCompras->count(),
            ]);
        }

        // Manual pagination
        $total = $allCompras->count();
        $currentPage = $request->input('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $comprasPaginated = $allCompras->slice($offset, $perPage)->values();
        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => CompraResource::collection($comprasPaginated),
            'total' => $total,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        \Log::info('Compra store request:', $request->all());
        
        $esEnEspera = $request->input('estado_de_compra') === 'ee';

        $validated = $request->validate([
            'id' => 'sometimes|string',
            'tipo_documento' => $esEnEspera ? 'nullable|string' : 'required|string',
            'serie' => 'nullable|string',
            'numero' => 'nullable|integer',
            'descripcion' => 'nullable|string',
            'forma_de_pago' => $esEnEspera ? 'nullable|string' : 'required|string',
            'tipo_moneda' => 'required|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'percepcion' => 'nullable|numeric',
            'numero_dias' => 'nullable|integer',
            'fecha_vencimiento' => 'nullable|date',
            'fecha' => 'required|date',
            'guia' => 'nullable|string',
            'estado_de_compra' => 'required|string',
            'egreso_dinero_id' => 'nullable|string',
            'gasto_extra_id' => 'nullable|string|exists:gastos_extras,id',
            'despliegue_de_pago_id' => 'nullable|string',
            'metodos_de_pago' => 'nullable|array',
            'metodos_de_pago.*.despliegue_de_pago_id' => 'required_with:metodos_de_pago|string',
            'metodos_de_pago.*.monto' => 'required_with:metodos_de_pago|numeric|min:0.01',
            'metodos_de_pago.*.numero_operacion' => 'nullable|string',
            'user_id' => 'required|string',
            'almacen_id' => 'required|integer',
            'proveedor_id' => $esEnEspera ? 'nullable|integer' : 'required|integer',
            'orden_compra_id' => 'nullable|integer|exists:ordenes_compra,id',
            'productos_por_almacen' => 'required|array',
            'productos_por_almacen.*.costo' => 'required|numeric',
            'productos_por_almacen.*.producto_almacen_id' => 'sometimes|integer',
            'productos_por_almacen.*.producto_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas' => 'required|array',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_name' => 'sometimes|string',
            'productos_por_almacen.*.unidades_derivadas.*.factor' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad_pendiente' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.lote' => 'nullable|string',
            'productos_por_almacen.*.unidades_derivadas.*.vencimiento' => 'nullable|date',
            'productos_por_almacen.*.unidades_derivadas.*.flete' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.bonificacion' => 'nullable|boolean',
        ]);
        
        \Log::info('Validated data:', $validated);

        return DB::transaction(function () use ($validated) {
            // Extraer el despliegue_id del formato "sub_caja_id-despliegue_id" si es necesario
            if (isset($validated['despliegue_de_pago_id']) && str_contains($validated['despliegue_de_pago_id'], '-')) {
                $parts = explode('-', $validated['despliegue_de_pago_id']);
                $validated['despliegue_de_pago_id'] = $parts[1] ?? $validated['despliegue_de_pago_id'];
            }

            // Validar orden_compra_id si se proporciona
            if (isset($validated['orden_compra_id']) && $validated['orden_compra_id']) {
                $orden = OrdenCompra::findOrFail($validated['orden_compra_id']);
                
                // Validar almacén
                if ($orden->almacen_id !== $validated['almacen_id']) {
                    throw new \Exception('El almacén de la orden no coincide con el almacén de la compra');
                }
                
                // Validar estado
                if ($orden->estado->value !== 'pendiente') {
                    throw new \Exception('La orden ya ha sido aprobada o anulada');
                }
            }

            // Validar nueva compra
            $this->validarNuevaCompra($validated);

            // Mapear tipo_documento del frontend (nombre) al valor del enum PHP
            $tipoDocumentoMap = [
                'Factura'         => '01',
                'Boleta'          => '03',
                'NotaDeVenta'     => 'nv',
                'Ingreso'         => 'in',
                'Salida'          => 'sa',
                'RecepcionAlmacen'=> 'rc',
            ];
            $validated['tipo_documento'] = isset($validated['tipo_documento'])
                ? ($tipoDocumentoMap[$validated['tipo_documento']] ?? $validated['tipo_documento'])
                : null;

            // Convert enums
            $estadoEnum = EstadoDeCompraDefinitiva::from($validated['estado_de_compra']);
            $formaDePagoEnum = isset($validated['forma_de_pago']) ? FormaDePago::from($validated['forma_de_pago']) : null;
            $tipoMonedaEnum = TipoMoneda::from($validated['tipo_moneda']);

            // Create compra
            $compra = Compra::create([
                'id' => $validated['id'] ?? (string) \Illuminate\Support\Str::ulid(),
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'serie' => $validated['serie'] ?? null,
                'numero' => $validated['numero'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'forma_de_pago' => $formaDePagoEnum,
                'tipo_moneda' => $tipoMonedaEnum,
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? null,
                'percepcion' => $validated['percepcion'] ?? null,
                'numero_dias' => $validated['numero_dias'] ?? null,
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'fecha' => $validated['fecha'],
                'guia' => $validated['guia'] ?? null,
                'estado_de_compra' => $estadoEnum,
                'egreso_dinero_id' => $validated['egreso_dinero_id'] ?? null,
                'gasto_extra_id' => $validated['gasto_extra_id'] ?? null,
                'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'] ?? null,
                'user_id' => $validated['user_id'],
                'almacen_id' => $validated['almacen_id'],
                'proveedor_id' => $validated['proveedor_id'] ?? null,
                'orden_compra_id' => $validated['orden_compra_id'] ?? null,
            ]);

            // Si la compra viene de una OC, marcar la orden como completada (aprobada)
            if (isset($validated['orden_compra_id']) && $validated['orden_compra_id']) {
                $orden = OrdenCompra::find($validated['orden_compra_id']);
                
                if ($orden->estado === EstadoDeCompra::Pendiente) {
                    $orden->update(['estado' => EstadoDeCompra::Completada]);
                }
            }

            // Create productos_por_almacen and unidades_derivadas
            foreach ($validated['productos_por_almacen'] as $producto) {
                // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                $productoAlmacenId = $producto['producto_almacen_id'] ?? null;

                if (!$productoAlmacenId && isset($producto['producto_id'])) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                        ->where('almacen_id', $validated['almacen_id'])
                        ->first();

                    if (!$productoAlmacen) {
                        throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$validated['almacen_id']}");
                    }

                    $productoAlmacenId = $productoAlmacen->id;
                }

                $productoAlmacenCompra = ProductoAlmacenCompra::create([
                    'compra_id' => $compra->id,
                    'costo' => $producto['costo'],
                    'producto_almacen_id' => $productoAlmacenId,
                ]);

                foreach ($producto['unidades_derivadas'] as $unidad) {
                    // Get unidad_derivada_inmutable_id (either provided or firstOrCreate by name)
                    $unidadDerivadaInmutableId = $unidad['unidad_derivada_inmutable_id'] ?? null;

                    if (!$unidadDerivadaInmutableId && isset($unidad['unidad_derivada_inmutable_name'])) {
                        $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                            ['name' => $unidad['unidad_derivada_inmutable_name']],
                            ['name' => $unidad['unidad_derivada_inmutable_name']]
                        );
                        $unidadDerivadaInmutableId = $unidadDerivadaInmutable->id;
                    }

                    UnidadDerivadaInmutableCompra::create([
                        'producto_almacen_compra_id' => $productoAlmacenCompra->id,
                        'unidad_derivada_inmutable_id' => $unidadDerivadaInmutableId,
                        'factor' => $unidad['factor'],
                        'cantidad' => $unidad['cantidad'],
                        'cantidad_pendiente' => $unidad['cantidad_pendiente'],
                        'lote' => $unidad['lote'] ?? null,
                        'vencimiento' => $unidad['vencimiento'] ?? null,
                        'flete' => $unidad['flete'] ?? 0,
                        'bonificacion' => $unidad['bonificacion'] ?? false,
                    ]);
                }
            }

            // Proceso post compra
            $validated['id'] = $compra->id;
            $this->procesoPostCompra($validated);

            return response()->json([
                'data' => $compra->load([
                    'proveedor:id,ruc,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'user:id,name',
                    'ordenCompra:id,codigo,estado',
                ]),
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $compra = Compra::with([
            'proveedor:id,ruc,razon_social',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'user:id,name',
            'ordenCompra:id,codigo,estado',
        ])
            ->withCount([
                'recepcionesAlmacen as recepciones_almacen_count' => function ($query) {
                    $query->where('estado', true);
                },
                'pagosDeCompras as pagos_de_compras_count' => function ($query) {
                    $query->where('estado', true);
                },
            ])
            ->withSum([
                'pagosDeCompras as total_pagado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto')
            ->findOrFail($id);

        return response()->json(['data' => new CompraResource($compra)]);
    }

    /**
     * Update the specified resource in storage (editar).
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'tipo_documento' => 'sometimes|string',
            'serie' => 'nullable|string',
            'numero' => 'nullable|integer',
            'descripcion' => 'nullable|string',
            'forma_de_pago' => 'sometimes|string',
            'tipo_moneda' => 'sometimes|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'percepcion' => 'nullable|numeric',
            'numero_dias' => 'nullable|integer',
            'fecha_vencimiento' => 'nullable|date',
            'fecha' => 'sometimes|date',
            'guia' => 'nullable|string',
            'estado_de_compra' => 'sometimes|string',
            'egreso_dinero_id' => 'nullable|string',
            'gasto_extra_id' => 'nullable|string|exists:gastos_extras,id',
            'despliegue_de_pago_id' => 'nullable|string',
            'user_id' => 'sometimes|string',
            'almacen_id' => 'sometimes|integer',
            'proveedor_id' => 'sometimes|integer',
            'productos_por_almacen' => 'sometimes|array',
            'productos_por_almacen.*.costo' => 'required|numeric',
            'productos_por_almacen.*.producto_almacen_id' => 'sometimes|integer',
            'productos_por_almacen.*.producto_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas' => 'required|array',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_name' => 'sometimes|string',
            'productos_por_almacen.*.unidades_derivadas.*.factor' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad_pendiente' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.lote' => 'nullable|string',
            'productos_por_almacen.*.unidades_derivadas.*.vencimiento' => 'nullable|date',
            'productos_por_almacen.*.unidades_derivadas.*.flete' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.bonificacion' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            // Extraer el despliegue_id del formato "sub_caja_id-despliegue_id" si es necesario
            if (isset($validated['despliegue_de_pago_id']) && str_contains($validated['despliegue_de_pago_id'], '-')) {
                $parts = explode('-', $validated['despliegue_de_pago_id']);
                $validated['despliegue_de_pago_id'] = $parts[1] ?? $validated['despliegue_de_pago_id'];
            }

            $compra = Compra::with([
                'productosPorAlmacen.unidadesDerivadas',
            ])->findOrFail($id);

            // Add id to validated data for validation
            $validated['id'] = $id;

            // Merge existing compra data with validated data for validation
            $dataParaValidar = array_merge([
                'estado_de_compra' => $compra->estado_de_compra->value,
                'forma_de_pago' => $compra->forma_de_pago->value,
                'tipo_moneda' => $compra->tipo_moneda->value,
                'tipo_de_cambio' => $compra->tipo_de_cambio,
                'serie' => $compra->serie,
                'numero' => $compra->numero,
                'proveedor_id' => $compra->proveedor_id,
                'egreso_dinero_id' => $compra->egreso_dinero_id,
                'gasto_extra_id' => $compra->gasto_extra_id,
                'despliegue_de_pago_id' => $compra->despliegue_de_pago_id,
            ], $validated);

            // Validar nueva compra
            $this->validarNuevaCompra($dataParaValidar);

            // Devolver dinero de compra anterior
            $this->devolverDineroDeCompra($compra);

            // Mapear tipo_documento del frontend al valor del enum PHP
            if (isset($validated['tipo_documento'])) {
                $tipoDocumentoMap = [
                    'Factura'         => '01',
                    'Boleta'          => '03',
                    'NotaDeVenta'     => 'nv',
                    'Ingreso'         => 'in',
                    'Salida'          => 'sa',
                    'RecepcionAlmacen'=> 'rc',
                ];
                $validated['tipo_documento'] = $tipoDocumentoMap[$validated['tipo_documento']] ?? $validated['tipo_documento'];
            }

            // Convert enums if present
            $updateData = [];
            foreach ($validated as $key => $value) {
                if ($key === 'estado_de_compra') {
                    $updateData[$key] = EstadoDeCompraDefinitiva::from($value);
                } elseif ($key === 'forma_de_pago') {
                    $updateData[$key] = FormaDePago::from($value);
                } elseif ($key === 'tipo_moneda') {
                    $updateData[$key] = TipoMoneda::from($value);
                } elseif ($key !== 'productos_por_almacen' && $key !== 'id') {
                    $updateData[$key] = $value;
                }
            }

            // Update compra
            $compra->update($updateData);

            // If productos_por_almacen is provided, update them
            if (isset($validated['productos_por_almacen'])) {
                // Delete existing productos_por_almacen
                ProductoAlmacenCompra::where('compra_id', $id)->delete();

                // Create new productos_por_almacen
                foreach ($validated['productos_por_almacen'] as $producto) {
                    // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                    $productoAlmacenId = $producto['producto_almacen_id'] ?? null;

                    if (!$productoAlmacenId && isset($producto['producto_id'])) {
                        $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $compra->almacen_id)
                            ->first();

                        if (!$productoAlmacen) {
                            throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$compra->almacen_id}");
                        }

                        $productoAlmacenId = $productoAlmacen->id;
                    }

                    $productoAlmacenCompra = ProductoAlmacenCompra::create([
                        'compra_id' => $compra->id,
                        'costo' => $producto['costo'],
                        'producto_almacen_id' => $productoAlmacenId,
                    ]);

                    foreach ($producto['unidades_derivadas'] as $unidad) {
                        // Get unidad_derivada_inmutable_id (either provided or firstOrCreate by name)
                        $unidadDerivadaInmutableId = $unidad['unidad_derivada_inmutable_id'] ?? null;

                        if (!$unidadDerivadaInmutableId && isset($unidad['unidad_derivada_inmutable_name'])) {
                            $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                                ['name' => $unidad['unidad_derivada_inmutable_name']],
                                ['name' => $unidad['unidad_derivada_inmutable_name']]
                            );
                            $unidadDerivadaInmutableId = $unidadDerivadaInmutable->id;
                        }

                        UnidadDerivadaInmutableCompra::create([
                            'producto_almacen_compra_id' => $productoAlmacenCompra->id,
                            'unidad_derivada_inmutable_id' => $unidadDerivadaInmutableId,
                            'factor' => $unidad['factor'],
                            'cantidad' => $unidad['cantidad'],
                            'cantidad_pendiente' => $unidad['cantidad_pendiente'],
                            'lote' => $unidad['lote'] ?? null,
                            'vencimiento' => $unidad['vencimiento'] ?? null,
                            'flete' => $unidad['flete'] ?? 0,
                            'bonificacion' => $unidad['bonificacion'] ?? false,
                        ]);
                    }
                }
            }

            // Proceso post compra
            $validated['id'] = $id;
            $this->procesoPostCompra($validated);

            return response()->json([
                'data' => $compra->fresh([
                    'proveedor:id,ruc,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'user:id,name',
                ]),
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (anular).
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $compra = Compra::with([
                'productosPorAlmacen.unidadesDerivadas',
            ])
                ->withCount([
                    'recepcionesAlmacen as recepciones_almacen_count' => function ($query) {
                        $query->where('estado', true);
                    },
                    'pagosDeCompras as pagos_de_compras_count' => function ($query) {
                        $query->where('estado', true);
                    },
                ])
                ->findOrFail($id);

            if (
                $compra->estado_de_compra === EstadoDeCompraDefinitiva::Procesado ||
                $compra->estado_de_compra === EstadoDeCompraDefinitiva::Anulado
            ) {
                return response()->json([
                    'error' => ['message' => 'La compra no se puede anular'],
                ], 400);
            }

            if ($compra->recepciones_almacen_count > 0) {
                return response()->json([
                    'error' => ['message' => 'La compra no se puede anular porque tiene Recepciones de Almacén activas'],
                ], 400);
            }

            if ($compra->pagos_de_compras_count > 0) {
                return response()->json([
                    'error' => ['message' => 'La compra no se puede anular porque tiene Pagos de Compra activos'],
                ], 400);
            }

            // Devolver dinero
            $this->devolverDineroDeCompra($compra);

            // Update egreso_dinero if exists
            if ($compra->egreso_dinero_id) {
                EgresoDinero::where('id', $compra->egreso_dinero_id)
                    ->update(['estado' => false]);
            }

            // Update compra to Anulado
            $compra->update([
                'estado_de_compra' => EstadoDeCompraDefinitiva::Anulado,
                'egreso_dinero_id' => null,
                'gasto_extra_id' => null,
            ]);

            return response()->json(['data' => 'ok']);
        });
    }

    /**
     * Calculate total de compra
     */
    private function getTotalCompra($compra)
    {
        $total = 0;

        if ($compra instanceof Compra) {
            // Eloquent model
            foreach ($compra->productosPorAlmacen as $item) {
                $costo = (float) ($item->costo ?? 0);
                foreach ($item->unidadesDerivadas as $u) {
                    $cantidad = (float) ($u->cantidad ?? 0);
                    $factor = (float) ($u->factor ?? 0);
                    $flete = (float) ($u->flete ?? 0);
                    $bonificacion = (bool) $u->bonificacion;
                    $montoLinea = ($bonificacion ? 0 : $costo * $cantidad * $factor) + $flete;
                    $total += $montoLinea;
                }
            }

            $totalConPercepcion = $total + (float) ($compra->percepcion ?? 0);
            $totalSoles = $compra->tipo_moneda === TipoMoneda::Soles
                ? $totalConPercepcion
                : $totalConPercepcion * (float) ($compra->tipo_de_cambio ?? 1);
        } else {
            // Array data
            foreach ($compra['productos_por_almacen'] as $item) {
                $costo = (float) ($item['costo'] ?? 0);
                foreach ($item['unidades_derivadas'] as $u) {
                    $cantidad = (float) ($u['cantidad'] ?? 0);
                    $factor = (float) ($u['factor'] ?? 0);
                    $flete = (float) ($u['flete'] ?? 0);
                    $bonificacion = (bool) ($u['bonificacion'] ?? false);
                    $montoLinea = ($bonificacion ? 0 : $costo * $cantidad * $factor) + $flete;
                    $total += $montoLinea;
                }
            }

            $totalConPercepcion = $total + (float) ($compra['percepcion'] ?? 0);
            $tipoMoneda = TipoMoneda::from($compra['tipo_moneda']);
            $totalSoles = $tipoMoneda === TipoMoneda::Soles
                ? $totalConPercepcion
                : $totalConPercepcion * (float) ($compra['tipo_de_cambio'] ?? 1);
        }

        return $totalSoles;
    }

    /**
     * Validar nueva compra
     */
    private function validarNuevaCompra($compra)
    {
        $estadoEnum = EstadoDeCompraDefinitiva::from($compra['estado_de_compra']);

        // En Espera no necesita validaciones de pago
        if ($estadoEnum === EstadoDeCompraDefinitiva::EnEspera) {
            return;
        }

        $formaDePagoEnum = FormaDePago::from($compra['forma_de_pago']);

        $tieneEgreso = !empty($compra['egreso_dinero_id']) || !empty($compra['gasto_extra_id']);
        $tieneMetodosPago = !empty($compra['metodos_de_pago']);
        $tieneDespliegue = !empty($compra['despliegue_de_pago_id']) || $tieneMetodosPago;

        if (
            $estadoEnum === EstadoDeCompraDefinitiva::Creado &&
            $formaDePagoEnum === FormaDePago::Contado &&
            !$tieneEgreso &&
            !$tieneDespliegue
        ) {
            throw new \Exception('En compras al contado debes seleccionar Egreso asociado o Despliegue de Pago');
        }

        if (
            $estadoEnum === EstadoDeCompraDefinitiva::Creado &&
            $formaDePagoEnum === FormaDePago::Credito &&
            ($tieneEgreso || $tieneDespliegue)
        ) {
            throw new \Exception('En compras a crédito no debes seleccionar Egreso asociado ni Despliegue de Pago');
        }

        // egreso_dinero_id (legado) no puede combinarse con despliegue (legado)
        if (
            $estadoEnum === EstadoDeCompraDefinitiva::Creado &&
            !empty($compra['egreso_dinero_id']) &&
            !empty($compra['despliegue_de_pago_id'])
        ) {
            throw new \Exception('No puedes seleccionar Egreso asociado (legado) y Despliegue de Pago al mismo tiempo');
        }

        // Validación de duplicado eliminada
    }

    /**
     * Proceso post compra
     */
    private function procesoPostCompra($compra)
    {
        $estadoEnum = EstadoDeCompraDefinitiva::from($compra['estado_de_compra']);

        if ($estadoEnum === EstadoDeCompraDefinitiva::Creado) {
            $compraModel = Compra::with([
                'productosPorAlmacen.unidadesDerivadas',
            ])->findOrFail($compra['id']);

            $totalSoles = $this->getTotalCompra($compraModel);

            if (isset($compra['egreso_dinero_id'])) {
                $egreso = EgresoDinero::findOrFail($compra['egreso_dinero_id']);
                $montoMenosVuelto = (float) $egreso->monto - (float) $egreso->vuelto;
                $a = round($montoMenosVuelto, 2);
                $b = round($totalSoles, 2);

                if ($a !== $b) {
                    throw new \Exception('El monto menos el vuelto del egreso debe ser igual al total de la compra');
                }
            }

            if (isset($compra['despliegue_de_pago_id'])) {
                // Extraer el despliegue_id del formato "sub_caja_id-despliegue_id" si es necesario
                $desplieguePagoId = $compra['despliegue_de_pago_id'];
                if (str_contains($desplieguePagoId, '-')) {
                    $parts = explode('-', $desplieguePagoId);
                    $desplieguePagoId = $parts[1] ?? $desplieguePagoId;
                }

                $despliegue = DespliegueDePago::where('id', $desplieguePagoId)
                    ->where('activo', true)
                    ->first();

                if (!$despliegue) {
                    throw new \Exception('El despliegue de pago seleccionado no existe o no está activo. Por favor, selecciona otro método de pago.');
                }

                MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                    ->decrement('monto', $totalSoles);
            }

            // Procesar múltiples métodos de pago del modal
            if (!empty($compra['metodos_de_pago'])) {
                foreach ($compra['metodos_de_pago'] as $metodo) {
                    $desplieguePagoId = $metodo['despliegue_de_pago_id'];
                    if (str_contains($desplieguePagoId, '-')) {
                        $parts = explode('-', $desplieguePagoId);
                        $desplieguePagoId = $parts[1] ?? $desplieguePagoId;
                    }

                    $despliegue = DespliegueDePago::where('id', $desplieguePagoId)
                        ->where('activo', true)
                        ->first();

                    if (!$despliegue) {
                        throw new \Exception('Un método de pago seleccionado no existe o no está activo.');
                    }

                    $compraModel->pagosDeCompras()->create([
                        'despliegue_de_pago_id' => $desplieguePagoId,
                        'monto'                 => $metodo['monto'],
                        'fecha'                 => now()->format('Y-m-d'),
                        'numero_operacion'      => $metodo['numero_operacion'] ?? null,
                        'estado'                => true,
                    ]);

                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->decrement('monto', $metodo['monto']);
                }
            }
        }
    }

    /**
     * Devolver dinero de compra
     */
    private function devolverDineroDeCompra($compra)
    {
        if ($compra->estado_de_compra === EstadoDeCompraDefinitiva::Creado) {
            $totalSoles = $this->getTotalCompra($compra);

            if ($compra->despliegue_de_pago_id) {
                $despliegue = DespliegueDePago::where('id', $compra->despliegue_de_pago_id)
                    ->first();

                if ($despliegue) {
                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->increment('monto', $totalSoles);
                }
            }

            if ($compra->egreso_dinero_id) {
                $egreso = EgresoDinero::find($compra->egreso_dinero_id);

                if ($egreso && $egreso->despliegue_de_pago_id) {
                    $despliegue = DespliegueDePago::where('id', $egreso->despliegue_de_pago_id)
                        ->first();

                    if ($despliegue) {
                        $reintegro = (float) $egreso->monto - (float) $egreso->vuelto;

                        if ($reintegro > 0) {
                            MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                                ->increment('monto', $reintegro);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get pagos de compra
     */
    public function getPagos(string $id)
    {
        $compra = Compra::findOrFail($id);

        $pagos = $compra->pagosDeCompras()
            ->with('despliegueDePago.metodoDePago')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json(['data' => $pagos]);
    }

    /**
     * Get compra details + pagos in a single request
     * GET /api/compras/{id}/detalle-completo
     */
    public function detalleCompleto(string $id)
    {
        $compra = Compra::with([
            'proveedor:id,ruc,razon_social',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'user:id,name',
            'ordenCompra:id,codigo,estado',
        ])
            ->withCount([
                'recepcionesAlmacen as recepciones_almacen_count' => function ($query) {
                    $query->where('estado', true);
                },
                'pagosDeCompras as pagos_de_compras_count' => function ($query) {
                    $query->where('estado', true);
                },
            ])
            ->withSum([
                'pagosDeCompras as total_pagado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto')
            ->findOrFail($id);

        $pagos = $compra->pagosDeCompras()
            ->with('despliegueDePago.metodoDePago')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'data' => new CompraResource($compra),
            'pagos' => $pagos,
        ]);
    }
    /**
     * Get compras por pagar (credit purchases with pending balance)
     */
    public function comprasPorPagar(Request $request)
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'proveedor_id' => 'sometimes|integer',
            'user_id' => 'sometimes|string',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Compra::query()
            ->with([
                'proveedor:id,ruc,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
            ])
            ->withSum([
                'pagosDeCompras as total_pagado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto')
            // Only credit purchases
            ->where('forma_de_pago', FormaDePago::Credito)
            // Only active purchases (not cancelled)
            ->where('estado_de_compra', '!=', EstadoDeCompraDefinitiva::Anulado);

        // Filter by almacen_id
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        // Filter by proveedor_id
        if ($request->has('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by fecha range (desde/hasta)
        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Search by serie, numero, or proveedor razon_social
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'LIKE', "%{$search}%")
                    ->orWhere('numero', 'LIKE', "%{$search}%")
                    ->orWhereHas('proveedor', function ($q2) use ($search) {
                        $q2->where('razon_social', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = $request->input('per_page', 50);

        if ($perPage === -1) {
            // Return all without pagination
            $compras = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->limit(100)->get();

            // Filter only purchases with pending balance
            $comprasConSaldo = $compras->filter(function ($compra) {
                $total = $this->getTotalCompra($compra);
                $totalPagado = (float) ($compra->total_pagado ?? 0);
                $saldo = $total - $totalPagado;
                return $saldo > 0.01; // Only show if balance > 1 cent
            });

            return response()->json([
                'data' => $comprasConSaldo->values(),
                'total' => $comprasConSaldo->count(),
            ]);
        }

        $compras = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        // Filter only purchases with pending balance
        $comprasConSaldo = $compras->getCollection()->filter(function ($compra) {
            $total = $this->getTotalCompra($compra);
            $totalPagado = (float) ($compra->total_pagado ?? 0);
            $saldo = $total - $totalPagado;
            return $saldo > 0.01; // Only show if balance > 1 cent
        });

        // Update the collection with filtered results
        $compras->setCollection($comprasConSaldo);

        return response()->json([
            'data' => $compras->items(),
            'total' => $comprasConSaldo->count(),
            'current_page' => $compras->currentPage(),
            'per_page' => $compras->perPage(),
            'last_page' => $compras->lastPage(),
        ]);
    }


    /**
     * Store pago de compra
     */
    public function storePago(Request $request, string $id)
    {
        $validated = $request->validate([
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'monto' => 'required|numeric|min:0.01',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string',
            'afecta_caja' => 'required|boolean',
            'numero_letra' => 'nullable|string',
            'numero_operacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $compra = Compra::with([
                'productosPorAlmacen.unidadesDerivadas',
                'pagosDeCompras' => function ($query) {
                    $query->where('estado', true);
                },
            ])->findOrFail($id);

            // Calcular total de la compra
            $totalCompra = $this->getTotalCompra($compra);

            // Calcular total pagado hasta ahora
            $totalPagado = $compra->pagosDeCompras->sum('monto');

            // Calcular saldo pendiente
            $saldoPendiente = $totalCompra - $totalPagado;

            // Validar que el monto no exceda el saldo
            if ($validated['monto'] > $saldoPendiente) {
                throw new \Exception('El monto del pago no puede exceder el saldo pendiente de S/ ' . number_format($saldoPendiente, 2));
            }

            // Crear el pago
            $pago = $compra->pagosDeCompras()->create([
                'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'],
                'monto' => $validated['monto'],
                'fecha' => \Carbon\Carbon::parse($validated['fecha'])->format('Y-m-d'),
                'observacion' => $validated['observacion'] ?? null,
                'numero_letra' => $validated['numero_letra'] ?? null,
                'numero_operacion' => $validated['numero_operacion'] ?? null,
                'estado' => true,
            ]);

            // Si afecta caja, registrar el egreso
            if ($validated['afecta_caja']) {
                // TODO: Implementar lógica de afectación a caja
                // Esto dependerá de cómo manejes los movimientos de caja
            }

            // Cargar relaciones para la respuesta
            $pago->load('despliegueDePago.metodoDePago');

            return response()->json([
                'data' => $pago,
                'message' => 'Pago registrado correctamente',
            ]);
        });
    }

    /**
     * Update lotes y vencimientos de unidades derivadas de una compra
     */
    public function updateLotesVencimientos(Request $request, string $id)
    {
        $validated = $request->validate([
            'unidades_derivadas' => 'required|array|min:1',
            'unidades_derivadas.*.id' => 'required|integer|exists:unidadderivadainmutablecompra,id',
            'unidades_derivadas.*.lote' => 'nullable|string|max:255',
            'unidades_derivadas.*.vencimiento' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            // Verificar que la compra existe
            $compra = Compra::findOrFail($id);

            // Verificar que todas las unidades derivadas pertenecen a esta compra
            $unidadIds = collect($validated['unidades_derivadas'])->pluck('id');

            $unidadesValidas = UnidadDerivadaInmutableCompra::whereIn('id', $unidadIds)
                ->whereHas('productoAlmacenCompra', function ($query) use ($id) {
                    $query->where('compra_id', $id);
                })
                ->count();

            if ($unidadesValidas !== count($validated['unidades_derivadas'])) {
                return response()->json([
                    'error' => ['message' => 'Algunas unidades derivadas no pertenecen a esta compra'],
                ], 400);
            }

            // Actualizar cada unidad derivada
            foreach ($validated['unidades_derivadas'] as $unidadData) {
                $updateData = [
                    'lote' => $unidadData['lote'] ?? null,
                ];

                // Convertir fecha ISO a formato MySQL si existe
                if (isset($unidadData['vencimiento']) && $unidadData['vencimiento']) {
                    try {
                        $updateData['vencimiento'] = Carbon::parse($unidadData['vencimiento'])->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $updateData['vencimiento'] = null;
                    }
                } else {
                    $updateData['vencimiento'] = null;
                }

                UnidadDerivadaInmutableCompra::where('id', $unidadData['id'])
                    ->update($updateData);
            }

            // Retornar la compra actualizada
            $compraActualizada = Compra::with([
                'proveedor:id,ruc,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
            ])->findOrFail($id);

            return response()->json([
                'data' => $compraActualizada,
                'message' => 'Lotes y vencimientos actualizados correctamente',
            ]);
        });
    }
}
