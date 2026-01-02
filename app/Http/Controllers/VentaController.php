<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDeVenta;
use App\Enums\FormaDePago;
use App\Enums\TipoDocumento;
use App\Enums\TipoMoneda;
use App\Models\DespliegueDePago;
use App\Models\IngresoDinero;
use App\Models\MetodoDePago;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenVenta;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\DespliegueDePagoVenta;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Venta::query()
            ->with([
                'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                'recomendadoPor:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'despliegueDePagoVentas.despliegueDePago',
                'user:id,name',
                'almacen:id,name',
            ])
            ->withCount('entregasProductos as entregas_productos_count');

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

        // Search by serie, numero, or cliente
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
            'despliegue_de_pago_ventas.*.monto' => 'required|numeric',
            'despliegue_de_pago_ventas.*.referencia' => 'nullable|string|max:191',
            'despliegue_de_pago_ventas.*.recibe_efectivo' => 'nullable|numeric',
            'ingreso_dinero_id' => 'nullable|string',
        ]);

        // DEBUG: Ver TODO el request
        \Log::info('=== INICIO REQUEST VENTA ===');
        \Log::info('Request completo:', $validated);
        \Log::info('Tipo documento:', [
            'valor' => $validated['tipo_documento'],
            'tipo' => gettype($validated['tipo_documento']),
            'length' => strlen($validated['tipo_documento'])
        ]);
        \Log::info('Forma de pago:', [
            'valor' => $validated['forma_de_pago'],
            'tipo' => gettype($validated['forma_de_pago'])
        ]);
        \Log::info('=== FIN DEBUG ===');

        return DB::transaction(function () use ($validated) {
            
            // Si no se proporciona cliente_id, usar "CLIENTE VARIOS" (DNI: 99999999)
            if (empty($validated['cliente_id'])) {
                $clienteVarios = \App\Models\Cliente::where('numero_documento', '99999999')->first();
                
                if (!$clienteVarios) {
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

                if (!$serieDoc) {
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
                'recomendado_por_id' => $validated['recomendado_por_id'] ?? null,
                'user_id' => $validated['user_id'],
                'almacen_id' => $validated['almacen_id'],
            ]);

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

                $productoAlmacenVenta = ProductoAlmacenVenta::create([
                    'venta_id' => $venta->id,
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
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,direccion,telefono,email',
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

                    if (!$productoAlmacenId && isset($producto['producto_id'])) {
                        $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                            ->where('almacen_id', $venta->almacen_id)
                            ->first();

                        if (!$productoAlmacen) {
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

                        if (!$unidadDerivadaInmutableId && isset($unidad['unidad_derivada_inmutable_name'])) {
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
            !isset($venta['ingreso_dinero_id']) &&
            (!isset($venta['despliegue_de_pago_ventas']) || empty($venta['despliegue_de_pago_ventas']))
        ) {
            throw new \Exception('En ventas al contado debes seleccionar Ingreso asociado o Métodos de Pago');
        }

        // Validar pagos a crédito
        if (
            $estadoEnum === EstadoDeVenta::Creado &&
            $formaDePagoEnum === FormaDePago::Credito &&
            (isset($venta['ingreso_dinero_id']) || (isset($venta['despliegue_de_pago_ventas']) && !empty($venta['despliegue_de_pago_ventas'])))
        ) {
            throw new \Exception('En ventas a crédito no debes seleccionar Ingreso asociado ni Métodos de Pago');
        }
    }

    /**
     * Proceso post venta (registrar ingresos en métodos de pago)
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
            if (isset($venta['despliegue_de_pago_ventas']) && !empty($venta['despliegue_de_pago_ventas'])) {
                foreach ($venta['despliegue_de_pago_ventas'] as $desplieguePago) {
                    $despliegue = DespliegueDePago::findOrFail($desplieguePago['despliegue_de_pago_id']);

                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->increment('monto', (float) $desplieguePago['monto']);
                }
            }
        }
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
