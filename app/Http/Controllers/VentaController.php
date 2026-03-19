<?php

namespace App\Http\Controllers;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Enums\EstadoDeVenta;
use App\Enums\FormaDePago;
use App\Enums\TipoDocumento;
use App\Enums\TipoMoneda;
use App\Models\AperturaCierreCaja;
use App\Models\DetalleEntregaProducto;
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
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\ValeCompraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VentaController extends Controller
{
    public function __construct(
        private FacturaServiceInterface $facturaService,
        private ValeCompraService $valeCompraService
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
            ])
            ->withCount('entregasProductos as entregas_productos_count')
            ->withSum('despliegueDePagoVentas as total_pagado', 'monto');

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

        // Filter by entrega status (pendiente = tiene cantidad_pendiente > 0, completa = todo entregado)
        if ($request->has('entrega')) {
            if ($request->entrega === 'pendiente') {
                $query->whereHas('productosPorAlmacen.unidadesDerivadas', function ($q) {
                    $q->where('cantidad_pendiente', '>', 0);
                });
            } elseif ($request->entrega === 'completa') {
                $query->whereDoesntHave('productosPorAlmacen.unidadesDerivadas', function ($q) {
                    $q->where('cantidad_pendiente', '>', 0);
                });
            }
        }

        $perPage = $request->input('per_page', 50);

        if ($perPage === -1) {
            // Return all without pagination
            return response()->json([
                'data' => $query->orderBy('fecha', 'desc')->limit(100)->get(),
                'total' => $query->count(),
            ]);
        }

        $ventas = $query->orderBy('fecha', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $ventas->items(),
            'total' => $ventas->total(),
            'current_page' => $ventas->currentPage(),
            'per_page' => $ventas->perPage(),
            'last_page' => $ventas->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
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

            // Generar serie y número automáticamente si no se proporcionan
            if (empty($validated['serie']) || empty($validated['numero'])) {
                $serieDoc = \App\Models\SerieDocumento::where('tipo_documento', $validated['tipo_documento'])
                    ->where('almacen_id', $validated['almacen_id'])
                    ->where('activo', true)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (! $serieDoc) {
                    throw new \Exception("No se encontró una serie activa para el tipo de documento {$validated['tipo_documento']} en el almacén {$validated['almacen_id']}");
                }

                // Incrementar el correlativo
                $nuevoCorrelativo = $serieDoc->correlativo + 1;
                $serieDoc->update(['correlativo' => $nuevoCorrelativo]);

                $validated['serie'] = $serieDoc->serie;
                $validated['numero'] = $nuevoCorrelativo;
            }

            // ✅ VALIDACIÓN CRÍTICA: Tipo de documento vs tipo de cliente
            $cliente = \App\Models\Cliente::find($validated['cliente_id']);
            if (!$cliente) {
                throw new \Exception("Cliente no encontrado");
            }

            // Validar que Facturas (01) solo se emitan a clientes con RUC
            if ($validated['tipo_documento'] === '01' && $cliente->tipo_documento !== 'ruc') {
                return response()->json([
                    'message' => 'Las Facturas (01) solo pueden emitirse a clientes con RUC. Para clientes con DNI debe emitir una Boleta (03).',
                    'error' => 'TIPO_DOCUMENTO_INVALIDO',
                    'cliente_tipo_documento' => $cliente->tipo_documento,
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
                'serie' => $validated['serie'],
                'numero' => $validated['numero'],
                'descripcion' => $validated['descripcion'] ?? null,
                'forma_de_pago' => $formaDePagoEnum,
                'numero_dias' => $validated['numero_dias'] ?? null,
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'tipo_moneda' => $tipoMonedaEnum,
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? 1,
                'fecha' => $validated['fecha'],
                'estado_de_venta' => $estadoEnum,
                'cliente_id' => $validated['cliente_id'],
                'direccion_seleccionada' => $validated['direccion_seleccionada'] ?? null, // Guardar dirección seleccionada
                'recomendado_por_id' => $validated['recomendado_por_id'] ?? null,
                'user_id' => $validated['user_id'],
                'almacen_id' => $validated['almacen_id'],
            ]);

            // Create productos_por_almacen and unidades_derivadas
            foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
                // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                $productoAlmacenId = $producto['producto_almacen_id'] ?? null;

                if (! $productoAlmacenId && isset($producto['producto_id'])) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                        ->where('almacen_id', $validated['almacen_id'])
                        ->first();

                    if (! $productoAlmacen) {
                        throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$validated['almacen_id']}");
                    }

                    $productoAlmacenId = $productoAlmacen->id;
                }

                $productoAlmacenVenta = ProductoAlmacenVenta::create([
                    'venta_id' => $venta->id,
                    'costo' => $producto['costo'],
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

            // Create despliegue_de_pago_ventas if provided
            if (isset($validated['despliegue_de_pago_ventas'])) {
                foreach ($validated['despliegue_de_pago_ventas'] as $desplieguePago) {
                    // Log para debug
                    \Log::info('Procesando método de pago:', [
                        'despliegue_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                        'monto' => $desplieguePago['monto'],
                        'numero_operacion' => $desplieguePago['numero_operacion'] ?? 'NO ENVIADO',
                        'isset' => isset($desplieguePago['numero_operacion']),
                        'empty' => empty($desplieguePago['numero_operacion']),
                    ]);

                    // Obtener el método de pago para calcular sobrecargo
                    $metodoPago = DespliegueDePago::find($desplieguePago['despliegue_de_pago_id']);
                    
                    if (!$metodoPago) {
                        throw new \Exception("Método de pago no encontrado: {$desplieguePago['despliegue_de_pago_id']}");
                    }

                    \Log::info('Método de pago info:', [
                        'name' => $metodoPago->name,
                        'requiere_numero_serie' => $metodoPago->requiere_numero_serie,
                    ]);

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
                $valesAplicados = $this->valeCompraService->aplicarValesAutomaticos($venta, $detallesVenta);

                if ($valesAplicados->isNotEmpty()) {
                    Log::info('Vales aplicados automáticamente', [
                        'venta_id' => $venta->id,
                        'vales_count' => $valesAplicados->count(),
                        'vales' => $valesAplicados->pluck('vale_compra_id'),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error al aplicar vales automáticos', [
                    'venta_id' => $venta->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // CANJEAR VALE GENERADO (código de próxima compra)
            if (!empty($validated['codigo_vale'])) {
                try {
                    $canjeado = $this->valeCompraService->aplicarValeGenerado($validated['codigo_vale'], $venta);
                    if ($canjeado) {
                        Log::info('Vale generado canjeado en la venta', [
                            'venta_id' => $venta->id,
                            'codigo_vale' => $validated['codigo_vale'],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error al canjear vale generado', [
                        'venta_id' => $venta->id,
                        'codigo_vale' => $validated['codigo_vale'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // GENERAR COMPROBANTE ELECTRÓNICO AUTOMÁTICAMENTE
            // Solo para facturas (01) y boletas (03)
            $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum 
                ? $venta->tipo_documento->value 
                : $venta->tipo_documento;

            Log::info('🔍 Intentando generar comprobante automático', [
                'venta_id' => $venta->id,
                'tipo_documento' => $tipoDocumento,
                'user_id' => $validated['user_id'],
            ]);

            if (in_array($tipoDocumento, ['01', '03'])) {
                try {
                    $dto = new FacturaDTO(
                        ventaId: $venta->id,
                        usuarioId: $validated['user_id']
                    );

                    Log::info('🔍 DTO creado, llamando a facturaService', [
                        'dto_venta_id' => $dto->ventaId,
                        'dto_usuario_id' => $dto->usuarioId,
                    ]);

                    $resultado = $this->facturaService->generarComprobanteDesdeVenta($dto);
                    
                    Log::info('✅ Comprobante electrónico generado automáticamente', [
                        'venta_id' => $venta->id,
                        'tipo_documento' => $tipoDocumento,
                        'success' => $resultado['success'] ?? false,
                        'comprobante_id' => $resultado['comprobante']->id ?? 'NO_ID',
                    ]);
                } catch (\Exception $e) {
                    // No fallar la venta si hay error al generar comprobante
                    Log::error('❌ Error al generar comprobante automáticamente', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            } else {
                Log::info('⏭️ Tipo de documento no requiere comprobante electrónico', [
                    'tipo_documento' => $tipoDocumento,
                ]);
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
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.detallesEntrega',
            'despliegueDePagoVentas.despliegueDePago.metodoDePago',
            'serviciosVenta.servicio',
            'user:id,name',
            'almacen:id,name',
            'entregasProductos',
            'valesAplicados.valeCompra',
            'comprobanteElectronico:id,venta_id,tipo_comprobante,serie,correlativo,fecha_emision,estado_sunat,xml_path,xml_firmado,cdr_path,pdf_path,moneda,operacion_gravada,total_igv,importe_total',
        ])
            ->withCount('entregasProductos as entregas_productos_count')
            ->withSum('despliegueDePagoVentas as total_pagado', 'monto')
            ->findOrFail($id);

        return response()->json(['data' => $venta]);
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
            ])->findOrFail($id);

            // Add id to validated data for validation
            $validated['id'] = $id;

            // ✅ VALIDACIÓN CRÍTICA: Tipo de documento vs tipo de cliente (si se está cambiando)
            if (isset($validated['cliente_id']) || isset($validated['tipo_documento'])) {
                $clienteId = $validated['cliente_id'] ?? $venta->cliente_id;
                $tipoDocumento = $validated['tipo_documento'] ?? ($venta->tipo_documento instanceof \BackedEnum ? $venta->tipo_documento->value : $venta->tipo_documento);
                
                $cliente = \App\Models\Cliente::find($clienteId);
                if (!$cliente) {
                    throw new \Exception("Cliente no encontrado");
                }

                // Validar que Facturas (01) solo se emitan a clientes con RUC
                if ($tipoDocumento === '01' && $cliente->tipo_documento !== 'ruc') {
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
                } elseif ($key !== 'productos_por_almacen' && $key !== 'despliegue_de_pago_ventas' && $key !== 'servicios_venta' && $key !== 'id') {
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

            // Update venta
            $venta->update($updateData);

            // If productos_por_almacen is provided, update them
            if (isset($validated['productos_por_almacen'])) {
                // Eliminar registros hijos en orden correcto para evitar FK constraint
                $productoAlmacenVentaIds = ProductoAlmacenVenta::where('venta_id', $id)->pluck('id');
                $unidadDerivadaVentaIds = UnidadDerivadaInmutableVenta::whereIn('producto_almacen_venta_id', $productoAlmacenVentaIds)->pluck('id');
                DetalleEntregaProducto::whereIn('unidad_derivada_venta_id', $unidadDerivadaVentaIds)->delete();

                // Delete existing productos_por_almacen (cascades to unidadderivadainmutableventa)
                ProductoAlmacenVenta::where('venta_id', $id)->delete();

                // Create new productos_por_almacen
                foreach ($validated['productos_por_almacen'] as $producto) {
                    // Get producto_almacen_id (either provided or find by producto_id + almacen_id)
                    $productoAlmacenId = $producto['producto_almacen_id'] ?? null;

                    if (! $productoAlmacenId && isset($producto['producto_id'])) {
                        $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $venta->almacen_id)
                            ->first();

                        if (! $productoAlmacen) {
                            throw new \Exception("Producto {$producto['producto_id']} no encontrado en almacén {$venta->almacen_id}");
                        }

                        $productoAlmacenId = $productoAlmacen->id;
                    }

                    $productoAlmacenVenta = ProductoAlmacenVenta::create([
                        'venta_id' => $venta->id,
                        'costo' => $producto['costo'],
                        'producto_almacen_id' => $productoAlmacenId,
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

            // ✅ REGENERAR COMPROBANTE ELECTRÓNICO AUTOMÁTICAMENTE AL EDITAR
            // Solo para facturas (01) y boletas (03) que ya tienen comprobante
            $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum 
                ? $venta->tipo_documento->value 
                : $venta->tipo_documento;

            if (in_array($tipoDocumento, ['01', '03'])) {
                try {
                    // Verificar si ya existe un comprobante para esta venta
                    $comprobanteExistente = \App\Models\ComprobanteElectronico::where('venta_id', $venta->id)->first();
                    
                    if ($comprobanteExistente) {
                        Log::info('🔄 Regenerando comprobante electrónico automáticamente', [
                            'venta_id' => $venta->id,
                            'comprobante_id' => $comprobanteExistente->id,
                        ]);

                        // Eliminar el comprobante existente y sus detalles
                        \App\Models\DetalleComprobanteElectronico::where('comprobante_electronico_id', $comprobanteExistente->id)->delete();
                        $comprobanteExistente->delete();

                        Log::info('🗑️ Comprobante anterior eliminado', [
                            'venta_id' => $venta->id,
                            'comprobante_id' => $comprobanteExistente->id,
                        ]);

                        // Generar nuevo comprobante
                        $dto = new FacturaDTO(
                            ventaId: $venta->id,
                            usuarioId: $venta->user_id
                        );

                        $resultado = $this->facturaService->generarComprobanteDesdeVenta($dto);
                        
                        Log::info('✅ Comprobante electrónico regenerado automáticamente', [
                            'venta_id' => $venta->id,
                            'comprobante_id' => $resultado['comprobante']->id ?? 'NO_ID',
                        ]);
                    }
                } catch (\Exception $e) {
                    // No fallar la actualización si hay error al regenerar comprobante
                    Log::error('❌ Error al regenerar comprobante automáticamente', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage(),
                    ]);
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
                'despliegueDePagoVentas',
            ])
                ->withCount('entregasProductos as entregas_productos_count')
                ->findOrFail($id);

            if (
                $venta->estado_de_venta === EstadoDeVenta::Procesado ||
                $venta->estado_de_venta === EstadoDeVenta::Anulado
            ) {
                return response()->json([
                    'error' => ['message' => 'La venta no se puede anular'],
                ], 400);
            }

            if ($venta->entregas_productos_count > 0) {
                return response()->json([
                    'error' => ['message' => 'La venta no se puede anular porque tiene Entregas de Productos activas'],
                ], 400);
            }

            // Devolver dinero
            $this->devolverDineroDeVenta($venta);

            // Update ingreso_dinero if exists
            if ($venta->ingreso_dinero_id) {
                IngresoDinero::where('id', $venta->ingreso_dinero_id)
                    ->update(['estado' => false]);
            }

            // Update venta to Anulado
            $venta->update([
                'estado_de_venta' => EstadoDeVenta::Anulado,
            ]);

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
                    $factor = (float) ($u->factor ?? 0);
                    $precio = (float) ($u->precio ?? 0);
                    $recargo = (float) ($u->recargo ?? 0);
                    $descuento = (float) ($u->descuento ?? 0);

                    $subtotal = $precio * $cantidad * $factor;
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
                    $factor = (float) ($u['factor'] ?? 0);
                    $precio = (float) ($u['precio'] ?? 0);
                    $recargo = (float) ($u['recargo'] ?? 0);
                    $descuento = (float) ($u['descuento'] ?? 0);
                    $descuentoTipo = $u['descuento_tipo'] ?? null;

                    $subtotal = $precio * $cantidad * $factor;
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
            throw new \Exception('En ventas al contado debes seleccionar Ingreso asociado o Métodos de Pago');
        }

        // Validar pagos a crédito
        if (
            $estadoEnum === EstadoDeVenta::Creado &&
            $formaDePagoEnum === FormaDePago::Credito &&
            (isset($venta['ingreso_dinero_id']) || (isset($venta['despliegue_de_pago_ventas']) && ! empty($venta['despliegue_de_pago_ventas'])))
        ) {
            throw new \Exception('En ventas a crédito no debes seleccionar Ingreso asociado ni Métodos de Pago');
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
            \Log::info('=== INICIANDO REGISTRO EN CAJA ===');
            \Log::info("Venta ID: {$venta->id}");
            \Log::info("User ID: {$venta->user_id}");

            // 1. Buscar la caja abierta del vendedor
            $apertura = AperturaCierreCaja::where('user_id', $venta->user_id)
                ->where('estado', 'abierta')
                ->first();

            // Si el vendedor no tiene apertura propia, buscar cualquier apertura activa de una caja principal
            if (!$apertura) {
                \Log::info("ℹ️ Vendedor sin apertura propia, buscando apertura activa de caja principal");
                
                // Buscar cualquier apertura activa (para vendedores que no tienen caja asignada)
                $apertura = AperturaCierreCaja::where('estado', 'abierta')
                    ->orderBy('fecha_apertura', 'desc')
                    ->first();
                
                if ($apertura) {
                    \Log::info("✅ Usando apertura de caja principal: {$apertura->id} (Caja: {$apertura->caja_principal_id})");
                }
            }

            if ($apertura) {
                \Log::info("✅ Apertura encontrada: {$apertura->id}");
                \Log::info("Caja Principal ID: {$apertura->caja_principal_id}");
            } else {
                \Log::info("ℹ️ No hay apertura de caja disponible, usando sub-cajas directamente");
            }

            $totalVenta = $this->getTotalVenta($venta);
            \Log::info("Total venta: {$totalVenta}");

            // 2. Procesar cada método de pago
            foreach ($desplieguesDePago as $desplieguePago) {
                \Log::info("Procesando método de pago: {$desplieguePago['despliegue_de_pago_id']}");

                $despliegue = DespliegueDePago::with('metodoDePago')->findOrFail($desplieguePago['despliegue_de_pago_id']);
                $monto = (float) $desplieguePago['monto'];

                \Log::info("Despliegue: {$despliegue->name}, Monto: {$monto}");

                // 3. Determinar la sub-caja a usar
                $subCaja = null;
                
                // PRIORIDAD 1: Si viene sub_caja_id en los datos, usarlo directamente
                if (isset($desplieguePago['sub_caja_id']) && $desplieguePago['sub_caja_id']) {
                    $subCajaTemp = SubCaja::find($desplieguePago['sub_caja_id']);
                    
                    if ($subCajaTemp) {
                        // ✅ VALIDAR: Verificar que la sub-caja acepta el tipo de comprobante de la venta
                        if ($subCajaTemp->aceptaComprobante($venta->tipo_documento->value)) {
                            $subCaja = $subCajaTemp;
                            \Log::info("✅ Usando sub-caja especificada: {$subCaja->id} - {$subCaja->nombre}");
                        } else {
                            \Log::warning("⚠️ Sub-caja {$subCajaTemp->id} - {$subCajaTemp->nombre} NO acepta tipo de comprobante {$venta->tipo_documento->value}");
                            \Log::warning("Tipos aceptados: " . json_encode($subCajaTemp->tipos_comprobante));
                            // No usar esta sub-caja, continuar con las siguientes prioridades
                        }
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
                        
                        if ($subCaja) {
                            \Log::info('Usando Caja Chica de la apertura');
                        }
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
                    \Log::error("❌ No se encontró sub-caja para registrar venta {$venta->id}");
                    continue;
                }

                \Log::info("✅ Sub-caja encontrada: {$subCaja->id} - {$subCaja->nombre}");

                // ✅ VALIDACIÓN CRÍTICA: Verificar que la sub-caja acepta el tipo de comprobante
                if (!$subCaja->aceptaComprobante($venta->tipo_documento->value)) {
                    \Log::error("❌ VALIDACIÓN FALLIDA: Sub-caja '{$subCaja->nombre}' NO acepta tipo de comprobante '{$venta->tipo_documento->value}'", [
                        'sub_caja_id' => $subCaja->id,
                        'sub_caja_nombre' => $subCaja->nombre,
                        'tipos_aceptados' => $subCaja->tipos_comprobante,
                        'tipo_documento_venta' => $venta->tipo_documento->value,
                        'venta_id' => $venta->id,
                        'despliegue_pago_id' => $desplieguePago['despliegue_de_pago_id'],
                    ]);
                    
                    // NO registrar esta transacción, continuar con el siguiente método de pago
                    continue;
                }

                \Log::info("✅ Validación exitosa: Sub-caja acepta el tipo de comprobante", [
                    'sub_caja' => $subCaja->nombre,
                    'tipo_documento' => $venta->tipo_documento->value,
                ]);

                // 4. Actualizar saldo de la sub-caja
                $saldoAnterior = $subCaja->saldo_actual;
                $subCaja->saldo_actual += $monto;
                $subCaja->save();

                \Log::info("Saldo actualizado: {$saldoAnterior} -> {$subCaja->saldo_actual}");

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

            \Log::info("✅ Venta {$venta->id} registrada en sub-cajas");

        } catch (\Exception $e) {
            // Log el error pero no fallar la venta
            \Log::error('Error al registrar venta en caja: '.$e->getMessage());
        }
    }

    /**
     * Determinar si un método de pago es efectivo
     */
    private function esMetodoPagoEfectivo($despliegue)
    {
        $metodoPago = $despliegue->metodoDePago;
        if (! $metodoPago) {
            return false;
        }

        // Verificar si el nombre contiene palabras clave de efectivo
        $nombre = strtolower($metodoPago->name);

        return str_contains($nombre, 'efectivo') ||
               str_contains($nombre, 'cash') ||
               str_contains($nombre, 'cch') ||
               str_contains($nombre, 'ca');
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

        \Log::info("Sub-cajas compatibles encontradas: " . count($subCajasCompatibles));
        \Log::info("Sub-caja seleccionada: {$subCajasCompatibles[0]['subCaja']->nombre} (Prioridad: {$subCajasCompatibles[0]['prioridad']})");

        return $subCajasCompatibles[0]['subCaja'];
    }

    /**
     * Buscar sub-caja globalmente (en todas las cajas principales) que acepte un método de pago específico
     * Se usa cuando no hay apertura de caja del vendedor
     */
    private function buscarSubCajaGlobalParaMetodoPago($desplieguePagoId, $tipoComprobante)
    {
        \Log::info("🔍 Buscando sub-caja global para método de pago: {$desplieguePagoId}, tipo comprobante: {$tipoComprobante}");

        // Buscar todas las sub-cajas activas (incluyendo Caja Chica)
        $subCajas = SubCaja::where('estado', true)->get();

        \Log::info("📊 Total sub-cajas activas: " . $subCajas->count());

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
            \Log::warning("❌ No se encontró ninguna sub-caja compatible globalmente");
            return null;
        }

        // Ordenar por prioridad (menor es mejor) y luego por especificidad (menor es más específico)
        usort($subCajasCompatibles, function ($a, $b) {
            if ($a['prioridad'] !== $b['prioridad']) {
                return $a['prioridad'] - $b['prioridad'];
            }
            return $a['especificidad'] - $b['especificidad'];
        });

        \Log::info("✅ Sub-cajas compatibles encontradas globalmente: " . count($subCajasCompatibles));
        \Log::info("Sub-caja seleccionada: {$subCajasCompatibles[0]['subCaja']->nombre} (Caja Principal: {$subCajasCompatibles[0]['subCaja']->caja_principal_id}, Prioridad: {$subCajasCompatibles[0]['prioridad']})");

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

        foreach ($venta['productos_por_almacen'] ?? [] as $producto) {
            // Calcular cantidad total en unidad base
            $cantidadTotal = 0;
            foreach ($producto['unidades_derivadas'] ?? [] as $unidad) {
                $cantidad = (float) ($unidad['cantidad'] ?? 0);
                $factor = (float) ($unidad['factor'] ?? 1);
                $cantidadTotal += $cantidad * $factor;
            }

            // Intentar obtener producto_id y categoria_id
            $productoId = null;
            $categoriaId = null;

            // Opción 1: producto_almacen_id viene en el request
            $productoAlmacenId = $producto['producto_almacen_id'] ?? null;
            if ($productoAlmacenId) {
                $productoAlmacen = ProductoAlmacen::with('producto.categoria')
                    ->find($productoAlmacenId);
                if ($productoAlmacen && $productoAlmacen->producto) {
                    $productoId = $productoAlmacen->producto->id;
                    $categoriaId = $productoAlmacen->producto->categoria_id ?? null;
                }
            }

            // Opción 2: producto_id viene directo en el request (frontend envía esto)
            if (!$productoId && isset($producto['producto_id'])) {
                $productoId = $producto['producto_id'];
                $productoModel = \App\Models\Producto::find($productoId);
                $categoriaId = $productoModel?->categoria_id ?? null;
            }

            if ($productoId) {
                $detalles[] = [
                    'producto_id' => $productoId,
                    'categoria_id' => $categoriaId,
                    'cantidad' => $cantidadTotal,
                ];
            }
        }

        return $detalles;
    }

    /**
     * Listar ventas a crédito con saldo pendiente (para módulo "Ventas por Cobrar")
     */
    public function ventasPorCobrar(Request $request)
    {
        $request->validate([
            'almacen_id'  => 'sometimes|integer',
            'cliente_id'  => 'sometimes|integer',
            'user_id'     => 'sometimes|string',
            'desde'       => 'sometimes|date',
            'hasta'       => 'sometimes|date',
            'search'      => 'sometimes|string',
            'dias'        => 'sometimes|integer|min:1', // Ventas que vencen en X días
            'per_page'    => 'sometimes|integer|min:-1|max:200',
            'page'        => 'sometimes|integer|min:1',
        ]);

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
            // Solo ventas a crédito
            ->where('forma_de_pago', FormaDePago::Credito)
            // Solo activas (no anuladas)
            ->where('estado_de_venta', '!=', EstadoDeVenta::Anulado);

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

        if ($request->has('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->has('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Filtro por días a vencer (ej: ventas que vencen en 15 días desde hoy)
        if ($request->has('dias')) {
            $fechaLimite = now()->addDays($request->dias)->toDateString();
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', $fechaLimite);
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
            $ventas = $query->orderBy('fecha', 'asc')->limit(200)->get();

            // Filtrar solo las que tienen saldo > 0
            $ventasConSaldo = $ventas->filter(function ($venta) {
                $total        = $this->getTotalVenta($venta);
                $totalCobrado = (float) ($venta->total_cobrado ?? 0);
                return ($total - $totalCobrado) > 0.01;
            });

            return response()->json([
                'data'  => $ventasConSaldo->values(),
                'total' => $ventasConSaldo->count(),
            ]);
        }

        $ventas = $query->orderBy('fecha', 'asc')->paginate($perPage);

        // Filtrar solo las que tienen saldo pendiente
        $ventasConSaldo = $ventas->getCollection()->filter(function ($venta) {
            $total        = $this->getTotalVenta($venta);
            $totalCobrado = (float) ($venta->total_cobrado ?? 0);
            return ($total - $totalCobrado) > 0.01;
        });

        $ventas->setCollection($ventasConSaldo);

        return response()->json([
            'data'         => $ventas->items(),
            'total'        => $ventasConSaldo->count(),
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
                'fecha'                 => \Carbon\Carbon::parse($validated['fecha'])->format('Y-m-d'),
                'observacion'           => $validated['observacion'] ?? null,
                'numero_letra'          => $validated['numero_letra'] ?? null,
                'numero_operacion'      => $validated['numero_operacion'] ?? null,
                'estado'                => true,
                'user_id'               => $validated['user_id'],
            ]);

            // Actualizar estado de la venta si quedó completamente pagada
            $nuevoTotalCobrado = $totalCobrado + $validated['monto'];
            if ($nuevoTotalCobrado >= ($totalVenta - 0.01)) {
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
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'monto_total'           => 'required|numeric|min:0.01',
            'fecha'                 => 'required|date',
            'observacion'           => 'nullable|string|max:500',
            'numero_operacion'      => 'nullable|string|max:100',
            'user_id'               => 'required|string|exists:user,id',
            'distribucion'          => 'required|array|min:1',
            'distribucion.*.venta_id' => 'required|string',
            'distribucion.*.monto'    => 'required|numeric|min:0.01',
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

                // Crear el cobro
                $cobro = $venta->cobrosVenta()->create([
                    'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'],
                    'monto'                 => $item['monto'],
                    'fecha'                 => \Carbon\Carbon::parse($validated['fecha'])->format('Y-m-d'),
                    'observacion'           => $validated['observacion'] ?? null,
                    'numero_operacion'      => $validated['numero_operacion'] ?? null,
                    'estado'                => true,
                    'user_id'               => $validated['user_id'],
                ]);

                // Actualizar estado si quedó completamente pagada
                $nuevoTotalCobrado = $totalCobrado + $item['monto'];
                if ($nuevoTotalCobrado >= ($totalVenta - 0.01)) {
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
            ], 201);
        });
    }
}
