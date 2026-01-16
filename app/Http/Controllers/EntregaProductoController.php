<?php

namespace App\Http\Controllers;

use App\Models\DetalleEntregaProducto;
use App\Models\EntregaProducto;
use App\Models\UnidadDerivadaInmutableVenta;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntregaProductoController extends Controller
{
    /**
     * Display a listing of the resource (todas las entregas o por venta).
     */
    public function index(Request $request)
    {
        $request->validate([
            'venta_id' => 'sometimes|string',
            'almacen_salida_id' => 'sometimes|integer',
            'chofer_id' => 'sometimes|string',
            'estado_entrega' => 'sometimes|string',
            'fecha_desde' => 'sometimes|date',
            'fecha_hasta' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = EntregaProducto::query()
            ->with([
                'venta:id,serie,numero,cliente_id',
                'venta.cliente:id,nombres,apellidos,razon_social',
                'almacenSalida:id,name',
                'chofer:id,name',
                'user:id,name',
                'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
            ]);

        // Filter by venta_id
        if ($request->has('venta_id')) {
            $query->where('venta_id', $request->venta_id);
        }

        // Filter by almacen_salida_id
        if ($request->has('almacen_salida_id')) {
            $query->where('almacen_salida_id', $request->almacen_salida_id);
        }

        // Filter by chofer_id
        if ($request->has('chofer_id')) {
            $query->where('chofer_id', $request->chofer_id);
        }

        // Filter by estado_entrega
        if ($request->has('estado_entrega')) {
            $query->where('estado_entrega', $request->estado_entrega);
        }

        // Filter by fecha range
        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_entrega', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha_entrega', '<=', $request->fecha_hasta);
        }

        $perPage = $request->input('per_page', 50);

        if ($perPage === -1) {
            return response()->json([
                'data' => $query->orderBy('created_at', 'desc')->limit(100)->get(),
                'total' => $query->count(),
            ]);
        }

        $entregas = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $entregas->items(),
            'total' => $entregas->total(),
            'current_page' => $entregas->currentPage(),
            'per_page' => $entregas->perPage(),
            'last_page' => $entregas->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'venta_id' => 'required|string',
            'tipo_entrega' => 'required|string',
            'tipo_despacho' => 'nullable|string',
            'estado_entrega' => 'required|string',
            'fecha_entrega' => 'required|date',
            'fecha_programada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'direccion_entrega' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'almacen_salida_id' => 'required|integer',
            'chofer_id' => 'nullable|string',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer', // Nuevo campo
            'user_id' => 'required|string',
            'productos_entregados' => 'required|array',
            'productos_entregados.*.unidad_derivada_venta_id' => 'required|integer',
            'productos_entregados.*.cantidad_entregada' => 'required|numeric|min:0',
            'productos_entregados.*.ubicacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            // Verificar que la venta existe
            $venta = Venta::findOrFail($validated['venta_id']);

            // Crear entrega
            $entrega = EntregaProducto::create([
                'venta_id' => $validated['venta_id'],
                'tipo_entrega' => $validated['tipo_entrega'],
                'tipo_despacho' => $validated['tipo_despacho'] ?? null,
                'estado_entrega' => $validated['estado_entrega'],
                'fecha_entrega' => $validated['fecha_entrega'],
                'fecha_programada' => $validated['fecha_programada'] ?? null,
                'hora_inicio' => $validated['hora_inicio'] ?? null,
                'hora_fin' => $validated['hora_fin'] ?? null,
                'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'almacen_salida_id' => $validated['almacen_salida_id'],
                'chofer_id' => $validated['chofer_id'] ?? null,
                'quien_entrega' => $validated['quien_entrega'] ?? null, // Nuevo campo
                'user_id' => $validated['user_id'],
            ]);

            // Crear detalles y actualizar cantidades pendientes
            foreach ($validated['productos_entregados'] as $detalle) {
                // Validar que la unidad derivada venta existe y pertenece a esta venta
                $unidadDerivadaVenta = UnidadDerivadaInmutableVenta::whereHas('productoAlmacenVenta', function ($query) use ($validated) {
                    $query->where('venta_id', $validated['venta_id']);
                })->findOrFail($detalle['unidad_derivada_venta_id']);

                // Validar que hay suficiente cantidad pendiente
                $cantidadEntregada = (float) $detalle['cantidad_entregada'];
                $cantidadPendiente = (float) $unidadDerivadaVenta->cantidad_pendiente;

                if ($cantidadEntregada > $cantidadPendiente) {
                    throw new \Exception("La cantidad entregada ({$cantidadEntregada}) no puede ser mayor a la cantidad pendiente ({$cantidadPendiente})");
                }

                // Crear detalle de entrega
                DetalleEntregaProducto::create([
                    'entrega_producto_id' => $entrega->id,
                    'unidad_derivada_venta_id' => $detalle['unidad_derivada_venta_id'],
                    'cantidad_entregada' => $cantidadEntregada,
                    'ubicacion' => $detalle['ubicacion'] ?? null,
                ]);

                // Actualizar cantidad pendiente
                $unidadDerivadaVenta->decrement('cantidad_pendiente', $cantidadEntregada);
            }

            return response()->json([
                'data' => $entrega->load([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social',
                    'almacenSalida:id,name',
                    'chofer:id,name',
                    'user:id,name',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
                ]),
                'message' => 'Entrega de producto creada exitosamente',
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $entrega = EntregaProducto::with([
            'venta:id,serie,numero,cliente_id,almacen_id',
            'venta.cliente:id,nombres,apellidos,razon_social,direccion,telefono',
            'venta.almacen:id,name',
            'almacenSalida:id,name',
            'chofer:id,name',
            'user:id,name',
            'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto.marca',
            'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
        ])->findOrFail($id);

        return response()->json(['data' => $entrega]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'tipo_entrega' => 'sometimes|string',
            'tipo_despacho' => 'nullable|string',
            'estado_entrega' => 'sometimes|string',
            'fecha_entrega' => 'sometimes|date',
            'fecha_programada' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'direccion_entrega' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'almacen_salida_id' => 'sometimes|integer',
            'chofer_id' => 'nullable|string',
            'quien_entrega' => 'nullable|string|in:vendedor,almacen,chofer', // Nuevo campo
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($id);

            // Update entrega
            $entrega->update($validated);

            return response()->json([
                'data' => $entrega->fresh([
                    'venta:id,serie,numero,cliente_id',
                    'venta.cliente:id,nombres,apellidos,razon_social',
                    'almacenSalida:id,name',
                    'chofer:id,name',
                    'user:id,name',
                    'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
                ]),
                'message' => 'Entrega de producto actualizada exitosamente',
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (anular entrega).
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $entrega = EntregaProducto::with('productosEntregados')->findOrFail($id);

            // Revertir cantidades pendientes
            foreach ($entrega->productosEntregados as $detalle) {
                $unidadDerivadaVenta = UnidadDerivadaInmutableVenta::findOrFail($detalle->unidad_derivada_venta_id);
                $unidadDerivadaVenta->increment('cantidad_pendiente', (float) $detalle->cantidad_entregada);
            }

            // Eliminar detalles
            DetalleEntregaProducto::where('entrega_producto_id', $id)->delete();

            // Eliminar entrega
            $entrega->delete();

            return response()->json([
                'data' => 'ok',
                'message' => 'Entrega de producto eliminada exitosamente',
            ]);
        });
    }
}
