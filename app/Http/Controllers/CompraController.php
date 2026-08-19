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
use App\Models\ProductoAlmacenUnidadDerivada;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableCompra;
use App\Models\PagoDeCompra;
use App\Models\TransaccionCaja;
use App\Models\SubCaja;
use App\Models\AperturaCierreCaja;
use App\Models\MovimientoCaja;
use App\Http\Resources\CompraResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\Interfaces\CompraReporteServiceInterface;
use App\Services\Cajas\EfectivoDisponibleService;

class CompraController extends Controller
{
    private ?CompraReporteServiceInterface $reporteService;

    public function __construct(
        CompraReporteServiceInterface $reporteService,
        private EfectivoDisponibleService $efectivoDisponibleService
    ) {
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
            'estado_de_cuenta' => 'sometimes|string|in:Pagado,Credito',
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
                // Necesario para calcular el saldo en dólares (cada pago con su propio TC)
                'pagosDeCompras' => function ($query) {
                    $query->where('estado', true);
                },
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

        // Adjuntar saldo pendiente, estado de cuenta y última fecha de pago referencial
        $allCompras->each(function ($compra) {
            $saldo = $this->calcularSaldoPendiente($compra);
            $esCredito = $compra->forma_de_pago === FormaDePago::Credito;
            $anulada = $compra->estado_de_compra === EstadoDeCompraDefinitiva::Anulado;
            $compra->saldo_pendiente = round($saldo, 2);
            // Anuladas: sin estado de cuenta. Contado: siempre pagado. Crédito: pagado si saldo <= 0.01
            $compra->esta_pagado = $anulada ? null : (!$esCredito || $saldo <= 0.01);
            // Última fecha de pago referencial de los pagos activos
            $compra->ultima_fecha_pago_referencial = $compra->pagosDeCompras
                ->pluck('fecha_pago_referencial')
                ->filter()
                ->max();
        });

        // Filter by estado_de_cuenta if provided
        if ($request->has('estado_de_cuenta')) {
            $estadoDeCuenta = $request->input('estado_de_cuenta');
            
            $allCompras = $allCompras->filter(function ($compra) use ($estadoDeCuenta) {
                // Exclude cancelled purchases from account status filters
                if ($compra->estado_de_compra === EstadoDeCompraDefinitiva::Anulado) {
                    return false;
                }

                // Saldo en la moneda de la compra (dólares si tipo_moneda = Dólares)
                $saldo = $this->calcularSaldoPendiente($compra);

                $esContado = $compra->forma_de_pago === FormaDePago::Contado;
                $esCredito = $compra->forma_de_pago === FormaDePago::Credito;

                if ($estadoDeCuenta === 'Pagado') {
                    // Paid = Cash OR (Credit AND balance <= 0.01)
                    return $esContado || ($esCredito && $saldo <= 0.01);
                } else if ($estadoDeCuenta === 'Credito') {
                    // Debt = Credit AND balance > 0.01
                    return $esCredito && $saldo > 0.01;
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
        try {
            
            // Mapear tipo_documento del frontend (nombre) al valor del enum PHP ANTES de validar
            $tipoDocumentoMap = [
                'Factura'         => '01',
                'Boleta'          => '03',
                'NotaDeVenta'     => 'nv',
                'Ingreso'         => 'in',
                'Salida'          => 'sa',
                'RecepcionAlmacen'=> 'rc',
            ];
            
            if ($request->has('tipo_documento') && isset($tipoDocumentoMap[$request->input('tipo_documento')])) {
                $request->merge(['tipo_documento' => $tipoDocumentoMap[$request->input('tipo_documento')]]);
            }

            // Formatear serie con ceros a la izquierda
            if ($request->has('serie') && !empty($request->serie)) {
                $serie = $request->serie;
                $request->merge(['serie' => is_numeric($serie) ? str_pad($serie, 4, '0', STR_PAD_LEFT) : strtoupper($serie)]);
            }
            
            $esEnEspera = $request->input('estado_de_compra') === 'ee';

            $validated = $request->validate([
                'id' => 'sometimes|string',
                'tipo_documento' => $esEnEspera ? 'nullable|string' : 'required|string',
                'serie' => ['nullable', 'string', 'regex:/[^0]/'],
                'numero' => ['nullable', 'string', 'regex:/[^0]/'],
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
                'metodos_de_pago.*.fecha_pago_referencial' => 'nullable|string|date',
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
            ], [
                'serie.regex' => 'La serie no puede ser solo ceros.',
                'numero.regex' => 'El número no puede ser solo ceros.',
            ]);
        

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
            try {
                $this->validarNuevaCompra($validated);
            } catch (\Exception $e) {
                \Log::error('Error en validación:', ['error' => $e->getMessage()]);
                throw $e;
            }

            // Convert enums
            $estadoEnum = EstadoDeCompraDefinitiva::from($validated['estado_de_compra']);
            $formaDePagoEnum = isset($validated['forma_de_pago']) ? FormaDePago::from($validated['forma_de_pago']) : null;
            $tipoMonedaEnum = TipoMoneda::from($validated['tipo_moneda']);


            // Calcular total_dolares solo si se provee tipo_de_cambio (compras en USD)
            $tipoDeCambio = isset($validated['tipo_de_cambio']) && floatval($validated['tipo_de_cambio']) > 0
                ? floatval($validated['tipo_de_cambio'])
                : null;
            $totalDolares = null;
            if ($tipoDeCambio !== null && isset($validated['productos_por_almacen'])) {
                $totalSoles = collect($validated['productos_por_almacen'])->sum(function ($producto) {
                    return collect($producto['unidades_derivadas'] ?? [])->sum(function ($ud) use ($producto) {
                        $bonificacion = $ud['bonificacion'] ?? false;
                        $subtotal = $bonificacion ? 0 : (floatval($producto['costo']) * floatval($ud['factor']) * floatval($ud['cantidad']));
                        return $subtotal + floatval($ud['flete'] ?? 0);
                    });
                }) + floatval($validated['percepcion'] ?? 0);
                $totalDolares = $totalSoles / $tipoDeCambio;
            }

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
                'total_dolares' => $totalDolares,
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
            
            foreach ($validated['productos_por_almacen'] as $index => $producto) {
                
                // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                $productoAlmacenId = $producto['producto_almacen_id'] ?? null;

                if (!$productoAlmacenId && isset($producto['producto_id'])) {
                    
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                        ->where('almacen_id', $validated['almacen_id'])
                        ->first();

                    if (!$productoAlmacen) {
                        \Log::error("ProductoAlmacen no encontrado", [
                            'producto_id' => $producto['producto_id'],
                            'almacen_id' => $validated['almacen_id'],
                        ]);
                        throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$validated['almacen_id']}");
                    }

                    $productoAlmacenId = $productoAlmacen->id;
                }

                $productoAlmacenCompra = ProductoAlmacenCompra::create([
                    'compra_id' => $compra->id,
                    'costo' => $producto['costo'],
                    'producto_almacen_id' => $productoAlmacenId,
                ]);
                

                foreach ($producto['unidades_derivadas'] as $udIndex => $unidad) {
                    
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

                    // Actualizar precios de venta si el costo subió y el usuario los ajustó
                    if (!empty($unidad['nuevos_precios']) && isset($unidad['nuevos_precios']['unidad_derivada_id'])) {
                        $np = $unidad['nuevos_precios'];
                        $update = array_filter([
                            'precio_publico'  => isset($np['precio_publico'])  ? (float) $np['precio_publico']  : null,
                            'precio_especial' => isset($np['precio_especial']) ? (float) $np['precio_especial'] : null,
                            'precio_minimo'   => isset($np['precio_minimo'])   ? (float) $np['precio_minimo']   : null,
                            'precio_ultimo'   => isset($np['precio_ultimo'])   ? (float) $np['precio_ultimo']   : null,
                        ], fn($v) => !is_null($v));

                        if (!empty($update)) {
                            ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
                                ->where('unidad_derivada_id', (int) $np['unidad_derivada_id'])
                                ->update($update);
                        }
                    }

                }
            }

            // Registrar en kardex inventario solo si NO está en espera
            if ($validated['estado_de_compra'] !== 'ee') {
                $kardexInventarioService = app(\App\Services\Kardex\KardexInventarioService::class);
                
                foreach ($compra->productosPorAlmacen as $pac) {
                    foreach ($pac->unidadesDerivadas as $unidad) {
                        $kardexInventarioService->registrarCompraReferencia(
                            $compra,
                            $pac->productoAlmacen,
                            $unidad,
                            $pac->costo,
                            0 // orden = 0 para referencia
                        );
                    }
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
        } catch (\Exception $e) {
            \Log::error('Error creating compra:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al crear la compra',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
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
        // Mapear tipo_documento del frontend (nombre) al valor del enum PHP ANTES de validar
        $tipoDocumentoMap = [
            'Factura'         => '01',
            'Boleta'          => '03',
            'NotaDeVenta'     => 'nv',
            'Ingreso'         => 'in',
            'Salida'          => 'sa',
            'RecepcionAlmacen'=> 'rc',
        ];
        
        if ($request->has('tipo_documento') && isset($tipoDocumentoMap[$request->input('tipo_documento')])) {
            $request->merge(['tipo_documento' => $tipoDocumentoMap[$request->input('tipo_documento')]]);
        }

        // Formatear serie y número con ceros a la izquierda
        if ($request->has('serie') && !empty($request->serie)) {
            $serie = $request->serie;
            $request->merge(['serie' => is_numeric($serie) ? str_pad($serie, 4, '0', STR_PAD_LEFT) : strtoupper($serie)]);
        }
        $validated = $request->validate([
            'tipo_documento' => 'sometimes|string',
            'serie' => ['nullable', 'string', 'regex:/[^0]/'],
            'numero' => ['nullable', 'string', 'regex:/[^0]/'],
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
            'metodos_de_pago' => 'nullable|array',
            'metodos_de_pago.*.despliegue_de_pago_id' => 'required_with:metodos_de_pago|string',
            'metodos_de_pago.*.monto' => 'required_with:metodos_de_pago|numeric|min:0.01',
            'metodos_de_pago.*.numero_operacion' => 'nullable|string',
            'metodos_de_pago.*.fecha_pago_referencial' => 'nullable|string|date',
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
        ], [
            'serie.regex' => 'La serie no puede ser solo ceros.',
            'numero.regex' => 'El número no puede ser solo ceros.',
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

            // Con recepciones de almacén activas los productos ya no son editables:
            // borrar/recrear los detalles resetearía cantidad_pendiente y perdería
            // el avance de recepción. Solo se actualiza la información de la compra.
            $tieneRecepcionesActivas = $compra->recepcionesAlmacen()
                ->where('estado', true)
                ->exists();

            if ($tieneRecepcionesActivas) {
                unset($validated['productos_por_almacen']);
            }

            // Add id to validated data for validation
            $validated['id'] = $id;

            // Guardar el estado anterior ANTES de actualizar
            $estadoAnterior = $compra->estado_de_compra->value;

            // Merge existing compra data with validated data for validation
            // Use ?-> because En Espera compras may have null forma_de_pago/tipo_moneda
            $dataParaValidar = array_merge([
                'estado_de_compra' => $compra->estado_de_compra->value,
                'forma_de_pago' => $compra->forma_de_pago?->value,
                'tipo_moneda' => $compra->tipo_moneda?->value,
                'tipo_de_cambio' => $compra->tipo_de_cambio,
                'serie' => $compra->serie,
                'numero' => $compra->numero,
                'proveedor_id' => $compra->proveedor_id,
                'egreso_dinero_id' => $compra->egreso_dinero_id,
                'gasto_extra_id' => $compra->gasto_extra_id,
                'despliegue_de_pago_id' => $compra->despliegue_de_pago_id,
                // Una compra contado ya pagada (p.ej. recepcionada que solo
                // edita información) no debe exigir un nuevo método de pago
                'tiene_pagos_activos' => $compra->pagosDeCompras()
                    ->where('estado', true)
                    ->exists(),
            ], $validated);

            // Validar nueva compra
            $this->validarNuevaCompra($dataParaValidar);

            // Devolver dinero de compra anterior
            $this->devolverDineroDeCompra($compra);

            // En compras al contado los pagos activos provienen de metodos_de_pago
            // y procesoPostCompra los vuelve a crear con los montos nuevos: hay que
            // anular los anteriores y devolver su dinero, o cada edición duplica
            // el total pagado. Los pagos de compras a crédito (amortizaciones) no
            // se tocan.
            if (
                $compra->estado_de_compra === EstadoDeCompraDefinitiva::Creado &&
                $compra->forma_de_pago === FormaDePago::Contado &&
                !empty($validated['metodos_de_pago'])
            ) {
                $pagosAnteriores = $compra->pagosDeCompras()
                    ->where('estado', true)
                    ->with('despliegueDePago')
                    ->get();

                foreach ($pagosAnteriores as $pago) {
                    if ($pago->despliegueDePago) {
                        MetodoDePago::where('id', $pago->despliegueDePago->metodo_de_pago_id)
                            ->increment('monto', (float) $pago->monto);
                    }
                    $this->revertirTransaccionCajaParaPagoCompra($pago, $compra);
                    $pago->update([
                        'estado' => false,
                        'observacion' => 'Anulado por edición de compra',
                    ]);
                }
            }

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
                } elseif (!in_array($key, ['productos_por_almacen', 'id', 'metodos_de_pago'])) {
                    $updateData[$key] = $value;
                }
            }

            // Recalcular total_dolares si cambió el TC o los productos
            // array_key_exists en lugar de isset porque isset() ignora null
            $tcFinal = array_key_exists('tipo_de_cambio', $validated)
                ? floatval($validated['tipo_de_cambio'])
                : floatval($compra->tipo_de_cambio ?? 0);

            if ($tcFinal > 0) {
                $productosParaCalculo = $validated['productos_por_almacen'] ?? null;

                if ($productosParaCalculo !== null) {
                    // Recalcular con los productos nuevos
                    $totalSoles = collect($productosParaCalculo)->sum(function ($producto) {
                        return collect($producto['unidades_derivadas'] ?? [])->sum(function ($ud) use ($producto) {
                            $bonificacion = $ud['bonificacion'] ?? false;
                            $subtotal = $bonificacion ? 0 : (floatval($producto['costo']) * floatval($ud['factor']) * floatval($ud['cantidad']));
                            return $subtotal + floatval($ud['flete'] ?? 0);
                        });
                    }) + floatval($validated['percepcion'] ?? $compra->percepcion ?? 0);
                } else {
                    // Recalcular con los productos existentes
                    $compra->load('productosPorAlmacen.unidadesDerivadas');
                    $totalSoles = $this->getTotalCompra($compra);
                }

                $updateData['total_dolares'] = $totalSoles / $tcFinal;
            } else {
                $updateData['total_dolares'] = null;
            }

            // Update compra
            $compra->update($updateData);

            // Registrar en kardex si la compra cambió de 'ee' a otro estado
            $estadoNuevo = $compra->estado_de_compra->value;
            
            
            if ($estadoAnterior === 'ee' && $estadoNuevo !== 'ee') {
                // La compra pasó de en espera a registrada/procesada
                $kardexInventarioService = app(\App\Services\Kardex\KardexInventarioService::class);
                
                $compra->refresh(); // Recargar para obtener relaciones actualizadas
                foreach ($compra->productosPorAlmacen as $pac) {
                    foreach ($pac->unidadesDerivadas as $unidad) {
                        $kardexInventarioService->registrarCompraReferencia(
                            $compra,
                            $pac->productoAlmacen,
                            $unidad,
                            $pac->costo,
                            0 // orden = 0 para referencia
                        );
                    }
                }
            } else {
            }

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
    public function destroy(Request $request, string $id)
    {
        $skipRefund = filter_var($request->query('skip_refund', false), FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($id, $skipRefund) {
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

            // 1. Devolver dinero de pagos específicos (créditos/modal pagos)
            $pagos = $compra->pagosDeCompras()->where('estado', true)->with('despliegueDePago')->get();
            foreach ($pagos as $pago) {
                if (!$skipRefund) {
                    if ($pago->despliegueDePago) {
                        MetodoDePago::where('id', $pago->despliegueDePago->metodo_de_pago_id)
                            ->increment('monto', (float) $pago->monto);
                    }
                    // Revertir la transacción de caja (ingreso para devolver el dinero)
                    $this->revertirTransaccionCajaParaPagoCompra($pago, $compra);
                }
                $pago->update(['estado' => false, 'observacion' => 'Anulado por anulación de compra']);
            }

            // 2. Devolver dinero de pago inicial (Contado/Egreso)
            if (!$skipRefund) {
                $this->devolverDineroDeCompra($compra);

                // Update egreso_dinero if exists
                if ($compra->egreso_dinero_id) {
                    EgresoDinero::where('id', $compra->egreso_dinero_id)
                        ->update(['estado' => false]);
                }
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
            
            // Los montos base (costo, subtotal) ya vienen en Soles desde el frontend 
            // aunque el tipo_moneda sea Dólares (informativo).
            return $totalConPercepcion;
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
            return $totalConPercepcion;
        }
    }

    /**
     * Calcula el saldo pendiente de una compra EN SU PROPIA MONEDA.
     *
     * - Soles:   saldo = total_soles − Σ(pagos.monto)
     * - Dólares: saldo = (total_soles / tc_compra) − Σ(pago.monto / pago.tc)
     *   Cada pago se convierte a dólares con el TC con el que se pagó, así que
     *   pagar el total en USD a un TC distinto al de la compra la deja cancelada
     *   (antes quedaba un residual en soles y aparecía como deuda).
     */
    private function calcularSaldoPendiente(Compra $compra): float
    {
        $totalSoles = $this->getTotalCompra($compra);

        $pagos = $compra->relationLoaded('pagosDeCompras')
            ? $compra->pagosDeCompras->where('estado', true)
            : $compra->pagosDeCompras()->where('estado', true)->get();

        $esDolares = $compra->tipo_moneda === TipoMoneda::Dolares;
        $tcCompra = (float) ($compra->tipo_de_cambio ?? 0);

        if ($esDolares && $tcCompra > 0) {
            $totalDolares = $totalSoles / $tcCompra;
            $pagadoDolares = 0.0;
            foreach ($pagos as $p) {
                $tc = (float) ($p->tipo_de_cambio ?? 0);
                $pagadoDolares += $tc > 0 ? ((float) $p->monto) / $tc : 0.0;
            }
            return $totalDolares - $pagadoDolares;
        }

        $pagadoSoles = (float) $pagos->sum('monto');
        return $totalSoles - $pagadoSoles;
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

        if (empty($compra['forma_de_pago'])) {
            throw new \Exception('La forma de pago es requerida para completar la compra');
        }

        $formaDePagoEnum = FormaDePago::from($compra['forma_de_pago']);

        $tieneEgreso = !empty($compra['egreso_dinero_id']) || !empty($compra['gasto_extra_id']);
        $tieneMetodosPago = !empty($compra['metodos_de_pago']);
        $tieneDespliegue = !empty($compra['despliegue_de_pago_id']) || $tieneMetodosPago;
        // En ediciones, los pagos ya registrados cuentan como pago del contado
        $tienePagosActivos = !empty($compra['tiene_pagos_activos']);

        if (
            $estadoEnum === EstadoDeCompraDefinitiva::Creado &&
            $formaDePagoEnum === FormaDePago::Contado &&
            !$tieneEgreso &&
            !$tieneDespliegue &&
            !$tienePagosActivos
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

        // Validación de duplicado: (serie, numero, proveedor_id) debe ser único
        // ENTRE COMPRAS VIGENTES. Las anuladas conservan su serie/número como
        // registro histórico, pero no deben bloquear el re-registro de la misma
        // factura (antes una anulada bloqueaba y obligaba a "liberar" el número).
        if (!empty($compra['serie']) && !empty($compra['numero']) && !empty($compra['proveedor_id'])) {
            $query = Compra::where('serie', $compra['serie'])
                ->where('numero', $compra['numero'])
                ->where('proveedor_id', $compra['proveedor_id'])
                ->where('estado_de_compra', '!=', EstadoDeCompraDefinitiva::Anulado);

            if (!empty($compra['id'])) {
                $query->where('id', '!=', $compra['id']);
            }

            if ($query->exists()) {
                // store() envuelve todo en un try/catch(\Exception) local (más abajo)
                // que responde con $e->getMessage() — un ValidationException con una
                // response 422 "a medida" queda descartado ahí (Laravel arma su
                // propio resumen genérico, "The given data was invalid.", para
                // $e->getMessage() porque el validator se construyó vacío), y ese es
                // el mensaje genérico que terminaba viendo el usuario. Un \Exception
                // simple sí se propaga con su texto real, igual que la validación de
                // arriba (línea 1149) y el mismo patrón que ya usa VentaController.
                throw new \Exception(
                    "Ya existe una compra con la serie {$compra['serie']}-{$compra['numero']} para este proveedor"
                );
            }
        }
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
                    
                    
                    // NO extraer el ID si ya viene en formato correcto (sin guión)
                    // Solo extraer si tiene el formato "subcaja_id-despliegue_id"
                    if (str_contains($desplieguePagoId, '-')) {
                        $parts = explode('-', $desplieguePagoId);
                        $desplieguePagoId = $parts[1] ?? $desplieguePagoId;
                    }

                    $despliegue = DespliegueDePago::where('id', $desplieguePagoId)
                        ->where('activo', true)
                        ->first();

                    if (!$despliegue) {
                        \Log::error('Despliegue de pago no encontrado:', ['id' => $desplieguePagoId]);
                        throw new \Exception("El método de pago seleccionado (ID: {$desplieguePagoId}) no existe o no está activo.");
                    }


                    try {
                        $pagoCreado = $compraModel->pagosDeCompras()->create([
                            'despliegue_de_pago_id'   => $desplieguePagoId,
                            'monto'                   => $metodo['monto'],
                            'fecha'                   => now()->format('Y-m-d H:i:s'),
                            'numero_operacion'        => $metodo['numero_operacion'] ?? null,
                            'fecha_pago_referencial'  => $metodo['fecha_pago_referencial'] ?? null,
                            'estado'                  => true,
                        ]);

                        // Registrar transacción en caja si el pago se hizo desde el POS
                        $this->registrarTransaccionCajaParaPagoCompra($pagoCreado, $compraModel);
                        
                    } catch (\Exception $e) {
                        \Log::error('Error al crear pago de compra:', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }

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
            'estado_pago' => 'sometimes|in:pendientes,pagadas,todas',
            'per_page' => 'sometimes|integer|min:-1|max:100',
            'page' => 'sometimes|integer|min:1',
            'dias' => 'sometimes|integer|min:0|max:365',
        ]);

        $estadoPago = $request->input('estado_pago', 'pendientes');

        $query = Compra::query()
            ->with([
                'proveedor:id,ruc,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
                // Necesario para calcular el saldo en dólares (cada pago con su propio TC)
                'pagosDeCompras' => function ($query) {
                    $query->where('estado', true);
                },
            ])
            ->withSum([
                'pagosDeCompras as total_pagado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto')
            // Only credit purchases
            ->where('forma_de_pago', FormaDePago::Credito)
            // Solo compras formalmente registradas: Creado o Procesado. Se excluyen
            // las anuladas y las "en espera" (ee), que aún no son cuentas por pagar.
            ->whereIn('estado_de_compra', [
                EstadoDeCompraDefinitiva::Creado,
                EstadoDeCompraDefinitiva::Procesado,
            ]);

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

        // Filtro por días a vencer (compras cuya fecha_vencimiento <= hoy+N)
        if ($request->has('dias')) {
            $fechaLimite = now()->addDays((int) $request->dias)->toDateString();
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', $fechaLimite);
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

        // Filtro por estado de pago según el saldo real (no según el estado de la
        // compra): "pendientes" = aún debe algo, "pagadas" = saldo liquidado,
        // "todas" = sin filtrar. Antes esto estaba fijo en "solo con saldo", por
        // eso las compras ya pagadas nunca se podían ver.
        $filtrarPorEstadoPago = function ($compra) use ($estadoPago) {
            // Saldo en la moneda de la compra (dólares si tipo_moneda='d'), así una compra
            // en dólares pagada al TC del pago no queda como deuda fantasma en soles.
            $saldo = round($this->calcularSaldoPendiente($compra), 2);

            // Adjuntar para que el frontend muestre la columna "Estado" sin recalcular.
            $compra->saldo_pendiente = $saldo;
            $compra->esta_pagado = $saldo <= 0.01;

            if ($estadoPago === 'pendientes') {
                return $saldo > 0.01; // Aún debe algo (con tolerancia por redondeo)
            } elseif ($estadoPago === 'pagadas') {
                return $saldo <= 0.01; // Pagada completamente
            }
            return true; // Todas
        };

        if ($perPage === -1) {
            // Return all without pagination
            $compras = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->limit(100)->get();

            $comprasFiltradas = $compras->filter($filtrarPorEstadoPago);

            return response()->json([
                'data' => $comprasFiltradas->values(),
                'total' => $comprasFiltradas->count(),
            ]);
        }

        $compras = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        $comprasFiltradas = $compras->getCollection()->filter($filtrarPorEstadoPago)->values();

        // Update the collection with filtered results (reindexada para que el
        // JSON salga como arreglo y no como objeto con claves dispersas).
        $compras->setCollection($comprasFiltradas);

        return response()->json([
            'data' => $compras->items(),
            'total' => $comprasFiltradas->count(),
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
            'tipo_de_cambio' => 'nullable|numeric|min:0.0001',
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

            // Calcular saldo pendiente EN LA MONEDA DE LA COMPRA (dólares si aplica)
            $saldoPendiente = $this->calcularSaldoPendiente($compra);

            // Convertir el monto del nuevo pago a la moneda de la compra para comparar.
            // El monto siempre llega en soles; para una compra en dólares se divide por
            // el TC con el que se está pagando.
            $esDolares = $compra->tipo_moneda === TipoMoneda::Dolares;
            $tcPago = (float) ($validated['tipo_de_cambio'] ?? 0);
            $montoEnMoneda = ($esDolares && $tcPago > 0)
                ? ((float) $validated['monto']) / $tcPago
                : (float) $validated['monto'];

            // Validar que el monto no exceda el saldo (tolerancia por redondeo).
            // abort(422) devuelve un JSON {"message": ...} legible (antes un \Exception
            // genérico se veía como "Server Error" 500 en producción).
            if ($montoEnMoneda > $saldoPendiente + 0.01) {
                $simbolo = $esDolares ? '$ ' : 'S/ ';
                $saldoFmt = $saldoPendiente <= 0.01
                    ? 'Esta compra ya está pagada.'
                    : 'No puedes pagar más de lo que se debe. Saldo pendiente: ' . $simbolo . number_format($saldoPendiente, 2);
                abort(422, $saldoFmt);
            }

            // Crear el pago. La fecha la elige el usuario (puede registrar un pago
            // atrasado), pero la hora es siempre la del momento real del registro.
            $pago = $compra->pagosDeCompras()->create([
                'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'],
                'monto' => $validated['monto'],
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? null,
                'fecha' => \Carbon\Carbon::parse($validated['fecha'])->setTimeFrom(now())->format('Y-m-d H:i:s'),
                'observacion' => $validated['observacion'] ?? null,
                'numero_letra' => $validated['numero_letra'] ?? null,
                'numero_operacion' => $validated['numero_operacion'] ?? null,
                'estado' => true,
            ]);

            // Si afecta caja, registrar el egreso en transacciones_caja
            if ($validated['afecta_caja']) {
                $this->registrarTransaccionCajaParaPagoCompra($pago, $compra);
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
     * Anular pago de compra
     */
    public function anularPago(Request $request, string $compraId, string $pagoId)
    {
        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($compraId, $pagoId, $validated) {
            $compra = Compra::with([
                'productosPorAlmacen.unidadesDerivadas',
                'pagosDeCompras' => fn($q) => $q->where('estado', true),
            ])->findOrFail($compraId);

            $pago = \App\Models\PagoDeCompra::where('id', $pagoId)
                ->where('compra_id', $compraId)
                ->firstOrFail();

            if (!$pago->estado) {
                return response()->json([
                    'error' => ['message' => 'El pago ya está anulado'],
                ], 422);
            }

            $pago->update([
                'estado' => false,
                // Guardar con hora (la columna es datetime) para que el kardex de finanzas
                // muestre la hora real de la anulación, no 12:00 AM.
                'fecha_anulacion' => now()->format('Y-m-d H:i:s'),
                'observacion' => ($pago->observacion ? $pago->observacion . ' | ' : '') .
                                 'ANULADO: ' . ($validated['motivo'] ?? 'Sin motivo especificado'),
            ]);

            // Revertir la transacción de caja asociada
            $this->revertirTransaccionCajaParaPagoCompra($pago, $compra);

            $totalCompra = $this->getTotalCompra($compra);
            $totalPagadoActivo = $compra->pagosDeCompras()->where('estado', true)->sum('monto');
            $saldoPendiente = $totalCompra - $totalPagadoActivo;

            return response()->json([
                'data'            => $pago->fresh(),
                'message'         => 'Pago anulado correctamente',
                'saldo_pendiente' => $saldoPendiente,
            ], 200);
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

    /**
     * Registrar la transacción de caja para un pago de compra
     */
    private function registrarTransaccionCajaParaPagoCompra(PagoDeCompra $pago, Compra $compra): void
    {
        // Los dos `return` mudos que había acá dejaban el pago guardado en
        // `pagodecompra` sin tocar la caja: el saldo no bajaba, el egreso no
        // aparecía en el cierre y la respuesta era igual de éxito. Mismo problema
        // que tenían los cobros de venta. Si el dinero no puede salir de ninguna
        // caja, el pago no debe existir: abort(422) revierte toda la transacción.
        $despliegue = DespliegueDePago::with('metodoDePago')->find($pago->despliegue_de_pago_id);
        if (!$despliegue) {
            \Log::warning('PagoCompra sin despliegue de pago válido', ['pago_id' => $pago->id]);
            abort(422, 'El método de pago seleccionado ya no existe o está inactivo. Elija otro método para registrar el pago.');
        }

        // Buscar sub-caja que acepta este método de pago
        $subCaja = SubCaja::where('estado', true)
            ->where(function ($query) use ($pago) {
                $query->whereJsonContains('despliegues_pago_ids', $pago->despliegue_de_pago_id)
                    ->orWhereJsonContains('despliegues_pago_ids', '*');
            })
            ->first();

        if (!$subCaja) {
            \Log::warning('No se encontró sub-caja para el pago de compra', [
                'despliegue_pago_id' => $pago->despliegue_de_pago_id,
            ]);
            $metodo = $despliegue->name ?? $despliegue->metodoDePago->name ?? 'el método elegido';
            abort(422, "El método de pago \"{$metodo}\" no está asignado a ninguna caja activa, así que el dinero no puede salir de ningún lado. "
                . 'Asigne ese método a una caja o elija otro método para pagar.');
        }

        $monto = (float) $pago->monto;
        $saldoAnterior = (float) $subCaja->saldo_actual;

        // Quien PAGA es quien pone el efectivo de su bolsillo, no quien registró la
        // compra. Antes todo esto usaba `$compra->user_id`, así que al pagar una
        // compra creada por otro vendedor se validaba contra la sesión del OTRO: un
        // usuario con 3,110.60 en su caja recibía "disponible S/ 0.00" porque el
        // creador de la compra no tenía efectivo. Y la transacción quedaba a nombre
        // del creador, restándole a él en el cierre en vez de a quien pagó.
        $pagadorId = auth()->id() ?? $compra->user_id;

        // Disponible para pagar: delegado a EfectivoDisponibleService (la MISMA
        // calculadora que ya usan Traslado a Bóveda / Traslado de Efectivo para
        // mostrar "Efectivo Disponible") en vez de una copia local — esta copia
        // tenía su propia fórmula (sub-caja-wide, sin filtrar por vendedor ni por
        // método de pago específico, con su propia lista de exclusiones) que
        // podía divergir bastante del número que el usuario ve en pantalla antes
        // de pagar. Si la sub-caja tiene una APERTURA ABIERTA de este vendedor,
        // solo se puede pagar con el dinero de ESA sesión (apertura + ingresos −
        // egresos desde que se abrió, para este método de pago específico); el
        // dinero de sesiones cerradas se dispone con "Traslado de Efectivo".
        $disponible = $saldoAnterior;
        $aperturaAbierta = AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $pagadorId)
            ->whereNull('fecha_cierre')
            ->first();

        if ($aperturaAbierta) {
            $disponibleSesion = $this->efectivoDisponibleService->calcularDesdeApertura(
                $subCaja,
                $pagadorId,
                $pago->despliegue_de_pago_id,
                $aperturaAbierta
            );
            $disponible = min($saldoAnterior, $disponibleSesion);
        }

        // Validar dinero suficiente: no se puede pagar con dinero que no está
        // disponible (antes el saldo quedaba en negativo sin aviso). El
        // abort(422) revierte toda la transacción, incluido el pago ya creado.
        if ($monto > $disponible + 0.001) {
            abort(422, "No tienes dinero suficiente en \"{$subCaja->nombre}\": disponible S/ "
                . number_format(max($disponible, 0), 2)
                . " y el pago es de S/ " . number_format($monto, 2)
                . ($aperturaAbierta
                    ? '. Solo puedes pagar con el dinero de la sesión abierta; usa "Traslado de Efectivo" para disponer del resto.'
                    : '. Registre un ingreso o use otro método de pago.'));
        }

        // Actualizar saldo de la sub-caja
        $subCaja->saldo_actual = $saldoAnterior - $monto;
        $subCaja->save();

        // Registrar transacción en transacciones_caja
        TransaccionCaja::create([
            'sub_caja_id' => $subCaja->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $subCaja->saldo_actual,
            'descripcion' => 'Pago de compra ' . ($compra->serie ? $compra->serie . '-' . $compra->numero : $compra->id),
            'referencia_id' => $compra->id,
            'referencia_tipo' => 'pago_compra',
            // A nombre de quien pagó: es su efectivo el que sale, y es a él a quien
            // el cierre debe descontarle este egreso.
            'user_id' => $pagadorId,
            'despliegue_pago_id' => $pago->despliegue_de_pago_id,
            'fecha' => $pago->fecha ?? now(),
        ]);

        // Registrar en MovimientoCaja si hay apertura activa
        $apertura = AperturaCierreCaja::where('estado', 'abierta')
            ->where('user_id', $pagadorId)
            ->orderBy('fecha_apertura', 'desc')
            ->first();

        if ($apertura) {
            try {
                MovimientoCaja::create([
                    'apertura_cierre_id' => $apertura->id,
                    'caja_principal_id' => $apertura->caja_principal_id,
                    'sub_caja_id' => $subCaja->id,
                    'cajero_id' => $pagadorId,
                    'fecha_hora' => now(),
                    'tipo_movimiento' => 'compra',
                    'concepto' => 'Pago de compra ' . ($compra->serie ? $compra->serie . '-' . $compra->numero : $compra->id),
                    'saldo_inicial' => $saldoAnterior,
                    'ingreso' => 0,
                    'salida' => $monto,
                    'saldo_final' => $subCaja->saldo_actual,
                    'estado_caja' => 'abierta',
                    'metodo_pago_id' => $despliegue->metodo_de_pago_id,
                    'referencia_id' => $compra->id,
                    'referencia_tipo' => 'pago_compra',
                ]);
            } catch (\Exception $e) {
                \Log::warning('Error al registrar MovimientoCaja para pago de compra', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Revertir la transacción de caja al anular un pago de compra
     */
    private function revertirTransaccionCajaParaPagoCompra(PagoDeCompra $pago, Compra $compra): void
    {
        // Buscar la transacción original de este pago
        $transaccionOriginal = TransaccionCaja::where('referencia_tipo', 'pago_compra')
            ->where('referencia_id', $compra->id)
            ->where('despliegue_pago_id', $pago->despliegue_de_pago_id)
            ->where('monto', (float) $pago->monto)
            ->first();

        if (!$transaccionOriginal) {
            return;
        }

        $subCaja = SubCaja::find($transaccionOriginal->sub_caja_id);
        if (!$subCaja) {
            return;
        }

        $monto = (float) $pago->monto;
        $saldoAnterior = (float) $subCaja->saldo_actual;

        // Revertir el saldo de la sub-caja
        $subCaja->saldo_actual = $saldoAnterior + $monto;
        $subCaja->save();

        // Registrar transacción de reversión (ingreso para devolver el dinero)
        TransaccionCaja::create([
            'sub_caja_id' => $subCaja->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $subCaja->saldo_actual,
            'descripcion' => 'Anulación de pago de compra ' . ($compra->serie ? $compra->serie . '-' . $compra->numero : $compra->id),
            'referencia_id' => $compra->id,
            'referencia_tipo' => 'anulacion_pago_compra',
            // A nombre del MISMO usuario que hizo el pago original, no del creador de
            // la compra: si el egreso quedó a nombre de uno y la reversión a nombre de
            // otro, no se cancelan — al primero le sigue figurando el gasto en su
            // cierre y al segundo le aparece un ingreso que nunca recibió.
            'user_id' => $transaccionOriginal->user_id,
            'despliegue_pago_id' => $pago->despliegue_de_pago_id,
            'fecha' => now(),
        ]);
    }
}
