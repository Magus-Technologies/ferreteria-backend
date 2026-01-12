<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDeCompra;
use App\Enums\FormaDePago;
use App\Enums\TipoMoneda;
use App\Models\Compra;
use App\Models\DespliegueDePago;
use App\Models\EgresoDinero;
use App\Models\MetodoDePago;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCompra;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'estado_de_compra' => 'sometimes|string',
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

        $query = Compra::query()
            ->with([
                'proveedor:id,ruc,razon_social',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
                'user:id,name',
            ])
            ->withCount([
                'recepcionesAlmacen as recepciones_almacen_count' => function ($query) {
                    $query->where('estado', true);
                },
                'pagosDeCompras as pagos_de_compras_count' => function ($query) {
                    $query->where('estado', true);
                },
            ]);

        // Filter by almacen_id
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        // Filter by estado_de_compra
        if ($request->has('estado_de_compra')) {
            $estadoEnum = EstadoDeCompra::tryFrom($request->estado_de_compra);
            if ($estadoEnum) {
                $query->where('estado_de_compra', $estadoEnum->value);
            }
        }

        // Filter by proveedor_id
        if ($request->has('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
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

        if ($perPage === -1) {
            // Return all without pagination
            return response()->json([
                'data' => $query->orderBy('fecha', 'desc')->limit(100)->get(),
                'total' => $query->count(),
            ]);
        }

        $compras = $query->orderBy('fecha', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $compras->items(),
            'total' => $compras->total(),
            'current_page' => $compras->currentPage(),
            'per_page' => $compras->perPage(),
            'last_page' => $compras->lastPage(),
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
            'serie' => 'nullable|string',
            'numero' => 'nullable|integer',
            'descripcion' => 'nullable|string',
            'forma_de_pago' => 'required|string',
            'tipo_moneda' => 'required|string',
            'tipo_de_cambio' => 'nullable|numeric',
            'percepcion' => 'nullable|numeric',
            'numero_dias' => 'nullable|integer',
            'fecha_vencimiento' => 'nullable|date',
            'fecha' => 'required|date',
            'guia' => 'nullable|string',
            'estado_de_compra' => 'required|string',
            'egreso_dinero_id' => 'nullable|string',
            'despliegue_de_pago_id' => 'nullable|string',
            'user_id' => 'required|string',
            'almacen_id' => 'required|integer',
            'proveedor_id' => 'required|integer',
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

        return DB::transaction(function () use ($validated) {
            // Validar nueva compra
            $this->validarNuevaCompra($validated);

            // Convert enums
            $estadoEnum = EstadoDeCompra::from($validated['estado_de_compra']);
            $formaDePagoEnum = FormaDePago::from($validated['forma_de_pago']);
            $tipoMonedaEnum = TipoMoneda::from($validated['tipo_moneda']);

            // Create compra
            $compra = Compra::create([
                'id' => $validated['id'] ?? (string) \Illuminate\Support\Str::ulid(),
                'tipo_documento' => $validated['tipo_documento'],
                'serie' => $validated['serie'],
                'numero' => $validated['numero'],
                'descripcion' => $validated['descripcion'],
                'forma_de_pago' => $formaDePagoEnum,
                'tipo_moneda' => $tipoMonedaEnum,
                'tipo_de_cambio' => $validated['tipo_de_cambio'],
                'percepcion' => $validated['percepcion'],
                'numero_dias' => $validated['numero_dias'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'fecha' => $validated['fecha'],
                'guia' => $validated['guia'],
                'estado_de_compra' => $estadoEnum,
                'egreso_dinero_id' => $validated['egreso_dinero_id'],
                'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'],
                'user_id' => $validated['user_id'],
                'almacen_id' => $validated['almacen_id'],
                'proveedor_id' => $validated['proveedor_id'],
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

        return response()->json(['data' => $compra]);
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
                'despliegue_de_pago_id' => $compra->despliegue_de_pago_id,
            ], $validated);

            // Validar nueva compra
            $this->validarNuevaCompra($dataParaValidar);

            // Devolver dinero de compra anterior
            $this->devolverDineroDeCompra($compra);

            // Convert enums if present
            $updateData = [];
            foreach ($validated as $key => $value) {
                if ($key === 'estado_de_compra') {
                    $updateData[$key] = EstadoDeCompra::from($value);
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
                $compra->estado_de_compra === EstadoDeCompra::Procesado ||
                $compra->estado_de_compra === EstadoDeCompra::Anulado
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
                'estado_de_compra' => EstadoDeCompra::Anulado,
                'egreso_dinero_id' => null,
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
        $estadoEnum = EstadoDeCompra::from($compra['estado_de_compra']);
        $formaDePagoEnum = FormaDePago::from($compra['forma_de_pago']);

        if (
            $estadoEnum === EstadoDeCompra::Creado &&
            $formaDePagoEnum === FormaDePago::Contado &&
            !isset($compra['egreso_dinero_id']) &&
            !isset($compra['despliegue_de_pago_id'])
        ) {
            throw new \Exception('En compras al contado debes seleccionar Egreso asociado o Despliegue de Pago');
        }

        if (
            $estadoEnum === EstadoDeCompra::Creado &&
            $formaDePagoEnum === FormaDePago::Credito &&
            (isset($compra['egreso_dinero_id']) || isset($compra['despliegue_de_pago_id']))
        ) {
            throw new \Exception('En compras a crédito no debes seleccionar Egreso asociado ni Despliegue de Pago');
        }

        if (
            $estadoEnum === EstadoDeCompra::Creado &&
            isset($compra['egreso_dinero_id']) &&
            isset($compra['despliegue_de_pago_id'])
        ) {
            throw new \Exception('No puedes seleccionar Egreso asociado y Despliegue de Pago al mismo tiempo');
        }

        if (
            $estadoEnum === EstadoDeCompra::Creado ||
            ($estadoEnum === EstadoDeCompra::EnEspera &&
                isset($compra['serie']) &&
                isset($compra['numero']) &&
                isset($compra['proveedor_id']))
        ) {
            $existingCompra = Compra::where('proveedor_id', $compra['proveedor_id'])
                ->where('serie', $compra['serie'])
                ->where('numero', $compra['numero']);

            if (isset($compra['id'])) {
                $existingCompra->where('id', '!=', $compra['id']);
            }

            if ($existingCompra->exists()) {
                throw new \Exception('Ya existe una compra con el mismo proveedor, serie y número');
            }
        }
    }

    /**
     * Proceso post compra
     */
    private function procesoPostCompra($compra)
    {
        $estadoEnum = EstadoDeCompra::from($compra['estado_de_compra']);

        if ($estadoEnum === EstadoDeCompra::Creado) {
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
                $despliegue = DespliegueDePago::findOrFail($compra['despliegue_de_pago_id']);

                MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                    ->decrement('monto', $totalSoles);
            }
        }
    }

    /**
     * Devolver dinero de compra
     */
    private function devolverDineroDeCompra($compra)
    {
        if ($compra->estado_de_compra === EstadoDeCompra::Creado) {
            $totalSoles = $this->getTotalCompra($compra);

            if ($compra->despliegue_de_pago_id) {
                $despliegue = DespliegueDePago::findOrFail($compra->despliegue_de_pago_id);

                MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                    ->increment('monto', $totalSoles);
            }

            if ($compra->egreso_dinero_id) {
                $egreso = EgresoDinero::findOrFail($compra->egreso_dinero_id);
                $despliegue = DespliegueDePago::findOrFail($egreso->despliegue_de_pago_id);

                $reintegro = (float) $egreso->monto - (float) $egreso->vuelto;

                if ($reintegro > 0) {
                    MetodoDePago::where('id', $despliegue->metodo_de_pago_id)
                        ->increment('monto', $reintegro);
                }
            }
        }
    }
}
