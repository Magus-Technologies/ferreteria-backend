<?php

namespace App\Http\Controllers;

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
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentaController extends Controller
{
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
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Venta::query()
            ->with([
                'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,direccion,direccion_2,direccion_3,direccion_4,telefono,email',
                'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'despliegueDePagoVentas.despliegueDePago',
                'user:id,name',
                'almacen:id,name',
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
            'tipo_moneda' => 'required|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'fecha' => 'required|date',
            'estado_de_venta' => 'required|string',
            'cliente_id' => 'nullable|integer', // Nullable para boletas y notas de venta
            'direccion_seleccionada' => 'nullable|string|in:D1,D2,D3,D4', // Nueva validación
            'recomendado_por_id' => 'nullable|integer',
            'user_id' => 'required|string',
            'almacen_id' => 'required|integer',
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
            foreach ($validated['productos_por_almacen'] as $producto) {
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

            // Proceso post venta
            $validated['id'] = $venta->id;
            $this->procesoPostVenta($validated);

            return response()->json([
                'data' => $venta->load([
                    'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'despliegueDePagoVentas.despliegueDePago',
                    'user:id,name',
                    'almacen:id,name',
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
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,direccion,direccion_2,direccion_3,direccion_4,telefono,email',
            'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.detallesEntrega',
            'despliegueDePagoVentas.despliegueDePago.metodoDePago',
            'user:id,name',
            'almacen:id,name',
            'entregasProductos',
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
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $venta = Venta::with([
                'productosPorAlmacen.unidadesDerivadas',
                'despliegueDePagoVentas',
            ])->findOrFail($id);

            // Add id to validated data for validation
            $validated['id'] = $id;

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
                } elseif ($key !== 'productos_por_almacen' && $key !== 'despliegue_de_pago_ventas' && $key !== 'id') {
                    $updateData[$key] = $value;
                }
            }

            // Update venta
            $venta->update($updateData);

            // If productos_por_almacen is provided, update them
            if (isset($validated['productos_por_almacen'])) {
                // Delete existing productos_por_almacen
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

            // Proceso post venta
            $validated['id'] = $id;
            $this->procesoPostVenta($validated);

            return response()->json([
                'data' => $venta->fresh([
                    'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                    'productosPorAlmacen.productoAlmacen.producto.marca',
                    'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                    'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                    'despliegueDePagoVentas.despliegueDePago',
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
            $estadoEnum === EstadoDeVenta::Creado ||
            ($estadoEnum === EstadoDeVenta::EnEspera &&
                isset($venta['serie']) &&
                isset($venta['numero']))
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

            // 1. Buscar la caja abierta del vendedor (opcional)
            $apertura = AperturaCierreCaja::where('user_id', $venta->user_id)
                ->where('estado', 'abierta')
                ->first();

            if ($apertura) {
                \Log::info("✅ Apertura encontrada: {$apertura->id}");
                \Log::info("Caja Principal ID: {$apertura->caja_principal_id}");
            } else {
                \Log::info("ℹ️ No hay apertura de caja para el usuario, usando sub-cajas directamente");
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
                    $subCaja = SubCaja::find($desplieguePago['sub_caja_id']);
                    if ($subCaja) {
                        \Log::info("✅ Usando sub-caja especificada: {$subCaja->id} - {$subCaja->nombre}");
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
}
