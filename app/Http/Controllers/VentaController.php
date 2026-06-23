<?php

namespace App\Http\Controllers;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Enums\EstadoDeVenta;
use App\Enums\FormaDePago;
use App\Enums\TipoDocumento;
use App\Enums\TipoMoneda;
use App\Models\AperturaCierreCaja;
use App\Models\DespliegueDePago;
use App\Models\DespliegueDePagoVenta;
use App\Models\IngresoDinero;
use App\Models\MetodoDePago;
use App\Models\MovimientoCaja;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenVenta;
use App\Models\ServicioVenta;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\Venta;
use App\Models\ValeCompra;
use App\Models\VentaHistorial;
use App\Services\Entrega\EntregaService;
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\SerieDocumentoService;
use App\Services\ValeCompraService;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentaController extends Controller
{
    public function __construct(
        private FacturaServiceInterface $facturaService,
        private ValeCompraService $valeCompraService,
        private EntregaService $entregaService,
        private SerieDocumentoService $serieDocumentoService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'estado_de_venta' => 'sometimes|string',
            'cliente_id' => 'sometimes|integer',
            'tipo_documento' => 'sometimes|string',
            'forma_de_pago' => 'sometimes|string',
            'despliegue_de_pago_id' => 'sometimes|string',
            'user_id' => 'sometimes|string',
            'serie' => 'sometimes|string',
            'numero' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'search' => 'sometimes|string',
            'entrega' => 'sometimes|string|in:pendiente,completa',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Venta::query()
            ->with([
                'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
                'cliente.direcciones',
                'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'despliegueDePagoVentas.despliegueDePago',
                'serviciosVenta.servicio',
                'user:id,name',
                'almacen:id,name',
                'comprobanteElectronico:id,venta_id,tipo_comprobante,serie,correlativo,fecha_emision,estado_sunat,xml_path,xml_firmado,cdr_path,pdf_path,moneda,operacion_gravada,total_igv,importe_total',
                // Lectura desde la tabla NUEVA (entrega). El estado viene por FK
                // al catálogo, así que se carga estadoEntrega:codigo para que el
                // front lea entregas[].estado_entrega.codigo.
                'entregas:id,venta_id,estado_entrega_id',
                'entregas.estadoEntrega:id,codigo',
                'valesAplicados:id,venta_id,descuento_aplicado,descuento_tipo',
            ])
            ->withCount('entregas as entregas_productos_count')
            ->withCount(['historial as total_ediciones' => function ($q) {
                // Solo cuenta acciones de tipo 'edicion' — ignora otras
                // entradas del historial (cambios de estado, anulación, etc.).
                $q->where('accion', 'edicion');
            }])
            ->withSum('despliegueDePagoVentas as total_pagado', 'monto')
            ->withSum([
                'cobrosVenta as total_cobrado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto');

        // Filter by almacen_id
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        // Filter by estado_de_venta
        if ($request->has('estado_de_venta')) {
            $estadoEnum = EstadoDeVenta::tryFrom($request->estado_de_venta);
            if ($estadoEnum) {
                $query->where('estado_de_venta', $estadoEnum->value);
            }
        }

        // Filter by estado_cuenta
           if ($request->has('estado_cuenta')) {
            $ec = $request->estado_cuenta;
            if ($ec === 'pagado') {
                $query->where('estado_de_venta', EstadoDeVenta::Procesado->value);
            } elseif ($ec === 'deuda') {
                $query->where('estado_de_venta', EstadoDeVenta::Creado->value)
                      ->where('forma_de_pago', FormaDePago::Credito->value);
            }
        }

        // Filtro "Todos" (sin estado): no se aplica ninguna exclusión, se
        // devuelven TODOS los estados (Creado, En Espera, Anulado, etc.). Los
        // totales del front excluyen del cálculo los estados que no son ventas
        // finalizadas (anulado / en espera).

        // Filter by cliente_id
        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filter by tipo_documento
        if ($request->has('tipo_documento')) {
            $tipoDocEnum = TipoDocumento::tryFrom($request->tipo_documento);
            if ($tipoDocEnum) {
                $query->where('tipo_documento', $tipoDocEnum->value);
            }
        }

        // Filter by forma_de_pago
        if ($request->has('forma_de_pago')) {
            $formaPagoEnum = FormaDePago::tryFrom($request->forma_de_pago);
            if ($formaPagoEnum) {
                $query->where('forma_de_pago', $formaPagoEnum->value);
            }
        }

        // Filter by despliegue_de_pago_id (metodo de pago)
        if ($request->has('despliegue_de_pago_id')) {
            $despliegueId = $request->despliegue_de_pago_id;
            
            // Si viene con formato "sub_caja_id-despliegue_pago_id" (ej. "29-01KQ5DMW...")
            if (str_contains($despliegueId, '-')) {
                $parts = explode('-', $despliegueId);
                $despliegueId = end($parts);
            }

            $query->whereHas('despliegueDePagoVentas', function ($q) use ($despliegueId) {
                $q->where('despliegue_de_pago_id', $despliegueId);
            });
        }

        // Filter by user_id (vendedor)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by serie
        if ($request->has('serie')) {
            $query->where('serie', $request->serie);
        }

        // Filter by numero
        if ($request->has('numero')) {
            $query->where('numero', $request->numero);
        }

        // Filter by fecha range (desde/hasta)
        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Search by serie, numero, or cliente
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'LIKE', "%{$search}%")
                    ->orWhere('numero', 'LIKE', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('razon_social', 'LIKE', "%{$search}%")
                            ->orWhere('nombres', 'LIKE', "%{$search}%")
                            ->orWhere('apellidos', 'LIKE', "%{$search}%")
                            ->orWhere('numero_documento', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filter by entrega status.
        // "pendiente" = tiene cantidad_pendiente > 0 O tiene entregas activas (pe/ec).
        // "completa"  = cantidad_pendiente = 0 Y sin entregas activas (pe/ec).
        // Ambas condiciones deben mantenerse en sync con la lógica de la columna
        // "Entrega" en el frontend (columns-mis-ventas).
        if ($request->has('entrega')) {
            if ($request->entrega === 'pendiente') {
                $query->where(function ($q) {
                    $q->whereHas('productosPorAlmacen.unidadesDerivadas', function ($q2) {
                        $q2->where('cantidad_pendiente', '>', 0);
                    })->orWhereHas('entregas.estadoEntrega', function ($q2) {
                        $q2->whereIn('codigo', ['pe', 'ec']);
                    });
                });
            } elseif ($request->entrega === 'completa') {
                $query->whereDoesntHave('productosPorAlmacen.unidadesDerivadas', function ($q) {
                    $q->where('cantidad_pendiente', '>', 0);
                })->whereDoesntHave('entregas.estadoEntrega', function ($q) {
                    $q->whereIn('codigo', ['pe', 'ec']);
                });
            }
        }

        // Filter por ediciones — `?editada=si` muestra solo ventas que tienen
        // al menos una edición en el historial; `?editada=no` muestra las
        // que nunca se editaron.
        if ($request->has('editada')) {
            if ($request->editada === 'si') {
                $query->whereHas('historial', function ($q) {
                    $q->where('accion', 'edicion');
                });
            } elseif ($request->editada === 'no') {
                $query->whereDoesntHave('historial', function ($q) {
                    $q->where('accion', 'edicion');
                });
            }
        }

        $perPage = $request->input('per_page', 50);

        if ($perPage === -1) {
            // Return all without pagination
            return response()->json([
                'data' => $query->orderBy('fecha', 'desc')->orderBy('numero', 'desc')->limit(100)->get(),
                'total' => $query->count(),
            ]);
        }

        $ventas = $query->orderBy('fecha', 'desc')->orderBy('numero', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $ventas->items(),
            'total' => $ventas->total(),
            'current_page' => $ventas->currentPage(),
            'per_page' => $ventas->perPage(),
            'last_page' => $ventas->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'sometimes|string',
            'tipo_documento' => 'required|string',
            'serie' => 'nullable|string', // Opcional: Se genera automáticamente
            'numero' => 'nullable|integer', // Opcional: Se genera automáticamente
            'descripcion' => 'nullable|string',
            'forma_de_pago' => 'required|string',
            'numero_dias' => 'nullable|integer',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_moneda' => 'required|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'fecha' => 'required|date',
            'estado_de_venta' => 'required|string',
            'canal' => 'nullable|string|in:presencial,web',
            'tipo_despacho' => 'nullable|string|in:et,do,pa,oc',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'omitir_entrega' => 'sometimes|boolean',
            // descontar_stock=no significa "el cliente ya tiene el producto":
            // NO descontar stock pero SÍ crear la entrega como ENTREGADA.
            'descontar_stock' => 'sometimes|string|in:si,no',
            // stock_ya_aplicado=true: la cotización origen ya reservó el stock,
            // no descontar de nuevo pero marcar stock_aplicado=true en la venta.
            'stock_ya_aplicado' => 'sometimes|boolean',
            'cliente_id' => 'nullable|integer', // Nullable para boletas y notas de venta
            'direccion_seleccionada' => 'nullable|string|in:D1,D2,D3,D4', // Nueva validación
            'recomendado_por_id' => 'nullable|integer',
            'user_id' => 'required|string',
            'almacen_id' => 'required|integer',
            'productos_por_almacen' => 'required_without:servicios_venta|array',
            'productos_por_almacen.*.costo' => 'required|numeric',
            'productos_por_almacen.*.producto_almacen_id' => 'sometimes|integer',
            'productos_por_almacen.*.producto_id' => 'sometimes|integer',
            'productos_por_almacen.*.paquete_id' => 'nullable|integer',
            'productos_por_almacen.*.paquete_nombre' => 'nullable|string|max:255',
            'productos_por_almacen.*.unidades_derivadas' => 'required|array',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_name' => 'sometimes|string',
            'productos_por_almacen.*.unidades_derivadas.*.factor' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad_pendiente' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.precio' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.recargo' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.descuento_tipo' => 'nullable|string',
            'productos_por_almacen.*.unidades_derivadas.*.descuento' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.comision' => 'nullable|numeric',
            'despliegue_de_pago_ventas' => 'sometimes|array',
            'despliegue_de_pago_ventas.*.despliegue_de_pago_id' => 'required|string',
            'despliegue_de_pago_ventas.*.sub_caja_id' => 'nullable|integer',
            'despliegue_de_pago_ventas.*.monto' => 'required|numeric',
            'despliegue_de_pago_ventas.*.numero_operacion' => 'nullable|string|max:100',
            'despliegue_de_pago_ventas.*.referencia' => 'nullable|string|max:191',
            'despliegue_de_pago_ventas.*.recibe_efectivo' => 'nullable|numeric',
            'ingreso_dinero_id' => 'nullable|string',
            // Servicios
            'servicios_venta' => 'sometimes|array',
            'servicios_venta.*.servicio_id' => 'required|integer|exists:servicio,id',
            'servicios_venta.*.cantidad' => 'required|numeric|min:0.001',
            'servicios_venta.*.precio_unitario' => 'required|numeric|min:0',
            'servicios_venta.*.subtotal' => 'required|numeric|min:0',
            'servicios_venta.*.referencia' => 'nullable|string|max:200',
            // Vale de compra (código de vale generado para canjear)
            'codigo_vale' => 'nullable|string|max:50',
            // Vales excluidos por el vendedor en la UI
            'vales_excluidos' => 'nullable|array',
            'vales_excluidos.*' => 'integer',
        ]);

        return DB::transaction(function () use ($validated) {

            // Si no se proporciona cliente_id, usar "CLIENTE VARIOS" (DNI: 99999999)
            if (empty($validated['cliente_id'])) {
                $clienteVarios = \App\Models\Cliente::where('numero_documento', '99999999')->first();

                if (! $clienteVarios) {
                    throw new \Exception("No se encontró el cliente 'CLIENTE VARIOS' (DNI: 99999999). Por favor, créelo en la base de datos.");
                }

                $validated['cliente_id'] = $clienteVarios->id;
            }

            // Generar serie y número automáticamente si no se proporcionan.
            // No se generan para ventas En Espera (borrador) — se reservan al pasar a Creado
            // para no consumir correlativos ni generar huecos en la numeración SUNAT.
            // Usa SerieDocumentoService que garantiza atomicidad (sin race conditions).
            $estadoVentaTmp = $validated['estado_de_venta'] ?? 'cr';
            if ($estadoVentaTmp !== 'ee' && (empty($validated['serie']) || empty($validated['numero']))) {
                try {
                    $correlativo = $this->serieDocumentoService->reservarCorrelativoSimple(
                        $validated['tipo_documento'],
                        $validated['almacen_id']
                    );
                } catch (\Exception $e) {
                    abort(422, $e->getMessage());
                }
                $validated['serie']  = $correlativo['serie'];
                $validated['numero'] = $correlativo['numero'];
            }

            // ✅ VALIDACIÓN CRÍTICA: Tipo de documento vs tipo de cliente
            $cliente = \App\Models\Cliente::find($validated['cliente_id']);
            if (!$cliente) {
                throw new \Exception("Cliente no encontrado");
            }

            // Validar que Facturas (01) solo se emitan a clientes con RUC (11 dígitos)
            $esRuc = strlen($cliente->numero_documento) === 11;
            if ($validated['tipo_documento'] === '01' && !$esRuc) {
                return response()->json([
                    'message' => 'Las Facturas (01) solo pueden emitirse a clientes con RUC (11 dígitos). Para clientes con DNI (8 dígitos) debe emitir una Boleta (03).',
                    'error' => 'TIPO_DOCUMENTO_INVALIDO',
                    'cliente_numero_documento' => $cliente->numero_documento,
                    'cliente_tipo_cliente' => $cliente->tipo_cliente->value ?? null,
                    'tipo_comprobante_solicitado' => '01',
                ], 422);
            }

            // Validar nueva venta
            $this->validarNuevaVenta($validated);

            // Convert enums
            $estadoEnum = EstadoDeVenta::from($validated['estado_de_venta']);
            $formaDePagoEnum = FormaDePago::from($validated['forma_de_pago']);
            $tipoDocumentoEnum = TipoDocumento::from($validated['tipo_documento']);
            $tipoMonedaEnum = TipoMoneda::from($validated['tipo_moneda']);

            // Create venta
            $venta = Venta::create([
                'id' => $validated['id'] ?? (string) \Illuminate\Support\Str::ulid(),
                'tipo_documento' => $tipoDocumentoEnum,
                // serie/numero pueden ser null para ventas En Espera (se reservan al pasar a Creado)
                'serie' => $validated['serie'] ?? null,
                'numero' => $validated['numero'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'forma_de_pago' => $formaDePagoEnum,
                'numero_dias' => $validated['numero_dias'] ?? null,
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'tipo_moneda' => $tipoMonedaEnum,
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? 1,
                'fecha' => $validated['fecha'],
                'estado_de_venta' => $estadoEnum,
                'tipo_despacho' => $validated['tipo_despacho'] ?? null,
                'canal' => $validated['canal'] ?? 'presencial',
                'cliente_id' => $validated['cliente_id'],
                'direccion_seleccionada' => $validated['direccion_seleccionada'] ?? null, // Guardar dirección seleccionada
                'recomendado_por_id' => $validated['recomendado_por_id'] ?? null,
                'user_id' => $validated['user_id'],
                'almacen_id' => $validated['almacen_id'],
            ]);

            // Calcular costos PEPS ANTES de crear ProductoAlmacenVenta
            // Esto permite guardar el costo correcto en la venta
            $costosCalculados = []; // Guardar costos PEPS por producto
            $desglosePEPS = [];     // Desglose (lote anterior/actual) por producto, solo para el reporte de pérdidas
            $costService = app(\App\Services\Producto\ProductoCostoService::class);
            $loteService = app(\App\Services\Producto\ProductoLoteService::class);
            
            foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
                $productoAlmacen = null;
                if ($productoAlmacenId) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenId);
                } else if (isset($producto['producto_id'])) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                        ->where('almacen_id', $validated['almacen_id'])
                        ->first();
                }

                if (! $productoAlmacen) {
                    throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$validated['almacen_id']}");
                }

                // Calcular costo PEPS para este producto SIN mutar (simulación sobre
                // los lotes reales): cuánto costaría consumir la cantidad total ahora.
                $cantidadTotalProducto = 0;
                foreach ($producto['unidades_derivadas'] as $unidad) {
                    $cantidadTotalProducto += (float) $unidad['cantidad'] * (float) $unidad['factor'];
                }

                if ($cantidadTotalProducto > 0) {
                    $costosCalculados[$productoAlmacen->id] = $loteService->simularCostoConsumo($productoAlmacen, $cantidadTotalProducto);
                } else {
                    $costosCalculados[$productoAlmacen->id] = $productoAlmacen->costo ?? 0;
                }

                // Desglose PEPS (solo lectura, sobre el producto REAL aún sin consumir):
                // cuántas unidades salen del lote anterior y del actual. Solo para el reporte.
                $desglosePEPS[$productoAlmacen->id] = $costService->calcularDesglosePEPS($productoAlmacen, $cantidadTotalProducto);
            }

            // Create productos_por_almacen and unidades_derivadas
            foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
                $productoAlmacen = null;
                if ($productoAlmacenId) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenId);
                } else if (isset($producto['producto_id'])) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                        ->where('almacen_id', $validated['almacen_id'])
                        ->first();
                }

                if (! $productoAlmacen) {
                    throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$validated['almacen_id']}");
                }

                $productoAlmacenId = $productoAlmacen->id;
                
                // Costo PEPS consumido: los buckets ya incluyen el flete (costo real por lote),
                // así que este valor es el costo real con flete del/los lote(s) vendidos.
                $costoPEPS = $costosCalculados[$productoAlmacenId] ?? ($productoAlmacen->costo ?? 0);
                $desg = $desglosePEPS[$productoAlmacenId] ?? null;

                $productoAlmacenVenta = ProductoAlmacenVenta::create([
                    'venta_id' => $venta->id,
                    'costo' => $costoPEPS,
                    // Desglose PEPS (lote anterior/actual) para el análisis de pérdidas. Solo reporte.
                    'cant_costo_anterior' => $desg['cant_anterior'] ?? 0,
                    'costo_anterior' => $desg['costo_anterior'] ?? null,
                    'cant_costo_actual' => $desg['cant_actual'] ?? 0,
                    'costo_actual' => $desg['costo_actual'] ?? null,
                    'producto_almacen_id' => $productoAlmacenId,
                    'paquete_id' => $producto['paquete_id'] ?? null,
                    'paquete_nombre' => $producto['paquete_nombre'] ?? null,
                ]);

                foreach ($producto['unidades_derivadas'] as $unidad) {
                    // Get unidad_derivada_inmutable_id (either provided or firstOrCreate by name)
                    $unidadDerivadaInmutableId = $unidad['unidad_derivada_inmutable_id'] ?? null;

                    if (! $unidadDerivadaInmutableId && isset($unidad['unidad_derivada_inmutable_name'])) {
                        $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                            ['name' => $unidad['unidad_derivada_inmutable_name']],
                            ['name' => $unidad['unidad_derivada_inmutable_name']]
                        );
                        $unidadDerivadaInmutableId = $unidadDerivadaInmutable->id;
                    }

                    UnidadDerivadaInmutableVenta::create([
                        'producto_almacen_venta_id' => $productoAlmacenVenta->id,
                        'unidad_derivada_inmutable_id' => $unidadDerivadaInmutableId,
                        'factor' => $unidad['factor'],
                        'cantidad' => $unidad['cantidad'],
                        'cantidad_pendiente' => $unidad['cantidad_pendiente'],
                        'precio' => $unidad['precio'],
                        'recargo' => $unidad['recargo'] ?? 0,
                        'descuento_tipo' => $unidad['descuento_tipo'] ?? 'm',
                        'descuento' => $unidad['descuento'] ?? 0,
                        'comision' => $unidad['comision'] ?? 0,
                    ]);
                }
            }

            // Descontar stock si el tipo de despacho es En Tienda o Domicilio
            // (Parcial se descuenta al momento de entregar, no al vender)
            // No descontar si la venta está "en espera" (no es una venta finalizada)
            // No descontar si se omite la entrega: el stock se descontará cuando
            // se cree la entrega manualmente desde Mis Ventas.
            // No descontar si descontar_stock='no' (caso: el cliente ya tiene
            // el producto físicamente; solo se registra la venta administrativa).
            $tipoDespacho = $validated['tipo_despacho'] ?? null;
            $estadoVentaStr = $validated['estado_de_venta'] ?? 'cr';
            $omitirEntrega = (bool) ($validated['omitir_entrega'] ?? false);
            $noDescontarStock = ($validated['descontar_stock'] ?? 'si') === 'no';
            $stockYaAplicado = (bool) ($validated['stock_ya_aplicado'] ?? false);
            $debeDescontar = in_array($tipoDespacho, ['et', 'do', 'pa', 'oc'])
                && $estadoVentaStr !== 'ee'
                && ! $omitirEntrega
                && ! $noDescontarStock
                && ! $stockYaAplicado;
            
            // CAPTURAR STOCK ANTERIOR ANTES DE DECREMENTAR (para kardex)
            // Capturar SIEMPRE si no está en espera (porque se registrará en kardex)
            $stocksAnteriores = [];
            if ($estadoVentaStr !== 'ee') {
                foreach ($validated['productos_por_almacen'] ?? [] as $idx => $producto) {
                    $pAlmacenId = $producto['producto_almacen_id'] ?? null;
                    if (! $pAlmacenId && isset($producto['producto_id'])) {
                        $pAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $validated['almacen_id'])
                            ->first();
                        $pAlmacenId = $pAlmacen?->id;
                    } else {
                        $pAlmacen = ProductoAlmacen::find($pAlmacenId);
                    }

                    if ($pAlmacen) {
                        // Guardar stock anterior ANTES de decrementar
                        $stocksAnteriores[$pAlmacenId] = (float) $pAlmacen->stock_fraccion;
                    }
                }
            }
            
            if ($debeDescontar) {
                foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                    $pAlmacenId = $producto['producto_almacen_id'] ?? null;
                    if (! $pAlmacenId && isset($producto['producto_id'])) {
                        $pAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $validated['almacen_id'])
                            ->first();
                        $pAlmacenId = $pAlmacen?->id;
                    } else {
                        $pAlmacen = ProductoAlmacen::find($pAlmacenId);
                    }

                    if (! $pAlmacen) continue;

                    $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                    foreach ($producto['unidades_derivadas'] as $unidad) {
                        $cantidadEnFraccion = (float) $unidad['cantidad'] * (float) $unidad['factor'];

                        // Consumir lotes PEPS y registrar el consumo (para anular/reportes)
                        $loteService->consumirLotes($pAlmacen, $cantidadEnFraccion, ['tipo' => 'venta', 'id' => $venta->id]);

                        // Descontar producto complementario si existe
                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $pAlmacen->id,
                            (float) $unidad['factor'],
                            (float) $unidad['cantidad'],
                            $validated['almacen_id'],
                            false // salida
                        );
                    }
                }
            }

            // Marcar si el stock fue aplicado al crear la venta.
            // stock_ya_aplicado=true: cotización origen reservó el stock.
            $venta->stock_aplicado = $debeDescontar || $stockYaAplicado;
            // descuenta_stock=false (venta administrativa, "no descontar stock"):
            // el producto ya salió físicamente → NO cuenta en el reporte de Ganancias.
            $venta->descuenta_stock = ! $noDescontarStock;
            $venta->save();

            // Auto-crear entrega para ventas de Recojo en Tienda (tipo_despacho='et').
            //
            // Estado inicial según quien_entrega:
            //  - vendedor: el vendedor ya está con el cliente en caja, la
            //    entrega ocurre AHORA → 'en' (entregado).
            //  - almacen / chofer: el cliente debe pasar al almacén, alguien
            //    de allá le entrega físicamente → 'pe' (pendiente) hasta que
            //    se marque desde Mis Entregas. user_entregado_id queda null.
            //
            //  Antes estaba hardcoded 'en' siempre, lo que hacía que la venta
            //  apareciera ya entregada aunque el cliente todavía no hubiera
            //  pasado al almacén.
            // Crear entrega automática para En Tienda. Casos:
            //  - Flujo normal (descontar_stock=si, no omitir): se crea con
            //    estado según quien_entrega (vendedor='en', almacen/chofer='pe').
            //  - descontar_stock=no: se crea SIEMPRE como 'en' (el cliente
            //    ya tiene el producto). NO descuenta stock pero registra la
            //    entrega completada.
            //  - omitir_entrega=true: NO se crea (queda pendiente para que
            //    el usuario la programe manualmente desde Mis Ventas).
            $autoCrearEntrega = $tipoDespacho === 'et'
                && $estadoVentaStr !== 'ee'
                && ! $omitirEntrega;
            if ($autoCrearEntrega) {
                // descontar_stock=no → el cliente YA TIENE la mercadería.
                // Asumimos que el vendedor la entregó (no queda stock físico
                // que mover, no hay almacén que "despachar"). Por eso forzamos
                // quien_entrega='vendedor' y estado='en' independientemente del
                // valor que mande el front. Para los otros casos respetamos lo
                // que diga el payload o caemos a 'almacen' como default histórico.
                $quienEntregaAuto = $noDescontarStock
                    ? 'vendedor'
                    : ($validated['quien_entrega'] ?? 'almacen');
                $estadoEntregaAuto = $noDescontarStock
                    ? 'en'  // descontar_stock=no → ya entregado siempre
                    : ($quienEntregaAuto === 'vendedor' ? 'en' : 'pe');

                $unidadesVenta = UnidadDerivadaInmutableVenta::whereHas(
                    'productoAlmacenVenta',
                    fn ($q) => $q->where('venta_id', $venta->id)
                )->get();

                // La auto-entrega compromete TODOS los items en entrega_detalle.
                // cantidad_pendiente = 0 en todos los casos: tanto 'en' (ya entregado)
                // como 'pe' (programado, pendiente de confirmar). El stock comprometido
                // no debe contarse como "por programar" — es tarea del almacén
                // confirmarlo desde Mis Entregas, no re-programarlo desde Mis Ventas.
                foreach ($unidadesVenta as $unidad) {
                    $unidad->update(['cantidad_pendiente' => 0]);
                }

                $this->entregaService->crearSync([
                    'venta_id'          => $venta->id,
                    'tipo_entrega'      => 'rt',
                    'tipo_despacho'     => 'in',
                    'estado_entrega'    => $estadoEntregaAuto,
                    'quien_entrega'     => $quienEntregaAuto,
                    'almacen_salida_id' => $validated['almacen_id'],
                    'user_creador_id'   => $validated['user_id'],
                    'user_entregado_id' => $estadoEntregaAuto === 'en' ? $validated['user_id'] : null,
                    'fecha_creacion'    => now()->toDateString(),
                    'fecha_ejecutada'   => $estadoEntregaAuto === 'en' ? now()->toDateTimeString() : null,
                    'tipo_pedido'       => 'interno',
                    'productos'         => $unidadesVenta->map(fn ($u) => [
                        'unidad_derivada_venta_id' => $u->id,
                        'cantidad'                 => (float) $u->cantidad,
                    ])->toArray(),
                ]);

                // Si la venta NO aplicó stock en la rama inicial (ej. crédito en
                // En Tienda), descontarlo aquí para que el comportamiento quede
                // alineado con Domicilio: la venta ya comprometió mercadería y
                // el stock debe bajar aunque termine negativo.
                if (! $venta->stock_aplicado && ! $noDescontarStock) {
                    foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                        $pAlmacenId = $producto['producto_almacen_id'] ?? null;
                        if (! $pAlmacenId && isset($producto['producto_id'])) {
                            $pAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                                ->where('almacen_id', $validated['almacen_id'])
                                ->first();
                        } else {
                            $pAlmacen = ProductoAlmacen::find($pAlmacenId);
                        }

                        if (! $pAlmacen) continue;

                        $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                        foreach ($producto['unidades_derivadas'] as $unidad) {
                            $cantidadEnFraccion = (float) $unidad['cantidad'] * (float) $unidad['factor'];

                            $loteService->consumirLotes($pAlmacen, $cantidadEnFraccion, ['tipo' => 'venta', 'id' => $venta->id]);

                            ComplementarioStockService::procesarComplementarioPorFactor(
                                $pAlmacen->id,
                                (float) $unidad['factor'],
                                (float) $unidad['cantidad'],
                                $validated['almacen_id'],
                                false // salida
                            );
                        }
                    }

                    $venta->stock_aplicado = true;
                    $venta->save();
                }
            }

            // Create despliegue_de_pago_ventas if provided
            if (isset($validated['despliegue_de_pago_ventas'])) {
                foreach ($validated['despliegue_de_pago_ventas'] as $desplieguePago) {
                    // Obtener el método de pago para calcular sobrecargo
                    $metodoPago = DespliegueDePago::find($desplieguePago['despliegue_de_pago_id']);

                    if (!$metodoPago) {
                        throw new \Exception("Método de pago no encontrado: {$desplieguePago['despliegue_de_pago_id']}");
                    }

                    // Validar si requiere número de operación
                    if ($metodoPago->requiere_numero_serie && 
                        (!isset($desplieguePago['numero_operacion']) || 
                         trim($desplieguePago['numero_operacion']) === '')) {
                        throw new \Exception("El método de pago '{$metodoPago->name}' requiere número de operación");
                    }

                    // Calcular sobrecargo
                    $sobrecargo = \App\Models\NumeroOperacionPago::calcularSobrecargo($metodoPago, $desplieguePago['monto']);
                    $montoTotal = $desplieguePago['monto'] + $sobrecargo;

                    // Registrar número de operación si existe
                    $numeroOperacionId = null;
                    if (isset($desplieguePago['numero_operacion']) && trim($desplieguePago['numero_operacion']) !== '') {
                        $numeroOperacion = \App\Models\NumeroOperacionPago::create([
                            'id' => (string) Str::ulid(),
                            'venta_id' => $venta->id,
                            'despliegue_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                            'numero_operacion' => $desplieguePago['numero_operacion'],
                            'monto' => $desplieguePago['monto'],
                            'sobrecargo_aplicado' => $sobrecargo,
                            'monto_total' => $montoTotal,
                            'fecha_operacion' => now(),
                            'user_id' => $validated['user_id'],
                        ]);
                        $numeroOperacionId = $numeroOperacion->id;
                    }

                    // Crear el registro de pago
                    DespliegueDePagoVenta::create([
                        'venta_id' => $venta->id,
                        'despliegue_de_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                        'monto' => $desplieguePago['monto'],
                        'numero_operacion_id' => $numeroOperacionId,
                        'sobrecargo_aplicado' => $sobrecargo,
                        'referencia' => $desplieguePago['referencia'] ?? null,
                        'recibe_efectivo' => $desplieguePago['recibe_efectivo'] ?? null,
                    ]);
                }
            }

            // Crear servicios de la venta si se proporcionan
            if (isset($validated['servicios_venta']) && !empty($validated['servicios_venta'])) {
                foreach ($validated['servicios_venta'] as $srv) {
                    ServicioVenta::create([
                        'venta_id' => $venta->id,
                        'servicio_id' => $srv['servicio_id'],
                        'cantidad' => $srv['cantidad'],
                        'precio_unitario' => $srv['precio_unitario'],
                        'subtotal' => $srv['subtotal'],
                        'referencia' => $srv['referencia'] ?? null,
                    ]);
                }
            }

            // Proceso post venta
            $validated['id'] = $venta->id;
            $this->procesoPostVenta($validated);

            // APLICAR VALES DE COMPRA AUTOMÁTICAMENTE
            try {
                $detallesVenta = $this->prepararDetallesVentaParaVales($validated);
                $this->valeCompraService->aplicarValesAutomaticos($venta, $detallesVenta, $validated['vales_excluidos'] ?? []);
            } catch (\Exception $e) {
                // No fallar la venta por error en vales
            }

            // CANJEAR VALE GENERADO (código de próxima compra)
            if (!empty($validated['codigo_vale'])) {
                try {
                    $this->valeCompraService->aplicarValeGenerado($validated['codigo_vale'], $venta, $detallesVenta);
                } catch (\Exception $e) {
                    // No fallar la venta por error en canje
                }
            }

            // REGISTRAR EN KARDEX FACTURACIÓN si la venta NO está en espera
            if ($estadoVentaStr !== 'ee') {
                try {
                    $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                    
                    // Cargar la relación cliente para el kardex
                    $venta->load('cliente');
                    
                    // Usar los datos del request directamente en lugar de recargar
                    foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                        $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
                        $productoAlmacen = null;
                        if ($productoAlmacenId) {
                            $productoAlmacen = ProductoAlmacen::with('producto')->find($productoAlmacenId);
                        } else if (isset($producto['producto_id'])) {
                            $productoAlmacen = ProductoAlmacen::with('producto')
                                ->where('producto_id', $producto['producto_id'])
                                ->where('almacen_id', $validated['almacen_id'])
                                ->first();
                            $productoAlmacenId = $productoAlmacen?->id;
                        }
                        
                        if (!$productoAlmacen) {
                            continue;
                        }

                        // Obtener el stock anterior capturado ANTES del decremento
                        $stockAnterior = $stocksAnteriores[$productoAlmacenId] ?? null;

                        foreach ($producto['unidades_derivadas'] as $unidad) {
                            $costo = (float) $producto['costo'];
                            $unidadData = [
                                'unidad_derivada_inmutable_name' => $unidad['unidad_derivada_inmutable_name'] ?? 'UNIDAD',
                                'cantidad' => (float) $unidad['cantidad'],
                                'factor' => (float) $unidad['factor'],
                                'precio' => (float) $unidad['precio'],
                            ];
                            
                            // Pasar el stock anterior capturado
                            $kardexFacturacionService->registrarVenta($venta, $productoAlmacen, $unidadData, $costo, 1, $stockAnterior);
                        }
                    }
                } catch (\Exception $e) {
                    // No fallar la venta si hay error al registrar en kardex
                    \Log::error('Error registrando en kardex facturación: ' . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
                }
            }

            // GENERAR COMPROBANTE ELECTRÓNICO AUTOMÁTICAMENTE
            // Solo para facturas (01) y boletas (03), y solo si la venta NO está En Espera.
            // En Espera = borrador no finalizado, no debe enviarse a SUNAT.
            $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum
                ? $venta->tipo_documento->value
                : $venta->tipo_documento;


            if (in_array($tipoDocumento, ['01', '03']) && $estadoVentaStr !== 'ee') {
                try {
                    $dto = new FacturaDTO(
                        ventaId: $venta->id,
                        usuarioId: $validated['user_id']
                    );
                    $this->facturaService->generarComprobanteDesdeVenta($dto);
                } catch (\Exception $e) {
                    // No fallar la venta si hay error al generar comprobante
                }
            }

            return response()->json([
                'data' => $venta->load([
                    'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono',
                    'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'despliegueDePagoVentas.despliegueDePago',
                    'serviciosVenta.servicio',
                    'user:id,name',
                    'almacen:id,name',
                    'valesAplicados.valeCompra',
                ]),
                'message' => 'Venta creada exitosamente',
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $venta = Venta::with([
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
            'cliente.direcciones',
            'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            // Cargar la configuración actual de unidades derivadas (precios + peso)
            // para que el frontend pueda calcular peso_total al crear guía/cotización.
            'productosPorAlmacen.productoAlmacen.unidadesDerivadas.unidadDerivada:id,name',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'despliegueDePagoVentas.despliegueDePago.metodoDePago',
            'serviciosVenta.servicio',
            'user:id,name',
            'almacen:id,name',
            // Lectura desde la tabla NUEVA (entrega) con los códigos de catálogo,
            // para reshapear a la forma plana legacy `entregas_productos`.
            'entregas.tipoEntrega:id,codigo',
            'entregas.estadoEntrega:id,codigo',
            'entregas.quienEntrega:id,codigo',
            'valesAplicados.valeCompra',
            'comprobanteElectronico:id,venta_id,tipo_comprobante,serie,correlativo,fecha_emision,estado_sunat,xml_path,xml_firmado,cdr_path,pdf_path,moneda,operacion_gravada,total_igv,importe_total',
        ])
            ->withSum('despliegueDePagoVentas as total_pagado', 'monto')
            ->findOrFail($id);

        // Reshapear las entregas (tabla nueva) a la forma plana que el front ya
        // consume bajo `entregas_productos` (tipo/estado/quien como códigos string).
        $codigo = fn ($cat) => $cat?->codigo instanceof \BackedEnum ? $cat->codigo->value : $cat?->codigo;
        $data = $venta->toArray();
        unset($data['entregas']);
        $data['entregas_productos'] = $venta->entregas->map(fn ($e) => [
            'id'                 => $e->id,
            'venta_id'           => $e->venta_id,
            'tipo_entrega'       => $codigo($e->tipoEntrega),
            'estado_entrega'     => $codigo($e->estadoEntrega),
            'quien_entrega'      => $codigo($e->quienEntrega),
            'chofer_id'          => $e->chofer_id,
            'vehiculo_id'        => $e->vehiculo_id,
            'fecha_programada'   => $e->fecha_programada?->toDateString(),
            'hora_inicio'        => $e->hora_inicio,
            'hora_fin'           => $e->hora_fin,
            'direccion_entrega'  => $e->direccion_entrega,
            'referencia_entrega' => $e->referencia_entrega,
            'latitud'            => $e->latitud,
            'longitud'           => $e->longitud,
            'observaciones'      => $e->observaciones,
            'tipo_pedido'        => $e->tipo_pedido,
            'cargo_destino'      => $e->cargo_destino,
        ])->all();
        $data['entregas_productos_count'] = count($data['entregas_productos']);

        return response()->json(['data' => $data]);
    }

    /**
     * Update the specified resource in storage (editar).
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'tipo_documento' => 'sometimes|string',
            'serie' => 'sometimes|string',
            'numero' => 'sometimes|integer',
            'descripcion' => 'nullable|string',
            'forma_de_pago' => 'sometimes|string',
            'numero_dias' => 'nullable|integer',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_moneda' => 'sometimes|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'fecha' => 'sometimes|date',
            'estado_de_venta' => 'sometimes|string',
            'tipo_despacho' => 'nullable|string|in:et,do,pa,oc',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer',
            'omitir_entrega' => 'sometimes|boolean',
            'cliente_id' => 'sometimes|integer',
            'direccion_seleccionada' => 'nullable|string|in:D1,D2,D3,D4', // Nueva validación
            'recomendado_por_id' => 'nullable|integer',
            'user_id' => 'sometimes|string',
            'almacen_id' => 'sometimes|integer',
            'productos_por_almacen' => 'sometimes|array',
            'productos_por_almacen.*.costo' => 'required|numeric',
            'productos_por_almacen.*.producto_almacen_id' => 'sometimes|integer',
            'productos_por_almacen.*.producto_id' => 'sometimes|integer',
            'productos_por_almacen.*.paquete_id' => 'nullable|integer',
            'productos_por_almacen.*.paquete_nombre' => 'nullable|string|max:255',
            'productos_por_almacen.*.unidades_derivadas' => 'required|array',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_id' => 'sometimes|integer',
            'productos_por_almacen.*.unidades_derivadas.*.unidad_derivada_inmutable_name' => 'sometimes|string',
            'productos_por_almacen.*.unidades_derivadas.*.factor' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.cantidad_pendiente' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.precio' => 'required|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.recargo' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.descuento_tipo' => 'nullable|string',
            'productos_por_almacen.*.unidades_derivadas.*.descuento' => 'nullable|numeric',
            'productos_por_almacen.*.unidades_derivadas.*.comision' => 'nullable|numeric',
            'despliegue_de_pago_ventas' => 'sometimes|array',
            'despliegue_de_pago_ventas.*.despliegue_de_pago_id' => 'required|string',
            'despliegue_de_pago_ventas.*.monto' => 'required|numeric',
            // Servicios
            'servicios_venta' => 'sometimes|array',
            'servicios_venta.*.servicio_id' => 'required|integer|exists:servicio,id',
            'servicios_venta.*.cantidad' => 'required|numeric|min:0.001',
            'servicios_venta.*.precio_unitario' => 'required|numeric|min:0',
            'servicios_venta.*.subtotal' => 'required|numeric|min:0',
            'servicios_venta.*.referencia' => 'nullable|string|max:200',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $venta = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'despliegueDePagoVentas',
                'comprobanteElectronico:id,venta_id,estado_sunat',
            ])->findOrFail($id);

            // Add id to validated data for validation
            $validated['id'] = $id;

            // ✅ FASE 1 — Bloquear edición cuando ya no es seguro modificar.
            // Ver plan-edicion-entregas.md (Escenario 1 vs 2/3).
            //
            // Caso A: SUNAT ya aceptó el comprobante → cualquier cambio rompe
            //   la trazabilidad fiscal. Para correcciones se debe usar Nota
            //   de Crédito (Escenario 3).
            $comprobante = $venta->comprobanteElectronico;
            $sunatAceptado = $comprobante && in_array(
                $comprobante->estado_sunat,
                ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES']
            );
            if ($sunatAceptado) {
                return response()->json([
                    'message' => 'No se puede editar: el comprobante ya fue aceptado por SUNAT. Para cambios usa Nota de Crédito.',
                    'error' => 'VENTA_SUNAT_ACEPTADA',
                ], 422);
            }

            // Caso B: antes se bloqueaba si había entregas en 'ec' o 'en' —
            // ahora se permite editar siempre. Los detalles de entrega se
            // regeneran para todas las entregas no canceladas (ver bloque al
            // final del update). El usuario asume el riesgo de inconsistencia
            // si quita productos que ya fueron entregados físicamente.

            // ✅ VALIDACIÓN CRÍTICA: Tipo de documento vs tipo de cliente (si se está cambiando)
            if (isset($validated['cliente_id']) || isset($validated['tipo_documento'])) {
                $clienteId = $validated['cliente_id'] ?? $venta->cliente_id;
                $tipoDocumento = $validated['tipo_documento'] ?? ($venta->tipo_documento instanceof \BackedEnum ? $venta->tipo_documento->value : $venta->tipo_documento);
                
                $cliente = \App\Models\Cliente::find($clienteId);
                if (!$cliente) {
                    throw new \Exception("Cliente no encontrado");
                }

                // Validar que Facturas (01) solo se emitan a clientes con RUC
                $clienteTipoDoc = $cliente->tipo_documento;
                // Inferir tipo_documento del número si es null
                if (!$clienteTipoDoc) {
                    $numDoc = $cliente->numero_documento ?? '';
                    $clienteTipoDoc = strlen($numDoc) === 11 ? 'ruc' : (strlen($numDoc) === 8 ? 'dni' : null);
                }
                if ($tipoDocumento === '01' && $clienteTipoDoc !== 'ruc') {
                    return response()->json([
                        'message' => 'Las Facturas (01) solo pueden emitirse a clientes con RUC. Para clientes con DNI debe emitir una Boleta (03).',
                        'error' => 'TIPO_DOCUMENTO_INVALIDO',
                        'cliente_tipo_documento' => $cliente->tipo_documento,
                        'tipo_comprobante_solicitado' => '01',
                    ], 422);
                }
            }

            // Validar nueva venta
            $this->validarNuevaVenta($validated);

            // Devolver dinero de venta anterior
            $this->devolverDineroDeVenta($venta);

            // Convert enums if present
            $updateData = [];
            foreach ($validated as $key => $value) {
                if ($key === 'estado_de_venta') {
                    $updateData[$key] = EstadoDeVenta::from($value);
                } elseif ($key === 'forma_de_pago') {
                    $updateData[$key] = FormaDePago::from($value);
                } elseif ($key === 'tipo_documento') {
                    $updateData[$key] = TipoDocumento::from($value);
                } elseif ($key === 'tipo_moneda') {
                    $updateData[$key] = TipoMoneda::from($value);
                } elseif ($key !== 'productos_por_almacen' && $key !== 'despliegue_de_pago_ventas' && $key !== 'servicios_venta' && $key !== 'id' && $key !== 'omitir_entrega') {
                    $updateData[$key] = $value;
                }
            }

            // Capturar datos anteriores para historial (incluye detalle de productos)
            $venta->load(['productosPorAlmacen.productoAlmacen.producto', 'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable']);
            $datosAnteriores = [
                'tipo_documento' => $venta->tipo_documento instanceof \BackedEnum ? $venta->tipo_documento->value : $venta->tipo_documento,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'forma_de_pago' => $venta->forma_de_pago instanceof \BackedEnum ? $venta->forma_de_pago->value : $venta->forma_de_pago,
                'estado_de_venta' => $venta->estado_de_venta instanceof \BackedEnum ? $venta->estado_de_venta->value : $venta->estado_de_venta,
                'cliente_id' => $venta->cliente_id,
                'fecha' => $venta->fecha?->toDateTimeString(),
                'tipo_moneda' => $venta->tipo_moneda instanceof \BackedEnum ? $venta->tipo_moneda->value : $venta->tipo_moneda,
                'numero_dias' => $venta->numero_dias,
                'fecha_vencimiento' => $venta->fecha_vencimiento?->toDateTimeString(),
                'descripcion' => $venta->descripcion,
                'productos_count' => $venta->productosPorAlmacen->count(),
                'productos' => $venta->productosPorAlmacen->map(function ($pav) {
                    return [
                        'nombre' => $pav->productoAlmacen?->producto?->name,
                        'codigo' => $pav->productoAlmacen?->producto?->cod_producto,
                        'costo' => $pav->costo,
                        'unidades' => $pav->unidadesDerivadas->map(function ($ud) {
                            return [
                                'unidad' => $ud->unidadDerivadaInmutable?->name,
                                'cantidad' => $ud->cantidad,
                                'precio' => $ud->precio,
                                'descuento' => $ud->descuento,
                                'descuento_tipo' => $ud->descuento_tipo,
                                'recargo' => $ud->recargo,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];

            // Capturar estado anterior antes del update
            $estadoAnterior = $venta->estado_de_venta instanceof \BackedEnum
                ? $venta->estado_de_venta->value
                : $venta->estado_de_venta;

            // Capturar tipo_despacho anterior y snapshot de cantidades previas
            // para poder decidir si revertir/aplicar stock correctamente
            $tipoDespachoAnterior = $venta->tipo_despacho instanceof \BackedEnum
                ? $venta->tipo_despacho->value
                : $venta->tipo_despacho;

            // Usar el flag persistido en la venta (refleja realmente si el stock fue
            // descontado al crear, considerando casos como "omitir entrega").
            $stockDescontadoAntes = (bool) $venta->stock_aplicado;

            $snapshotUnidadesAnteriores = [];
            if ($stockDescontadoAntes) {
                foreach ($venta->productosPorAlmacen as $pav) {
                    foreach ($pav->unidadesDerivadas as $ud) {
                        $snapshotUnidadesAnteriores[] = [
                            'producto_almacen_id' => $pav->producto_almacen_id,
                            'cantidad' => (float) $ud->cantidad,
                            'factor' => (float) $ud->factor,
                        ];
                    }
                }
            }

            // Update venta
            $venta->update($updateData);

            // Si cambió el tipo_documento, re-asignar correlativo para que
            // la serie corresponda al nuevo tipo (B001→Boleta, F001→Factura).
            $tipoDocAnterior = $datosAnteriores['tipo_documento'];
            $tipoDocNuevoVal = $venta->tipo_documento instanceof \BackedEnum
                ? $venta->tipo_documento->value
                : $venta->tipo_documento;

            if ($tipoDocAnterior !== $tipoDocNuevoVal
                && in_array($tipoDocNuevoVal, ['01', '03', 'nv'], true)
                && ($estadoAnterior ?? 'cr') !== 'ee'
            ) {
                try {
                    $nuevoCorrelativo = $this->serieDocumentoService->reservarCorrelativoSimple(
                        $tipoDocNuevoVal,
                        $venta->almacen_id
                    );
                    $venta->update([
                        'serie'  => $nuevoCorrelativo['serie'],
                        'numero' => $nuevoCorrelativo['numero'],
                    ]);
                    $venta->refresh();
                } catch (\Exception $e) {
                    \Log::warning('No se pudo re-asignar correlativo al cambiar tipo_documento', [
                        'venta_id'       => $venta->id,
                        'tipo_anterior'  => $tipoDocAnterior,
                        'tipo_nuevo'     => $tipoDocNuevoVal,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }

            // Si se recupera una venta anulada, reactivar entregas canceladas
            $estadoNuevo = isset($updateData['estado_de_venta'])
                ? ($updateData['estado_de_venta'] instanceof \BackedEnum ? $updateData['estado_de_venta']->value : $updateData['estado_de_venta'])
                : $estadoAnterior;
            if ($estadoAnterior === 'an' && $estadoNuevo === 'cr') {
                // Reactivar entregas canceladas en la tabla NUEVA (estado por FK).
                \App\Models\Entrega::where('venta_id', $id)
                    ->where('estado_entrega_id', \App\Models\EstadoEntrega::where('codigo', 'ca')->value('id'))
                    ->update(['estado_entrega_id' => \App\Models\EstadoEntrega::where('codigo', 'pe')->value('id')]);
            }

            // Si la venta sale de "En Espera", actualizar la fecha a HOY (la venta
            // se concreta en la fecha actual, no en la fecha original del borrador).
            if ($estadoAnterior === 'ee' && $estadoNuevo !== 'ee') {
                $venta->update(['fecha' => now()->toDateString()]);
            }

            // Si transición En Espera → Creado y la venta aún no tiene serie/numero,
            // reservar correlativo ahora (se difirió en store para no consumir números
            // en borradores). Usa servicio atómico para evitar duplicados.
            if ($estadoAnterior === 'ee' && $estadoNuevo !== 'ee' && (empty($venta->serie) || empty($venta->numero))) {
                $tipoDocVenta = $venta->tipo_documento instanceof \BackedEnum
                    ? $venta->tipo_documento->value
                    : $venta->tipo_documento;
                $correlativo = $this->serieDocumentoService->reservarCorrelativoSimple(
                    $tipoDocVenta,
                    $venta->almacen_id
                );
                $venta->update([
                    'serie'  => $correlativo['serie'],
                    'numero' => $correlativo['numero'],
                ]);
                $venta->refresh();

                // REGISTRAR EN KARDEX FACTURACIÓN cuando cambia de 'ee' a otro estado
                try {
                    $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                    $ventaConRelaciones = Venta::with([
                        'productosPorAlmacen.productoAlmacen.producto',
                        'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                        'cliente',
                    ])->findOrFail($venta->id);

                    foreach ($ventaConRelaciones->productosPorAlmacen as $detalle) {
                        $productoAlmacen = $detalle->productoAlmacen;
                        if (!$productoAlmacen) continue;

                        foreach ($detalle->unidadesDerivadas as $ud) {
                            $costo = (float) $detalle->costo;
                            $kardexFacturacionService->registrarVenta($ventaConRelaciones, $productoAlmacen, $ud, $costo);
                        }
                    }
                } catch (\Exception $e) {
                    // No fallar la venta si hay error al registrar en kardex
                }
            }

            // If productos_por_almacen is provided, update them
            if (isset($validated['productos_por_almacen'])) {
                // ──────────────────────────────────────────────────────────
                // SNAPSHOT antes de borrar — preservar lo entregado.
                //
                // Al recrear los productos perdemos las UDV viejas (y por FK
                // cascade los detalles de entrega). Para mantener la
                // trazabilidad de cuánto se entregó realmente en cada entrega
                // activa, capturamos:
                //
                //   $entregadoAcumulado["$productoAlmacenId:$unidadName"] =
                //       total entregado en TODAS las entregas no canceladas
                //       (sirve para calcular cantidad_pendiente nuevo).
                //
                //   $detallesPorEntrega[$entregaId]["$productoAlmacenId:$unidadName"] =
                //       cantidad entregada en ESA entrega (sirve para
                //       regenerar el detalle preservando el valor real).
                //
                // Antes el código sobrescribía `cantidad_entregada = cantidad`
                // total y `cantidad_pendiente = cantidad`, con lo que al
                // editar una venta con entregas previas el sistema "olvidaba"
                // qué se había entregado y reseteaba el pendiente al total.
                // ──────────────────────────────────────────────────────────
                $entregadoAcumulado = [];
                $detallesPorEntrega = [];
                $entregasActivas = \App\Models\Entrega::where('venta_id', $id)
                    ->whereHas('estadoEntrega', fn ($q) => $q->whereIn('codigo', ['pe', 'ec', 'en']))
                    ->with([
                        'detalles.unidadDerivadaVenta.productoAlmacenVenta',
                        'detalles.unidadDerivadaVenta.unidadDerivadaInmutable',
                    ])
                    ->get();

                foreach ($entregasActivas as $entrega) {
                    foreach ($entrega->detalles as $detalle) {
                        $udv = $detalle->unidadDerivadaVenta;
                        if (! $udv) continue;
                        $pav = $udv->productoAlmacenVenta;
                        $unidadInmutable = $udv->unidadDerivadaInmutable;
                        if (! $pav || ! $unidadInmutable) continue;

                        $clave = $pav->producto_almacen_id . ':' . $unidadInmutable->name;
                        // Modelo nuevo: detalle.cantidad = lo COMPROMETIDO en esa
                        // entrega; se usa como cobertura para cantidad_pendiente.
                        $cant = (float) $detalle->cantidad;
                        $entregadoAcumulado[$clave] = ($entregadoAcumulado[$clave] ?? 0.0) + $cant;
                        $detallesPorEntrega[$entrega->id][$clave] = $cant;
                    }
                }

                // Snapshot de detalles de entregas CANCELADAS para preservar las
                // cantidades originales. Sin esto, al editar la venta se pierden
                // los detalles cancelados y allEntregadoMap nunca puede superar
                // el nuevo total, haciendo que devolvio=0 siempre.
                $entregasCanceladas = \App\Models\Entrega::where('venta_id', $id)
                    ->whereHas('estadoEntrega', fn ($q) => $q->where('codigo', 'ca'))
                    ->with([
                        'detalles.unidadDerivadaVenta.productoAlmacenVenta',
                        'detalles.unidadDerivadaVenta.unidadDerivadaInmutable',
                    ])
                    ->get();

                $detallesCancelados = [];
                foreach ($entregasCanceladas as $entrega) {
                    foreach ($entrega->detalles as $detalle) {
                        $udv = $detalle->unidadDerivadaVenta;
                        if (! $udv) continue;
                        $pav = $udv->productoAlmacenVenta;
                        $unidadInmutable = $udv->unidadDerivadaInmutable;
                        if (! $pav || ! $unidadInmutable) continue;
                        $clave = $pav->producto_almacen_id . ':' . $unidadInmutable->name;
                        $detallesCancelados[$entrega->id][$clave] = (float) $detalle->cantidad;
                    }
                }

                // Eliminar registros hijos en orden correcto para evitar FK constraint
                $productoAlmacenVentaIds = ProductoAlmacenVenta::where('venta_id', $id)->pluck('id');
                $unidadDerivadaVentaIds = UnidadDerivadaInmutableVenta::whereIn('producto_almacen_venta_id', $productoAlmacenVentaIds)->pluck('id');
                \App\Models\EntregaDetalle::whereIn('unidad_derivada_venta_id', $unidadDerivadaVentaIds)->delete();

                // Delete existing productos_por_almacen (cascades to unidadderivadainmutableventa)
                ProductoAlmacenVenta::where('venta_id', $id)->delete();

                // Track de las UDV nuevas por clave (productoAlmacenId:unidadName)
                // para poder regenerar los detalles después.
                $nuevasUdvPorClave = [];

                // Create new productos_por_almacen
                foreach ($validated['productos_por_almacen'] as $producto) {
                    // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                    $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
                    $productoAlmacen = null;
                    if ($productoAlmacenId) {
                        $productoAlmacen = ProductoAlmacen::find($productoAlmacenId);
                    } else if (isset($producto['producto_id'])) {
                        $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $venta->almacen_id)
                            ->first();
                    }

                    if (! $productoAlmacen) {
                        throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$venta->almacen_id}");
                    }

                    $productoAlmacenId = $productoAlmacen->id;

                    $productoAlmacenVenta = ProductoAlmacenVenta::create([
                        'venta_id' => $venta->id,
                        'costo' => (isset($producto['costo']) && $producto['costo'] > 0) ? $producto['costo'] : ($productoAlmacen->costo ?? 0),
                        'producto_almacen_id' => $productoAlmacenId,
                        'paquete_id' => $producto['paquete_id'] ?? null,
                        'paquete_nombre' => $producto['paquete_nombre'] ?? null,
                    ]);

                    foreach ($producto['unidades_derivadas'] as $unidad) {
                        // Get unidad_derivada_inmutable_id (either provided or firstOrCreate by name)
                        $unidadDerivadaInmutableId = $unidad['unidad_derivada_inmutable_id'] ?? null;
                        $unidadName = $unidad['unidad_derivada_inmutable_name'] ?? null;

                        if (! $unidadDerivadaInmutableId && $unidadName) {
                            $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                                ['name' => $unidadName],
                                ['name' => $unidadName]
                            );
                            $unidadDerivadaInmutableId = $unidadDerivadaInmutable->id;
                        } elseif ($unidadDerivadaInmutableId && ! $unidadName) {
                            // Si solo vino el id, recuperamos el nombre para la clave del snapshot.
                            $unidadName = optional(UnidadDerivadaInmutable::find($unidadDerivadaInmutableId))->name;
                        }

                        // cantidad_pendiente correcto = cantidad nueva − lo ya
                        // entregado en entregas activas para esta combinación
                        // (producto, unidad). Si el usuario reduce la cantidad
                        // por debajo de lo entregado, queda 0 (no negativo) —
                        // el frontend debería bloquear esto pero el backend
                        // protege igual.
                        $clave = $productoAlmacenId . ':' . $unidadName;
                        $cantidadNueva = (float) $unidad['cantidad'];
                        $entregadoYa = (float) ($entregadoAcumulado[$clave] ?? 0.0);
                        $cantidadPendiente = max(0.0, $cantidadNueva - $entregadoYa);

                        $udvNueva = UnidadDerivadaInmutableVenta::create([
                            'producto_almacen_venta_id' => $productoAlmacenVenta->id,
                            'unidad_derivada_inmutable_id' => $unidadDerivadaInmutableId,
                            'factor' => $unidad['factor'],
                            'cantidad' => $cantidadNueva,
                            'cantidad_pendiente' => $cantidadPendiente,
                            'precio' => $unidad['precio'],
                            'recargo' => $unidad['recargo'] ?? 0,
                            'descuento_tipo' => $unidad['descuento_tipo'] ?? 'm',
                            'descuento' => $unidad['descuento'] ?? 0,
                            'comision' => $unidad['comision'] ?? 0,
                        ]);

                        $nuevasUdvPorClave[$clave] = $udvNueva;
                    }
                }

                // Regenerar entrega_detalle cubriendo todos los productos
                // actuales de la venta para cada entrega activa.
                //
                // Lógica por producto:
                //  - Si EXISTÍA antes (estaba en el snapshot): se preserva
                //    `cantidad_entregada` original.
                //  - Si es NUEVO (no estaba antes): se crea con
                //    `cantidad_entregada = cantidad total` — la entrega ya
                //    pasa a 'pe' (re-confirmación), así el usuario revisa
                //    el modal antes de confirmar; si quiere entregar menos
                //    puede ajustar via "Entregar Restante" después.
                //  - Si el producto FUE ELIMINADO de la venta: no se
                //    recrea su detalle (el producto ya no existe).
                //
                // Antes la lógica solo recreaba detalles del snapshot,
                // dejando la entrega VACÍA si el usuario cambió todos los
                // productos en la edición.
                foreach ($entregasActivas as $entrega) {
                    $clavesSolicitadas = $detallesPorEntrega[$entrega->id] ?? [];
                    foreach ($nuevasUdvPorClave as $clave => $udvNueva) {
                        $cantSolicitada = array_key_exists($clave, $clavesSolicitadas)
                            ? min((float) $clavesSolicitadas[$clave], (float) $udvNueva->cantidad)
                            // Producto nuevo agregado en la edición — presume que
                            // se entregará todo cuando el usuario re-confirme la
                            // entrega ('pe' → 'en').
                            : (float) $udvNueva->cantidad;
                        \App\Models\EntregaDetalle::create([
                            'entrega_id' => $entrega->id,
                            'unidad_derivada_venta_id' => $udvNueva->id,
                            'cantidad' => $cantSolicitada,
                            'ubicacion' => null,
                        ]);
                    }
                }

                // Recrear entrega_detalle de entregas CANCELADAS con cantidades
                // originales (sin capear al nuevo total). Esto permite que
                // allEntregadoMap en el frontend refleje lo que se comprometió
                // históricamente, haciendo que devolvio = max(0, comprometido - nuevo_total) > 0
                // cuando la venta se redujo después de haber comprometido stock.
                foreach ($entregasCanceladas as $entrega) {
                    $clavesCancelada = $detallesCancelados[$entrega->id] ?? [];
                    foreach ($nuevasUdvPorClave as $clave => $udvNueva) {
                        if (array_key_exists($clave, $clavesCancelada)) {
                            \App\Models\EntregaDetalle::create([
                                'entrega_id'               => $entrega->id,
                                'unidad_derivada_venta_id' => $udvNueva->id,
                                'cantidad'                 => $clavesCancelada[$clave],
                                'ubicacion'                => null,
                            ]);
                        }
                    }
                }

                // Forzar re-confirmación de entregas tras editar la venta:
                // las entregas que estaban 'en' (entregado) o 'ec' (en camino)
                // pasan a 'pe' (pendiente) para que el usuario re-valide
                // físicamente antes de marcarlas como entregadas otra vez.
                // La `cantidad_entregada` de los detalles se preserva (arriba),
                // así que al re-entregar el sistema sabe cuánto quedó por
                // entregar realmente. Las canceladas ('ca') no se tocan.
                \App\Models\Entrega::where('venta_id', $id)
                    ->whereHas('estadoEntrega', fn ($q) => $q->whereIn('codigo', ['en', 'ec']))
                    ->update(['estado_entrega_id' => \App\Models\EstadoEntrega::where('codigo', 'pe')->value('id')]);
            }

            // Ajustar stock según transición de estado + tipo_despacho
            // Revertir stock anterior si ya se había descontado
            if ($stockDescontadoAntes) {
                $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                // Devolver el stock a los lotes exactos que consumió esta venta
                // (o reingresar si es una venta anterior al ledger). Una vez por
                // producto, usando el total del snapshot.
                $totalPorPa = [];
                foreach ($snapshotUnidadesAnteriores as $snap) {
                    $paid = $snap['producto_almacen_id'];
                    $totalPorPa[$paid] = ($totalPorPa[$paid] ?? 0) + ($snap['cantidad'] * $snap['factor']);
                }
                foreach ($totalPorPa as $paid => $totalFr) {
                    $pAlmacen = ProductoAlmacen::find($paid);
                    if (! $pAlmacen) continue;
                    $loteService->revertirConsumoOReingresar($pAlmacen, 'venta', $venta->id, (float) $totalFr);
                }

                // Revertir complementarios (igual que antes), por unidad
                foreach ($snapshotUnidadesAnteriores as $snap) {
                    ComplementarioStockService::procesarComplementarioPorFactor(
                        $snap['producto_almacen_id'],
                        $snap['factor'],
                        $snap['cantidad'],
                        $venta->almacen_id,
                        true // revertir (ingreso)
                    );
                }
            }

            // Aplicar nuevo descuento de stock si corresponde
            $tipoDespachoNuevo = $validated['tipo_despacho'] ?? $tipoDespachoAnterior;
            $omitirEntregaUpdate = (bool) ($validated['omitir_entrega'] ?? false);
            $noDescontarStockUpdate = ($validated['descontar_stock'] ?? 'si') === 'no';
            $descontarStockAhora = in_array($tipoDespachoNuevo, ['et', 'do', 'oc'])
                && $estadoNuevo !== 'ee'
                && ! $omitirEntregaUpdate
                && ! $noDescontarStockUpdate;
            if ($descontarStockAhora && isset($validated['productos_por_almacen'])) {
                foreach ($validated['productos_por_almacen'] as $producto) {
                    $pAlmacenId = $producto['producto_almacen_id'] ?? null;
                    if (! $pAlmacenId && isset($producto['producto_id'])) {
                        $pAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $venta->almacen_id)
                            ->first();
                        $pAlmacenId = $pAlmacen?->id;
                    } else {
                        $pAlmacen = ProductoAlmacen::find($pAlmacenId);
                    }
                    if (! $pAlmacen) continue;

                    $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                    foreach ($producto['unidades_derivadas'] as $unidad) {
                        $cantidadFraccion = (float) $unidad['cantidad'] * (float) $unidad['factor'];

                        // Consumir lotes PEPS y registrar el consumo (para anular/reportes)
                        $loteService->consumirLotes($pAlmacen, $cantidadFraccion, ['tipo' => 'venta', 'id' => $venta->id]);

                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $pAlmacen->id,
                            (float) $unidad['factor'],
                            (float) $unidad['cantidad'],
                            $venta->almacen_id,
                            false // salida
                        );
                    }
                }
            }

            // Reflejar el nuevo estado del flag tras el update
            $venta->stock_aplicado = $descontarStockAhora;
            $venta->save();

            // ── Auto-crear entrega EnTienda cuando no existe ninguna activa ──
            // Casos cubiertos:
            //  A) 'ee' → 'cr': store() la saltó porque era venta en espera.
            //  B) 'an' → 'cr': la venta fue anulada sin haber tenido entrega activa.
            //  C) Cualquier estado → edición normal: la única entrega existente
            //     fue cancelada (p.ej. devolución parcial) y se reedita la venta;
            //     se crea una nueva entrega con las cantidades actualizadas.
            //
            // Si ya existe alguna entrega ACTIVA (pe/ec/en), este bloque no
            // ejecuta — las cantidades se actualizan por el loop de regeneración.
            if (
                $tipoDespachoNuevo === 'et' &&
                ! in_array($estadoNuevo, ['ee', 'an']) &&
                ! $omitirEntregaUpdate &&
                ! \App\Models\Entrega::where('venta_id', $id)
                    ->whereHas('estadoEntrega', fn ($q) => $q->whereNotIn('codigo', ['ca']))
                    ->exists()
            ) {
                $quienEntregaAuto = $validated['quien_entrega'] ?? 'almacen';
                $estadoEntregaAuto = $noDescontarStockUpdate
                    ? 'en'
                    : ($quienEntregaAuto === 'vendedor' ? 'en' : 'pe');

                $unidadesVenta = UnidadDerivadaInmutableVenta::whereHas(
                    'productoAlmacenVenta',
                    fn ($q) => $q->where('venta_id', $venta->id)
                )->get();

                foreach ($unidadesVenta as $unidad) {
                    $unidad->update([
                        'cantidad_pendiente' => $estadoEntregaAuto === 'en'
                            ? 0
                            : (float) $unidad->cantidad,
                    ]);
                }

                // Crear la entrega en la tabla NUEVA (sin fila legacy).
                $this->entregaService->crearSync([
                    'venta_id'          => $venta->id,
                    'tipo_entrega'      => 'rt',
                    'tipo_despacho'     => 'in',
                    'estado_entrega'    => $estadoEntregaAuto,
                    'quien_entrega'     => $quienEntregaAuto,
                    'almacen_salida_id' => $validated['almacen_id'] ?? $venta->almacen_id,
                    'user_creador_id'   => $validated['user_id'] ?? $venta->user_id,
                    'user_entregado_id' => $estadoEntregaAuto === 'en' ? ($validated['user_id'] ?? $venta->user_id) : null,
                    'fecha_creacion'    => now()->toDateString(),
                    'fecha_ejecutada'   => $estadoEntregaAuto === 'en' ? now()->toDateTimeString() : null,
                    'tipo_pedido'       => 'interno',
                    'productos'         => $unidadesVenta->map(fn ($u) => [
                        'unidad_derivada_venta_id' => $u->id,
                        'cantidad'                 => (float) $u->cantidad,
                    ])->toArray(),
                ]);
            }

            // If despliegue_de_pago_ventas is provided, update them
            if (isset($validated['despliegue_de_pago_ventas'])) {
                // Delete existing despliegue_de_pago_ventas
                DespliegueDePagoVenta::where('venta_id', $id)->delete();

                // Create new despliegue_de_pago_ventas
                foreach ($validated['despliegue_de_pago_ventas'] as $desplieguePago) {
                    DespliegueDePagoVenta::create([
                        'venta_id' => $venta->id,
                        'despliegue_de_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                        'monto' => $desplieguePago['monto'],
                        'referencia' => $desplieguePago['referencia'] ?? null,
                        'recibe_efectivo' => $desplieguePago['recibe_efectivo'] ?? null,
                    ]);
                }
            } elseif ($venta->forma_de_pago === FormaDePago::Credito) {
                // La venta quedó a CRÉDITO: el dinero no ingresa al crear (queda como
                // cuenta por cobrar), así que no debe sobrevivir ningún método de pago
                // de una edición previa al contado. El payload no trae el array porque
                // el front no envía métodos en crédito, por eso el bloque anterior no
                // los limpiaría. devolverDineroDeVenta() ya revirtió los montos en caja;
                // aquí eliminamos las filas huérfanas para no dejar la venta inconsistente.
                DespliegueDePagoVenta::where('venta_id', $id)->delete();
            }

            // If servicios_venta is provided, update them
            if (isset($validated['servicios_venta'])) {
                ServicioVenta::where('venta_id', $id)->delete();

                foreach ($validated['servicios_venta'] as $srv) {
                    ServicioVenta::create([
                        'venta_id' => $venta->id,
                        'servicio_id' => $srv['servicio_id'],
                        'cantidad' => $srv['cantidad'],
                        'precio_unitario' => $srv['precio_unitario'],
                        'subtotal' => $srv['subtotal'],
                        'referencia' => $srv['referencia'] ?? null,
                    ]);
                }
            }

            // Proceso post venta
            $validated['id'] = $id;
            $this->procesoPostVenta($validated);

            // Comprobante electrónico (Boleta 03 / Factura 01) automático en update.
            // Solo si el estado nuevo NO es En Espera. Si ya existe → regenerar.
            // Si no existe y la venta sale de En Espera → generar nuevo.
            $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum
                ? $venta->tipo_documento->value
                : $venta->tipo_documento;

            if (in_array($tipoDocumento, ['01', '03']) && $estadoNuevo !== 'ee') {
                try {
                    $comprobanteExistente = \App\Models\ComprobanteElectronico::where('venta_id', $venta->id)->first();

                    if ($comprobanteExistente) {
                        \App\Models\DetalleComprobanteElectronico::where('comprobante_electronico_id', $comprobanteExistente->id)->delete();
                        $comprobanteExistente->delete();
                    }

                    if ($comprobanteExistente || $estadoAnterior === 'ee') {
                        $dto = new FacturaDTO(
                            ventaId: $venta->id,
                            usuarioId: $venta->user_id
                        );
                        $this->facturaService->generarComprobanteDesdeVenta($dto);
                    }
                } catch (\Exception $e) {
                    // No fallar el update si hay error al generar comprobante
                }
            }

            // Registrar historial de edición (con detalle de productos nuevos)
            $ventaFresh = $venta->fresh(['productosPorAlmacen.productoAlmacen.producto', 'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable']);
            $datosNuevos = [
                'tipo_documento' => $ventaFresh->tipo_documento instanceof \BackedEnum ? $ventaFresh->tipo_documento->value : $ventaFresh->tipo_documento,
                'serie' => $ventaFresh->serie,
                'numero' => $ventaFresh->numero,
                'forma_de_pago' => $ventaFresh->forma_de_pago instanceof \BackedEnum ? $ventaFresh->forma_de_pago->value : $ventaFresh->forma_de_pago,
                'estado_de_venta' => $ventaFresh->estado_de_venta instanceof \BackedEnum ? $ventaFresh->estado_de_venta->value : $ventaFresh->estado_de_venta,
                'cliente_id' => $ventaFresh->cliente_id,
                'fecha' => $ventaFresh->fecha?->toDateTimeString(),
                'tipo_moneda' => $ventaFresh->tipo_moneda instanceof \BackedEnum ? $ventaFresh->tipo_moneda->value : $ventaFresh->tipo_moneda,
                'numero_dias' => $ventaFresh->numero_dias,
                'fecha_vencimiento' => $ventaFresh->fecha_vencimiento?->toDateTimeString(),
                'descripcion' => $ventaFresh->descripcion,
                'productos_count' => $ventaFresh->productosPorAlmacen->count(),
                'productos' => $ventaFresh->productosPorAlmacen->map(function ($pav) {
                    return [
                        'nombre' => $pav->productoAlmacen?->producto?->name,
                        'codigo' => $pav->productoAlmacen?->producto?->cod_producto,
                        'costo' => $pav->costo,
                        'unidades' => $pav->unidadesDerivadas->map(function ($ud) {
                            return [
                                'unidad' => $ud->unidadDerivadaInmutable?->name,
                                'cantidad' => $ud->cantidad,
                                'precio' => $ud->precio,
                                'descuento' => $ud->descuento,
                                'descuento_tipo' => $ud->descuento_tipo,
                                'recargo' => $ud->recargo,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];

            VentaHistorial::registrar(
                ventaId: $id,
                accion: 'edicion',
                descripcion: "Venta {$ventaFresh->serie}-{$ventaFresh->numero} editada",
                datosAnteriores: $datosAnteriores,
                datosNuevos: $datosNuevos,
                userId: $validated['user_id'] ?? auth()->id(),
            );

            // Marcar venta como editada en kardex facturación
            try {
                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                $kardexFacturacionService->marcarVentaComoEditada($id);
            } catch (\Exception $e) {
                \Log::error('Error marcando venta como editada en kardex: ' . $e->getMessage());
            }

            return response()->json([
                'data' => $ventaFresh->load([
                    'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'despliegueDePagoVentas.despliegueDePago',
                    'serviciosVenta.servicio',
                    'user:id,name',
                    'almacen:id,name',
                ]),
                'message' => 'Venta actualizada exitosamente',
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (anular).
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $venta = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'productosPorAlmacen.productoAlmacen',
                'despliegueDePagoVentas',
                // Cobros activos — necesarios para revertirlos al anular
                // una venta Procesada (crédito ya pagado).
                'cobrosVenta' => fn ($q) => $q->where('estado', true),
                'cliente',
            ])
                ->withCount('entregas as entregas_productos_count')
                ->findOrFail($id);

            // Solo bloqueamos si ya está anulada (no se puede re-anular).
            // Antes también bloqueaba si estaba Procesada, pero ahora se permite
            // anular ventas a crédito ya pagadas — en ese caso revertimos los
            // cobros activos, decrementamos los métodos de pago y devolvemos
            // el dinero como si no se hubiera cobrado.
            if ($venta->estado_de_venta === EstadoDeVenta::Anulado) {
                return response()->json([
                    'error' => ['message' => 'La venta ya está anulada'],
                ], 400);
            }

            // Si la venta era Procesada (crédito 100% pagado), revertir
            // los cobros: marcar estado=false y decrementar el monto del
            // método de pago correspondiente (sale dinero de caja).
            $eraProcesada = $venta->estado_de_venta === EstadoDeVenta::Procesado;
            if ($eraProcesada) {
                foreach ($venta->cobrosVenta as $cobro) {
                    if ($cobro->despliegue_de_pago_id) {
                        $despliegue = DespliegueDePago::find($cobro->despliegue_de_pago_id);
                        if ($despliegue && $despliegue->metodo_de_pago_id) {
                            MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                                ->decrement('monto', (float) $cobro->monto);
                        }
                    }
                    $cobro->update([
                        'estado' => false,
                        'observacion' => ($cobro->observacion ? $cobro->observacion . ' | ' : '')
                            . 'ANULADO POR ANULACIÓN DE VENTA',
                    ]);
                }
            }

            // Verificar entregas (tabla NUEVA): si hay entregas ya entregadas,
            // no se puede anular.
            $entregas = \App\Models\Entrega::with(['detalles', 'estadoEntrega:id,codigo'])
                ->where('venta_id', $id)
                ->get();

            $codigoEstado = function ($entrega) {
                $cod = optional($entrega->estadoEntrega)->codigo;
                return $cod instanceof \BackedEnum ? $cod->value : $cod;
            };

            $entregasEntregadas = $entregas->filter(fn ($e) => $codigoEstado($e) === 'en');
            if ($entregasEntregadas->count() > 0) {
                return response()->json([
                    'error' => ['message' => 'La venta no se puede anular porque tiene entregas ya completadas. Anule primero las entregas.'],
                ], 400);
            }

            // Cancelar entregas pendientes/en camino en la tabla nueva. El stock
            // de la venta se revierte más abajo (bloque independiente).
            $estadoCanceladoId = \App\Models\EstadoEntrega::where('codigo', 'ca')->value('id');
            $entregasPendientes = $entregas->filter(fn ($e) => in_array($codigoEstado($e), ['pe', 'ec'], true));
            foreach ($entregasPendientes as $entrega) {
                foreach ($entrega->detalles as $detalle) {
                    $unidadDerivadaVenta = UnidadDerivadaInmutableVenta::find($detalle->unidad_derivada_venta_id);
                    if ($unidadDerivadaVenta) {
                        $unidadDerivadaVenta->increment('cantidad_pendiente', (float) $detalle->cantidad);
                    }
                }
                // Solo borrar detalles si la entrega nunca fue anulada manualmente.
                // Las entregas con fecha_anulacion conservan sus detalles para el kardex.
                if (! $entrega->fecha_anulacion) {
                    \App\Models\EntregaDetalle::where('entrega_id', $entrega->id)->delete();
                }
                $entrega->update(['estado_entrega_id' => $estadoCanceladoId]);
            }

            // Revertir stock si se descontó al crear la venta (flag persistido)
            if ($venta->stock_aplicado) {
                $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                foreach ($venta->productosPorAlmacen as $productoAlmacenVenta) {
                    $productoAlmacen = $productoAlmacenVenta->productoAlmacen;
                    if (! $productoAlmacen) continue;

                    // Devolver el stock a los lotes exactos que consumió esta venta
                    // (o reingresar si es venta anterior al ledger), una vez por producto.
                    $totalFr = 0;
                    foreach ($productoAlmacenVenta->unidadesDerivadas as $unidad) {
                        $totalFr += (float) $unidad->cantidad * (float) $unidad->factor;
                    }
                    $loteService->revertirConsumoOReingresar($productoAlmacen, 'venta', $venta->id, $totalFr);

                    // Revertir producto complementario (por unidad, como antes)
                    foreach ($productoAlmacenVenta->unidadesDerivadas as $unidad) {
                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $productoAlmacen->id,
                            (float) $unidad->factor,
                            (float) $unidad->cantidad,
                            $venta->almacen_id,
                            true // ingreso (revertir)
                        );
                    }
                }
            }

            // Devolver dinero
            $this->devolverDineroDeVenta($venta);

            // Update ingreso_dinero if exists
            if ($venta->ingreso_dinero_id) {
                IngresoDinero::where('id', $venta->ingreso_dinero_id)
                    ->update(['estado' => false]);
            }

            // Update venta to Anulado y limpiar flag de stock aplicado
            $venta->update([
                'estado_de_venta' => EstadoDeVenta::Anulado,
                'stock_aplicado' => false,
            ]);

            // REGISTRAR DEVOLUCIÓN EN KARDEX FACTURACIÓN
            try {
                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);
                foreach ($venta->productosPorAlmacen as $detalle) {
                    $productoAlmacen = $detalle->productoAlmacen;
                    if (!$productoAlmacen) continue;

                    foreach ($detalle->unidadesDerivadas as $ud) {
                        if (!$ud->unidadDerivadaInmutable) {
                            \Log::warning("Unidad derivada sin relación: {$ud->id}");
                            continue;
                        }
                        
                        $costo = (float) $detalle->costo;
                        // Pasar los datos como objeto con propiedades accesibles
                        $unidadObj = new \stdClass();
                        $unidadObj->unidadDerivadaInmutable = $ud->unidadDerivadaInmutable;
                        $unidadObj->cantidad = (float) $ud->cantidad;
                        $unidadObj->factor = (float) $ud->factor;
                        $unidadObj->precio = (float) $ud->precio;
                        $kardexFacturacionService->registrarDevolucionVenta($venta, $productoAlmacen, $unidadObj, $costo);
                    }
                }
            } catch (\Exception $e) {
                // No fallar la anulación si hay error al registrar en kardex
                \Log::error('Error registrando devolución en kardex facturación: ' . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
            }

            return response()->json([
                'data' => 'ok',
                'message' => 'Venta anulada exitosamente',
            ]);
        });
    }

    /**
     * Calculate total de venta
     */
    private function getTotalVenta($venta)
    {
        $total = 0;

        if ($venta instanceof Venta) {
            // Eloquent model
            foreach ($venta->productosPorAlmacen as $item) {
                foreach ($item->unidadesDerivadas as $u) {
                    $cantidad = (float) ($u->cantidad ?? 0);
                    $precio = (float) ($u->precio ?? 0);
                    $recargo = (float) ($u->recargo ?? 0);
                    $descuento = (float) ($u->descuento ?? 0);
                    $bonificacion = (bool) ($u->bonificacion ?? false);

                    if ($bonificacion) continue;

                    // precio ya es por unidad derivada (no multiplicar por factor)
                    $subtotal = $precio * $cantidad;
                    $subtotalConRecargo = $subtotal + $recargo;

                    if ($u->descuento_tipo === 'porcentaje') {
                        $montoLinea = $subtotalConRecargo - ($subtotalConRecargo * $descuento / 100);
                    } else {
                        $montoLinea = $subtotalConRecargo - $descuento;
                    }

                    $total += $montoLinea;
                }
            }

            $totalSoles = $venta->tipo_moneda === TipoMoneda::Soles
                ? $total
                : $total * (float) ($venta->tipo_de_cambio ?? 1);
        } else {
            // Array data
            foreach ($venta['productos_por_almacen'] as $item) {
                foreach ($item['unidades_derivadas'] as $u) {
                    $cantidad = (float) ($u['cantidad'] ?? 0);
                    $precio = (float) ($u['precio'] ?? 0);
                    $recargo = (float) ($u['recargo'] ?? 0);
                    $descuento = (float) ($u['descuento'] ?? 0);
                    $descuentoTipo = $u['descuento_tipo'] ?? null;
                    $bonificacion = (bool) ($u['bonificacion'] ?? false);

                    if ($bonificacion) continue;

                    // precio ya es por unidad derivada (no multiplicar por factor)
                    $subtotal = $precio * $cantidad;
                    $subtotalConRecargo = $subtotal + $recargo;

                    if ($descuentoTipo === 'porcentaje') {
                        $montoLinea = $subtotalConRecargo - ($subtotalConRecargo * $descuento / 100);
                    } else {
                        $montoLinea = $subtotalConRecargo - $descuento;
                    }

                    $total += $montoLinea;
                }
            }

            $tipoMoneda = TipoMoneda::from($venta['tipo_moneda']);
            $totalSoles = $tipoMoneda === TipoMoneda::Soles
                ? $total
                : $total * (float) ($venta['tipo_de_cambio'] ?? 1);
        }

        return $totalSoles;
    }

    /**
     * Validar nueva venta
     */
    private function validarNuevaVenta($venta)
    {
        $estadoEnum = EstadoDeVenta::from($venta['estado_de_venta']);
        $formaDePagoEnum = FormaDePago::from($venta['forma_de_pago']);

        // Validar que no exista otra venta con la misma serie y número
        if (
            isset($venta['serie']) &&
            isset($venta['numero']) &&
            ($estadoEnum === EstadoDeVenta::Creado || $estadoEnum === EstadoDeVenta::EnEspera)
        ) {
            $existingVenta = Venta::where('serie', $venta['serie'])
                ->where('numero', $venta['numero']);

            if (isset($venta['id'])) {
                $existingVenta->where('id', '!=', $venta['id']);
            }

            if ($existingVenta->exists()) {
                throw new \Exception('Ya existe una venta con la misma serie y número');
            }
        }

        // Validar pagos al contado
        if (
            $estadoEnum === EstadoDeVenta::Creado &&
            $formaDePagoEnum === FormaDePago::Contado &&
            ! isset($venta['ingreso_dinero_id']) &&
            (! isset($venta['despliegue_de_pago_ventas']) || empty($venta['despliegue_de_pago_ventas']))
        ) {
            throw new \Exception('Esta venta es al CONTADO: registra cómo ingresó el dinero seleccionando un Ingreso asociado o detallando los Métodos de Pago.');
        }

        // Validar pagos a crédito
        if (
            $estadoEnum === EstadoDeVenta::Creado &&
            $formaDePagoEnum === FormaDePago::Credito &&
            (isset($venta['ingreso_dinero_id']) || (isset($venta['despliegue_de_pago_ventas']) && ! empty($venta['despliegue_de_pago_ventas'])))
        ) {
            throw new \Exception('Esta venta es a CRÉDITO: el pago se cobra después, por eso no corresponde registrar Ingreso asociado ni Métodos de Pago al crearla. Quítalos o cambia la forma de pago a Contado.');
        }
    }

    /**
     * Proceso post venta (registrar ingresos en métodos de pago y en caja)
     */
    private function procesoPostVenta($venta)
    {
        $estadoEnum = EstadoDeVenta::from($venta['estado_de_venta']);

        if ($estadoEnum === EstadoDeVenta::Creado) {
            $ventaModel = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'despliegueDePagoVentas',
            ])->findOrFail($venta['id']);

            $totalSoles = $this->getTotalVenta($ventaModel);

            // Si hay ingreso_dinero_id, validar que el monto coincida
            if (isset($venta['ingreso_dinero_id'])) {
                $ingreso = IngresoDinero::findOrFail($venta['ingreso_dinero_id']);
                $a = round((float) $ingreso->monto, 2);
                $b = round($totalSoles, 2);

                if ($a !== $b) {
                    throw new \Exception('El monto del ingreso debe ser igual al total de la venta');
                }
            }

            // Si hay despliegue_de_pago_ventas, incrementar los métodos de pago
            if (isset($venta['despliegue_de_pago_ventas']) && ! empty($venta['despliegue_de_pago_ventas'])) {
                foreach ($venta['despliegue_de_pago_ventas'] as $desplieguePago) {
                    $despliegue = DespliegueDePago::findOrFail($desplieguePago['despliegue_de_pago_id']);

                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->increment('monto', (float) $desplieguePago['monto']);
                }

                // ✅ NUEVO: Registrar en caja
                $this->registrarVentaEnCaja($ventaModel, $venta['despliegue_de_pago_ventas']);
            }
        }
    }

    /**
     * Registrar venta en la caja del vendedor
     */
    private function registrarVentaEnCaja($venta, $desplieguesDePago)
    {
        try {
            // 1. Buscar la caja abierta del vendedor
            $apertura = AperturaCierreCaja::where('user_id', $venta->user_id)
                ->where('estado', 'abierta')
                ->first();

            // Si el vendedor no tiene apertura propia, buscar cualquier apertura activa de una caja principal
            if (!$apertura) {
                $apertura = AperturaCierreCaja::where('estado', 'abierta')
                    ->orderBy('fecha_apertura', 'desc')
                    ->first();
            }

            // 2. Procesar cada método de pago
            foreach ($desplieguesDePago as $desplieguePago) {
                $despliegue = DespliegueDePago::with('metodoDePago')->findOrFail($desplieguePago['despliegue_de_pago_id']);
                $monto = (float) $desplieguePago['monto'];

                // 3. Determinar la sub-caja a usar
                $subCaja = null;

                // PRIORIDAD 1: Si viene sub_caja_id en los datos, usarlo directamente
                if (isset($desplieguePago['sub_caja_id']) && $desplieguePago['sub_caja_id']) {
                    $subCajaTemp = SubCaja::find($desplieguePago['sub_caja_id']);
                    if ($subCajaTemp && $subCajaTemp->aceptaComprobante($venta->tipo_documento->value)) {
                        $subCaja = $subCajaTemp;
                    }
                }

                // PRIORIDAD 2: Si hay apertura, buscar en la caja principal del vendedor
                if (!$subCaja && $apertura) {
                    $subCaja = $this->buscarSubCajaParaMetodoPago(
                        $apertura->caja_principal_id,
                        $desplieguePago['despliegue_de_pago_id'],
                        $venta->tipo_documento->value
                    );

                    // Si no se encontró sub-caja específica, intentar con Caja Chica
                    if (! $subCaja) {
                        $subCaja = SubCaja::where('caja_principal_id', $apertura->caja_principal_id)
                            ->where('tipo_caja', 'CC')
                            ->whereJsonContains('tipos_comprobante', $venta->tipo_documento->value)
                            ->first();
                    }
                }

                // PRIORIDAD 3: Buscar globalmente en todas las sub-cajas
                if (!$subCaja) {
                    $subCaja = $this->buscarSubCajaGlobalParaMetodoPago(
                        $desplieguePago['despliegue_de_pago_id'],
                        $venta->tipo_documento->value
                    );
                }

                if (! $subCaja) {
                    continue;
                }

                // ✅ VALIDACIÓN CRÍTICA: Verificar que la sub-caja acepta el tipo de comprobante
                if (!$subCaja->aceptaComprobante($venta->tipo_documento->value)) {
                    continue;
                }

                // 4. Actualizar saldo de la sub-caja
                $saldoAnterior = $subCaja->saldo_actual;
                $subCaja->saldo_actual += $monto;
                $subCaja->save();

                // 5. Registrar transacción en transacciones_caja
                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $subCaja->id,
                    'tipo_transaccion' => 'ingreso',
                    'monto' => $monto,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_nuevo' => $subCaja->saldo_actual,
                    'descripcion' => "Venta {$venta->serie}-{$venta->numero}",
                    'referencia_id' => $venta->id,
                    'referencia_tipo' => 'venta',
                    'user_id' => $venta->user_id,
                    'despliegue_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                    'fecha' => now(),
                ]);

                // 6. Registrar movimiento en movimiento_caja (solo si hay apertura)
                if ($apertura) {
                    $clienteNombre = $venta->cliente->razon_social ?? $venta->cliente->nombres ?? 'Cliente';

                    MovimientoCaja::create([
                        'id' => (string) Str::ulid(),
                        'apertura_cierre_id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'sub_caja_id' => $subCaja->id,
                        'cajero_id' => $venta->user_id,
                        'fecha_hora' => now(),
                        'tipo_movimiento' => 'venta',
                        'concepto' => "Venta {$venta->serie}-{$venta->numero} - Cliente: {$clienteNombre}",
                        'saldo_inicial' => $saldoAnterior,
                        'ingreso' => $monto,
                        'salida' => 0,
                        'saldo_final' => $subCaja->saldo_actual,
                        'estado_caja' => 'abierta',
                        'tipo_comprobante' => $venta->tipo_documento->value,
                        'numero_comprobante' => "{$venta->serie}-{$venta->numero}",
                        'metodo_pago_id' => $despliegue->metodo_de_pago_id,
                        'referencia_id' => $venta->id,
                        'referencia_tipo' => 'venta',
                    ]);
                }
            }

        } catch (\Exception $e) {
            // No fallar la venta si hay error al registrar en caja
        }
    }
    /**
     * Buscar sub-caja que acepte un método de pago específico
     * Prioriza sub-cajas más específicas sobre las que aceptan "*"
     */
    private function buscarSubCajaParaMetodoPago($cajaPrincipalId, $desplieguePagoId, $tipoComprobante)
    {
        // Buscar sub-cajas que acepten este método de pago y tipo de comprobante
        $subCajas = SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('tipo_caja', 'SC')
            ->where('estado', true)
            ->get();

        $subCajasCompatibles = [];

        foreach ($subCajas as $subCaja) {
            $desplieguesIds = $subCaja->despliegues_pago_ids;
            $tiposComprobante = $subCaja->tipos_comprobante;

            // Verificar si acepta este tipo de comprobante
            $aceptaComprobante = in_array($tipoComprobante, $tiposComprobante);
            
            if (!$aceptaComprobante) {
                continue; // Si no acepta el tipo de comprobante, saltar
            }

            // Verificar si acepta este método de pago
            $aceptaTodos = in_array('*', $desplieguesIds);
            $aceptaEspecifico = in_array($desplieguePagoId, $desplieguesIds);

            if ($aceptaEspecifico) {
                // Prioridad 1: Sub-caja específica para este método
                $subCajasCompatibles[] = [
                    'subCaja' => $subCaja,
                    'prioridad' => 1,
                    'especificidad' => count($desplieguesIds)
                ];
            } elseif ($aceptaTodos) {
                // Prioridad 2: Sub-caja que acepta todos los métodos
                $subCajasCompatibles[] = [
                    'subCaja' => $subCaja,
                    'prioridad' => 2,
                    'especificidad' => 999
                ];
            }
        }

        if (empty($subCajasCompatibles)) {
            return null;
        }

        // Ordenar por prioridad (menor es mejor) y luego por especificidad (menor es más específico)
        usort($subCajasCompatibles, function ($a, $b) {
            if ($a['prioridad'] !== $b['prioridad']) {
                return $a['prioridad'] - $b['prioridad'];
            }
            return $a['especificidad'] - $b['especificidad'];
        });

        return $subCajasCompatibles[0]['subCaja'];
    }

    /**
     * Buscar sub-caja globalmente (en todas las cajas principales) que acepte un método de pago específico
     * Se usa cuando no hay apertura de caja del vendedor
     */
    private function buscarSubCajaGlobalParaMetodoPago($desplieguePagoId, $tipoComprobante)
    {
        // Buscar todas las sub-cajas activas (incluyendo Caja Chica)
        $subCajas = SubCaja::where('estado', true)->get();

        $subCajasCompatibles = [];

        foreach ($subCajas as $subCaja) {
            $desplieguesIds = $subCaja->despliegues_pago_ids;
            $tiposComprobante = $subCaja->tipos_comprobante;

            // Verificar si acepta este tipo de comprobante
            $aceptaComprobante = in_array($tipoComprobante, $tiposComprobante);
            
            if (!$aceptaComprobante) {
                continue;
            }

            // Verificar si acepta este método de pago
            $aceptaTodos = in_array('*', $desplieguesIds);
            $aceptaEspecifico = in_array($desplieguePagoId, $desplieguesIds);

            if ($aceptaEspecifico) {
                // Prioridad 1: Sub-caja específica para este método
                $subCajasCompatibles[] = [
                    'subCaja' => $subCaja,
                    'prioridad' => 1,
                    'especificidad' => count($desplieguesIds)
                ];
            } elseif ($aceptaTodos) {
                // Prioridad 2: Sub-caja que acepta todos los métodos
                $subCajasCompatibles[] = [
                    'subCaja' => $subCaja,
                    'prioridad' => 2,
                    'especificidad' => 999
                ];
            }
        }

        if (empty($subCajasCompatibles)) {
            return null;
        }

        // Ordenar por prioridad (menor es mejor) y luego por especificidad (menor es más específico)
        usort($subCajasCompatibles, function ($a, $b) {
            if ($a['prioridad'] !== $b['prioridad']) {
                return $a['prioridad'] - $b['prioridad'];
            }
            return $a['especificidad'] - $b['especificidad'];
        });

        return $subCajasCompatibles[0]['subCaja'];
    }

    /**
     * Devolver dinero de venta (revertir métodos de pago)
     */
    private function devolverDineroDeVenta($venta)
    {
        if ($venta->estado_de_venta === EstadoDeVenta::Creado) {
            // Si hay despliegue_de_pago_ventas, decrementar los métodos de pago
            if ($venta->despliegueDePagoVentas && $venta->despliegueDePagoVentas->count() > 0) {
                foreach ($venta->despliegueDePagoVentas as $desplieguePagoVenta) {
                    $despliegue = DespliegueDePago::findOrFail($desplieguePagoVenta->despliegue_de_pago_id);

                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->decrement('monto', (float) $desplieguePagoVenta->monto);
                }
            }

            // Si hay ingreso_dinero_id, revertir
            if ($venta->ingreso_dinero_id) {
                $ingreso = IngresoDinero::findOrFail($venta->ingreso_dinero_id);
                $despliegue = DespliegueDePago::findOrFail($ingreso->despliegue_de_pago_id);

                MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                    ->decrement('monto', (float) $ingreso->monto);
            }
        }
    }

    // ventasPorCobrar() movido al final del archivo (usa cobrosVenta)


    /**
     * Historial de ediciones de una venta específica
     */
    public function getHistorial(string $id)
    {
        $venta = Venta::findOrFail($id);

        $historial = $venta->historial()
            ->with('usuario:id,name')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json(['data' => $historial]);
    }

    /**
     * Historial general de ediciones de todas las ventas
     */
    public function historialGeneral(Request $request)
    {
        $request->validate([
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'user_id' => 'sometimes|string',
            'accion' => 'sometimes|string',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = VentaHistorial::query()
            ->with([
                'usuario:id,name',
                'venta:id,tipo_documento,serie,numero,cliente_id,fecha',
                'venta.cliente:id,numero_documento,razon_social,nombres,apellidos',
            ])
            ->orderBy('fecha', 'desc');

        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('venta', function ($q) use ($search) {
                $q->where('serie', 'LIKE', "%{$search}%")
                    ->orWhere('numero', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 50);
        $historial = $query->paginate($perPage);

        return response()->json([
            'data' => $historial->items(),
            'total' => $historial->total(),
            'current_page' => $historial->currentPage(),
            'per_page' => $historial->perPage(),
            'last_page' => $historial->lastPage(),
        ]);
    }

    /**
     * Preparar detalles de venta para el servicio de vales
     */
    private function prepararDetallesVentaParaVales($venta): array
    {
        $detalles = [];

        // Batch-load PAUDs for price type detection
        $allPaudIds = [];
        foreach ($venta['productos_por_almacen'] ?? [] as $producto) {
            foreach ($producto['unidades_derivadas'] ?? [] as $unidad) {
                if (!empty($unidad['unidad_derivada_id'])) {
                    $allPaudIds[] = (int) $unidad['unidad_derivada_id'];
                }
            }
        }
        $pauds = \App\Models\ProductoAlmacenUnidadDerivada::whereIn('id', array_unique($allPaudIds))
            ->get()
            ->keyBy('id');

        foreach ($venta['productos_por_almacen'] ?? [] as $producto) {
            $cantidadTotal = 0;
            $precioTotal = 0;

            // Detectar el tipo de precio usado en la venta
            $tiposPrecioUsados = [];

            foreach ($producto['unidades_derivadas'] ?? [] as $unidad) {
                $cantidad = (float) ($unidad['cantidad'] ?? 0);
                $factor = (float) ($unidad['factor'] ?? 1);
                $precio = (float) ($unidad['precio'] ?? 0);
                $cantidadTotal += $cantidad * $factor;
                $precioTotal += $cantidad * $precio;

                // Determinar tipo de precio comparando contra los 4 precios del PAUD
                $paudId = isset($unidad['unidad_derivada_id']) ? (int) $unidad['unidad_derivada_id'] : null;
                if ($paudId && isset($pauds[$paudId])) {
                    $paud = $pauds[$paudId];
                    $tipo = $this->detectarTipoPrecio($precio, $paud);
                    if ($tipo) {
                        $tiposPrecioUsados[$tipo] = true;
                    }
                }
            }

            $productoId = null;
            $categoriaId = null;
            $marcaId = null;

            $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
            if ($productoAlmacenId) {
                $productoAlmacen = ProductoAlmacen::with('producto.categoria')
                    ->find($productoAlmacenId);
                if ($productoAlmacen && $productoAlmacen->producto) {
                    $productoId = $productoAlmacen->producto->id;
                    $categoriaId = $productoAlmacen->producto->categoria_id ?? null;
                    $marcaId = $productoAlmacen->producto->marca_id ?? null;
                }
            }

            if (!$productoId && isset($producto['producto_id'])) {
                $productoId = $producto['producto_id'];
                $productoModel = \App\Models\Producto::find($productoId);
                $categoriaId = $productoModel?->categoria_id ?? null;
                $marcaId = $productoModel?->marca_id ?? null;
            }

            if ($productoId) {
                $detalles[] = [
                    'producto_id' => $productoId,
                    'categoria_id' => $categoriaId,
                    'marca_id' => $marcaId,
                    'cantidad' => $cantidadTotal,
                    'precio_total' => $precioTotal,
                    'tipo_precio' => !empty($tiposPrecioUsados) ? array_keys($tiposPrecioUsados) : [],
                ];
            }
        }

        return $detalles;
    }

    /**
     * Determinar qué tipo de precio (publico/especial/minimo/ultimo) corresponde
     * al precio usado en la venta, comparándolo contra los 4 precios del PAUD.
     */
    private function detectarTipoPrecio(float $precio, \App\Models\ProductoAlmacenUnidadDerivada $paud): ?string
    {
        $map = [
            'publico'  => (float) $paud->precio_publico,
            'especial' => (float) ($paud->precio_especial ?? 0),
            'minimo'   => (float) ($paud->precio_minimo ?? 0),
            'ultimo'   => (float) ($paud->precio_ultimo ?? 0),
        ];

        // Buscar coincidencia exacta (tolerancia 0.01 por redondeo)
        foreach ($map as $tipo => $valor) {
            if ($valor > 0 && abs($precio - $valor) < 0.01) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * Listar ventas a crédito con saldo pendiente (para módulo "Ventas por Cobrar")
     */
    public function ventasPorCobrar(Request $request)
    {
        $request->validate([
            'almacen_id'     => 'sometimes|integer',
            'cliente_id'     => 'sometimes|integer',
            'user_id'        => 'sometimes|string',
            'desde'          => 'sometimes|date',
            'hasta'          => 'sometimes|date',
            'search'         => 'sometimes|string',
            'tipo_documento' => 'sometimes|string',
            'dias'           => 'sometimes|integer|min:1',
            'estado_pago'    => 'sometimes|in:pendientes,pagadas,todas',
            'per_page'       => 'sometimes|integer|min:-1|max:200',
            'page'           => 'sometimes|integer|min:1',
        ]);

        $estadoPago = $request->input('estado_pago', 'pendientes');

        $query = Venta::query()
            ->with([
                'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
                'almacen:id,name',
            ])
            ->withSum([
                'cobrosVenta as total_cobrado' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'monto')
            ->withMax([
                'cobrosVenta as ultimo_pago' => function ($query) {
                    $query->where('estado', true);
                }
            ], 'fecha')
            ->where('forma_de_pago', FormaDePago::Credito)
            // Incluir Creado y Procesado en todos los casos; quién es "pendiente" o
            // "pagada" lo decide el saldo real más abajo, no el estado. Así una venta
            // que quedó marcada Procesado pero aún debe centavos sigue saliendo como
            // pendiente.
            ->whereIn('estado_de_venta', [EstadoDeVenta::Creado, EstadoDeVenta::Procesado]);

        // Filtros opcionales
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('desde') || $request->has('hasta')) {
            // Filtrar siempre por la fecha del último cobro. Si no tiene cobros, usar la fecha de la venta.
            if ($request->has('desde')) {
                $query->whereRaw(
                    'COALESCE((SELECT MAX(cv.fecha) FROM cobroventa cv WHERE cv.venta_id = venta.id AND cv.estado = 1), venta.fecha) >= ?',
                    [$request->desde]
                );
            }
            if ($request->has('hasta')) {
                $query->whereRaw(
                    'COALESCE((SELECT MAX(cv.fecha) FROM cobroventa cv WHERE cv.venta_id = venta.id AND cv.estado = 1), venta.fecha) <= ?',
                    [$request->hasta . ' 23:59:59']
                );
            }
        }

        // Filtro por días a vencer (ej: ventas que vencen en 15 días desde hoy)
        if ($request->has('dias')) {
            $fechaLimite = now()->addDays((int) $request->dias)->toDateString();
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', $fechaLimite);
        }

        // Filtro por tipo de documento
        if ($request->has('tipo_documento') && !empty($request->tipo_documento)) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        // Búsqueda por serie, número o cliente
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'LIKE', "%{$search}%")
                    ->orWhere('numero', 'LIKE', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('razon_social', 'LIKE', "%{$search}%")
                            ->orWhere('nombres', 'LIKE', "%{$search}%")
                            ->orWhere('apellidos', 'LIKE', "%{$search}%")
                            ->orWhere('numero_documento', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->input('per_page', 50);

        if ($perPage === -1) {
            $ventas = $query->orderBy('id', 'desc')->limit(200)->get();

            // Filtrar según el estado de pago
            $ventasFiltradas = $ventas->filter(function ($venta) use ($estadoPago) {
                $total        = $this->getTotalVenta($venta);
                $totalCobrado = (float) ($venta->total_cobrado ?? 0);
                $saldo        = $total - $totalCobrado;

                if ($estadoPago === 'pendientes') {
                    return round($saldo, 2) > 0; // Cualquier saldo pendiente (aunque sea 1 centavo)
                } elseif ($estadoPago === 'pagadas') {
                    return round($saldo, 2) <= 0; // Solo pagadas completamente
                } else {
                    return true; // Todas
                }
            });

            return response()->json([
                'data'  => $ventasFiltradas->values(),
                'total' => $ventasFiltradas->count(),
            ]);
        }

        $ventas = $query->orderBy('id', 'desc')->paginate($perPage);

        // Filtrar según el estado de pago
        $ventasFiltradas = $ventas->getCollection()->filter(function ($venta) use ($estadoPago) {
            $total        = $this->getTotalVenta($venta);
            $totalCobrado = (float) ($venta->total_cobrado ?? 0);
            $saldo        = $total - $totalCobrado;

            if ($estadoPago === 'pendientes') {
                return round($saldo, 2) > 0; // Cualquier saldo pendiente (aunque sea 1 centavo)
            } elseif ($estadoPago === 'pagadas') {
                return round($saldo, 2) <= 0; // Solo pagadas completamente
            } else {
                return true; // Todas
            }
        });

        $ventas->setCollection($ventasFiltradas);

        return response()->json([
            'data'         => $ventas->items(),
            'total'        => $ventasFiltradas->count(),
            'current_page' => $ventas->currentPage(),
            'per_page'     => $ventas->perPage(),
            'last_page'    => $ventas->lastPage(),
        ]);
    }

    /**
     * Listar cobros de una venta específica
     */
    public function getCobros(string $id)
    {
        $venta = Venta::findOrFail($id);

        $cobros = $venta->cobrosVenta()
            ->with('despliegueDePago.metodoDePago', 'user:id,name')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json(['data' => $cobros]);
    }

    /**
     * Listar TODOS los cobros con filtros (para el modal de cobros realizados)
     */
    public function getAllCobros(Request $request)
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'cliente_id' => 'sometimes|integer',
            'estado' => 'sometimes|in:todos,activos,anulados',
            'per_page' => 'sometimes|integer|min:-1|max:100', // -1 = todos los registros
        ]);

        $query = \App\Models\CobroVenta::query()
            ->with([
                'venta.cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                'venta:id,tipo_documento,serie,numero,fecha,fecha_vencimiento,almacen_id,cliente_id',
                'despliegueDePago.metodoDePago',
                'user:id,name'
            ]);

        // Filtrar por estado del cobro
        $estadoFiltro = $request->input('estado', 'activos');
        if ($estadoFiltro === 'activos') {
            $query->where('estado', true);
        } elseif ($estadoFiltro === 'anulados') {
            $query->where('estado', false);
        }
        // Si es 'todos', no filtrar por estado

        // Filtrar por almacén (a través de la venta)
        if ($request->has('almacen_id')) {
            $query->whereHas('venta', function ($q) use ($request) {
                $q->where('almacen_id', $request->almacen_id);
            });
        }

        // Filtrar por rango de fechas del cobro
        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Filtrar por cliente
        if ($request->has('cliente_id')) {
            $query->whereHas('venta', function ($q) use ($request) {
                $q->where('cliente_id', $request->cliente_id);
            });
        }

        $query->orderBy('fecha', 'desc');

        $perPage = (int) $request->input('per_page', 50);

        // Si per_page es -1, devolver todos los registros sin paginación
        if ($perPage === -1) {
            return response()->json([
                'data' => $query->get(),
                'total' => $query->count(),
            ]);
        }

        $cobros = $query->paginate($perPage);

        return response()->json([
            'data' => $cobros->items(),
            'total' => $cobros->total(),
            'current_page' => $cobros->currentPage(),
            'per_page' => $cobros->perPage(),
            'last_page' => $cobros->lastPage(),
        ]);
    }

    /**
     * Registrar un cobro para una venta a crédito
     */
    public function storeCobro(Request $request, string $id)
    {
        $validated = $request->validate([
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'monto'                 => 'required|numeric|min:0.01',
            'fecha'                 => 'required|date',
            'observacion'           => 'nullable|string|max:500',
            'numero_letra'          => 'nullable|string|max:100',
            'numero_operacion'      => 'nullable|string|max:100',
            'user_id'               => 'required|string|exists:user,id',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $venta = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'cobrosVenta' => function ($query) {
                    $query->where('estado', true);
                },
            ])->findOrFail($id);

            // Validar que la venta es a crédito
            if ($venta->forma_de_pago !== FormaDePago::Credito) {
                return response()->json([
                    'error' => ['message' => 'Solo se pueden registrar cobros en ventas a crédito'],
                ], 422);
            }

            // Validar que la venta no está anulada
            if ($venta->estado_de_venta === EstadoDeVenta::Anulado) {
                return response()->json([
                    'error' => ['message' => 'No se puede registrar un cobro en una venta anulada'],
                ], 422);
            }

            // Calcular total de la venta
            $totalVenta = $this->getTotalVenta($venta);

            // Calcular total cobrado hasta ahora
            $totalCobrado = $venta->cobrosVenta->sum('monto');

            // Calcular saldo pendiente
            $saldoPendiente = $totalVenta - $totalCobrado;

            // Validar que el monto no exceda el saldo
            if ($validated['monto'] > $saldoPendiente + 0.01) {
                return response()->json([
                    'error' => ['message' => 'El monto del cobro (S/ ' . number_format($validated['monto'], 2) . ') no puede exceder el saldo pendiente de S/ ' . number_format($saldoPendiente, 2)],
                ], 422);
            }

            // Crear el cobro
            $cobro = $venta->cobrosVenta()->create([
                'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'],
                'monto'                 => $validated['monto'],
                'fecha'                 => \Carbon\Carbon::parse($validated['fecha'])->format('Y-m-d H:i:s'),
                'observacion'           => $validated['observacion'] ?? null,
                'numero_letra'          => $validated['numero_letra'] ?? null,
                'numero_operacion'      => $validated['numero_operacion'] ?? null,
                'estado'                => true,
                'user_id'               => $validated['user_id'],
            ]);

            // Actualizar estado de la venta solo si quedó completamente pagada.
            // Se compara redondeando a 2 decimales para absorber el ruido de punto
            // flotante (pagar el total exacto pero que el float dé 19.9999998) sin
            // perdonar un centavo real sin cobrar (pagar 19.99 de 20.00).
            $nuevoTotalCobrado = $totalCobrado + $validated['monto'];
            if (round($nuevoTotalCobrado, 2) >= round($totalVenta, 2)) {
                $venta->update(['estado_de_venta' => EstadoDeVenta::Procesado]);
            }

            // Cargar relaciones para la respuesta
            $cobro->load('despliegueDePago.metodoDePago', 'user:id,name');

            return response()->json([
                'data'    => $cobro,
                'message' => 'Cobro registrado correctamente',
                'saldo_pendiente' => max(0, $saldoPendiente - $validated['monto']),
            ], 201);
        });
    }

    /**
     * Cobro múltiple: distribuir un pago en varias ventas de un cliente
     */
    public function storeCobroMultiple(Request $request)
    {
        $validated = $request->validate([
            'cliente_id'            => 'required|integer|exists:cliente,id',
            'despliegue_de_pago_id' => 'nullable|string|exists:desplieguedepago,id',
            'monto_total'           => 'required|numeric|min:0.01',
            'fecha'                 => 'required|date',
            'observacion'           => 'nullable|string|max:500',
            'numero_operacion'      => 'nullable|string|max:100',
            'user_id'               => 'required|string|exists:user,id',
            'distribucion'          => 'required|array|min:1',
            'distribucion.*.venta_id'              => 'required|string',
            'distribucion.*.monto'                 => 'required|numeric|min:0.01',
            'distribucion.*.despliegue_de_pago_id' => 'nullable|string|exists:desplieguedepago,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $cobrosCreados = [];
            $ventasActualizadas = [];

            foreach ($validated['distribucion'] as $item) {
                $venta = Venta::with([
                    'productosPorAlmacen.unidadesDerivadas',
                    'cobrosVenta' => fn($q) => $q->where('estado', true),
                ])->findOrFail($item['venta_id']);

                // Validar que la venta pertenece al cliente
                if ((int) $venta->cliente_id !== (int) $validated['cliente_id']) {
                    throw new \Exception("La venta {$venta->serie}-{$venta->numero} no pertenece al cliente seleccionado");
                }

                // Validar que la venta es a crédito y no está anulada
                if ($venta->forma_de_pago !== FormaDePago::Credito) {
                    throw new \Exception("La venta {$venta->serie}-{$venta->numero} no es a crédito");
                }
                if ($venta->estado_de_venta === EstadoDeVenta::Anulado) {
                    throw new \Exception("La venta {$venta->serie}-{$venta->numero} está anulada");
                }

                $totalVenta = $this->getTotalVenta($venta);
                $totalCobrado = $venta->cobrosVenta->sum('monto');
                $saldoPendiente = $totalVenta - $totalCobrado;

                if ($item['monto'] > $saldoPendiente + 0.01) {
                    throw new \Exception("El monto S/ " . number_format($item['monto'], 2) . " excede el saldo pendiente S/ " . number_format($saldoPendiente, 2) . " de la venta {$venta->serie}-{$venta->numero}");
                }

                $desplieguePagoId = $item['despliegue_de_pago_id'] ?? $validated['despliegue_de_pago_id'] ?? null;
                if (!$desplieguePagoId) {
                    throw new \Exception("Debe seleccionar un modo de pago para la venta {$venta->serie}-{$venta->numero}");
                }

                // Crear el cobro
                $cobro = $venta->cobrosVenta()->create([
                    'despliegue_de_pago_id' => $desplieguePagoId,
                    'monto'                 => $item['monto'],
                    'fecha'                 => \Carbon\Carbon::parse($validated['fecha'])->format('Y-m-d H:i:s'),
                    'observacion'           => $validated['observacion'] ?? null,
                    'numero_operacion'      => $validated['numero_operacion'] ?? null,
                    'estado'                => true,
                    'user_id'               => $validated['user_id'],
                ]);

                // Actualizar estado solo si quedó completamente pagada (redondeo a
                // 2 decimales: absorbe ruido de float, no perdona centavos reales).
                $nuevoTotalCobrado = $totalCobrado + $item['monto'];
                if (round($nuevoTotalCobrado, 2) >= round($totalVenta, 2)) {
                    $venta->update(['estado_de_venta' => EstadoDeVenta::Procesado]);
                }

                $cobrosCreados[] = $cobro;
                $ventasActualizadas[] = [
                    'venta_id' => $venta->id,
                    'serie'    => $venta->serie,
                    'numero'   => $venta->numero,
                    'monto_cobrado' => $item['monto'],
                    'saldo_pendiente' => max(0, $saldoPendiente - $item['monto']),
                ];
            }

            return response()->json([
                'data'    => $ventasActualizadas,
                'message' => 'Cobro múltiple registrado correctamente (' . count($cobrosCreados) . ' ventas)',
                'total_cobrado' => array_sum(array_column($validated['distribucion'], 'monto')),
                'cobros_ids' => collect($cobrosCreados)->pluck('id'),
            ], 201);
        });
    }

    /**
     * Anular un cobro de venta
     */
    public function anularCobro(Request $request, string $ventaId, string $cobroId)
    {
        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($ventaId, $cobroId, $validated) {
            $venta = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'cobrosVenta' => fn($q) => $q->where('estado', true),
            ])->findOrFail($ventaId);

            $cobro = \App\Models\CobroVenta::where('id', $cobroId)
                ->where('venta_id', $ventaId)
                ->firstOrFail();

            // Validar que el cobro no esté ya anulado
            if (!$cobro->estado) {
                return response()->json([
                    'error' => ['message' => 'El cobro ya está anulado'],
                ], 422);
            }

            // Anular el cobro (cambiar estado a false)
            $cobro->update([
                'estado' => false,
                'fecha_anulacion' => now()->toDateString(),
                'observacion' => ($cobro->observacion ? $cobro->observacion . ' | ' : '') . 
                                 'ANULADO: ' . ($validated['motivo'] ?? 'Sin motivo especificado'),
            ]);

            // Recalcular el total cobrado (solo cobros activos)
            $totalVenta = $this->getTotalVenta($venta);
            $totalCobradoActivo = $venta->cobrosVenta()->where('estado', true)->sum('monto');
            $saldoPendiente = $totalVenta - $totalCobradoActivo;

            // Si había quedado como Procesado pero ahora tiene saldo pendiente, reabrirla.
            // (El enum no tiene "Pendiente"; el estado abierto es Creado.)
            if ($venta->estado_de_venta === EstadoDeVenta::Procesado && round($saldoPendiente, 2) > 0) {
                $venta->update(['estado_de_venta' => EstadoDeVenta::Creado]);
            }

            return response()->json([
                'data'    => $cobro->fresh(),
                'message' => 'Cobro anulado correctamente',
                'saldo_pendiente' => $saldoPendiente,
            ], 200);
        });
    }
}
