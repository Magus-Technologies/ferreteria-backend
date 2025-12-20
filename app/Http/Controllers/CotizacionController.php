<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCotizacion;
use App\Models\UnidadDerivadaInmutableCotizacion;
use App\Models\UnidadDerivadaInmutable;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CotizacionController extends Controller
{
    /**
     * Listar todas las cotizaciones
     */
 public function index(Request $request): JsonResponse
{
    $query = Cotizacion::with([
      'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,direccion,telefono,email',
        'user:id,name',
        'almacen:id,name',
        'productosPorAlmacen.productoAlmacen.producto.marca',
        'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
    ]);

        // Filtros opcionales
        if ($request->has('estado_cotizacion')) {
            $query->where('estado_cotizacion', $request->estado_cotizacion);
        }

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        // Búsqueda por número
        if ($request->has('search')) {
            $query->where('numero', 'like', '%' . $request->search . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $cotizaciones = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($cotizaciones);
    }

    /**
     * Crear una nueva cotización
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer',
            'productos.*.unidad_derivada_factor' => 'required|numeric|min:0',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.precio_venta' => 'required|numeric|min:0',
            'productos.*.recargo' => 'nullable|numeric|min:0',
            'productos.*.descuento_tipo' => 'nullable|in:%,m',
            'productos.*.descuento' => 'nullable|numeric|min:0',
            
            'fecha' => 'required|date',
            'fecha_proforma' => 'nullable|date',
            'tipo_moneda' => 'required|in:s,d',
            'tipo_de_cambio' => 'nullable|numeric|min:0',
            'vigencia_dias' => 'nullable|integer|min:1',
            'fecha_vencimiento' => 'nullable|date',
            
            'vendedor' => 'nullable|string|max:191',
            'forma_de_pago' => 'nullable|string|max:50',
            'ruc_dni' => 'nullable|string|max:20',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
            'tipo_documento' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string',
            'reservar_stock' => 'nullable|boolean',
            
            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        try {
            DB::beginTransaction();

            // Generar ID y número de cotización
            $cotizacionId = 'cot' . Str::random(10);
            $numero = $this->generarNumeroCotizacion();

            // Calcular fecha de vencimiento si no se proporciona
            $vigenciaDias = $validated['vigencia_dias'] ?? 7;
            $fechaVencimiento = $validated['fecha_vencimiento'] ?? 
                now()->addDays($vigenciaDias)->format('Y-m-d H:i:s');

            // Crear la cotización
            $cotizacion = Cotizacion::create([
                'id' => $cotizacionId,
                'numero' => $numero,
                'fecha' => $validated['fecha'],
                'fecha_proforma' => $validated['fecha_proforma'] ?? $validated['fecha'],
                'vigencia_dias' => $vigenciaDias,
                'fecha_vencimiento' => $fechaVencimiento,
                'tipo_moneda' => $validated['tipo_moneda'],
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? 1.0000,
                'observaciones' => $validated['observaciones'] ?? null,
                'estado_cotizacion' => 'pe', // Pendiente
                'reservar_stock' => $validated['reservar_stock'] ?? false,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'ruc_dni' => $validated['ruc_dni'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'user_id' => auth()->id(),
                'vendedor' => $validated['vendedor'] ?? null,
                'forma_de_pago' => $validated['forma_de_pago'] ?? null,
                'almacen_id' => $validated['almacen_id'],
            ]);

            // Procesar productos
            foreach ($validated['productos'] as $productoData) {
                $this->agregarProductoACotizacion(
                    $cotizacion,
                    $productoData,
                    $validated['almacen_id'],
                    $validated['reservar_stock'] ?? false
                );
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $cotizacion->load([
                'cliente',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            ]);

            return response()->json([
                'data' => $cotizacion,
                'message' => 'Cotización creada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agregar un producto a la cotización
     */
    private function agregarProductoACotizacion(
        Cotizacion $cotizacion,
        array $productoData,
        int $almacenId,
        bool $reservarStock
    ): void {
        // Buscar o crear ProductoAlmacen
        $productoAlmacen = ProductoAlmacen::firstOrCreate(
            [
                'producto_id' => $productoData['producto_id'],
                'almacen_id' => $almacenId,
            ],
            [
                'stock_fraccion' => 0,
                'costo' => 0,
                'ubicacion_id' => 1, // Ubicación por defecto
            ]
        );

        // Crear ProductoAlmacenCotizacion
        $productoAlmacenCotizacion = ProductoAlmacenCotizacion::create([
            'cotizacion_id' => $cotizacion->id,
            'producto_almacen_id' => $productoAlmacen->id,
            'costo' => $productoAlmacen->costo,
        ]);

        // Buscar o crear UnidadDerivadaInmutable
        // Primero obtener el nombre de la unidad derivada
        $unidadDerivada = \App\Models\UnidadDerivada::find($productoData['unidad_derivada_id']);
        $nombreUnidad = $unidadDerivada ? $unidadDerivada->name : 'UNIDAD';

        $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
            ['name' => $nombreUnidad]
        );

        // Crear UnidadDerivadaInmutableCotizacion
        UnidadDerivadaInmutableCotizacion::create([
            'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
            'producto_almacen_cotizacion_id' => $productoAlmacenCotizacion->id,
            'factor' => $productoData['unidad_derivada_factor'],
            'cantidad' => $productoData['cantidad'],
            'precio' => $productoData['precio_venta'],
            'recargo' => $productoData['recargo'] ?? 0,
            'descuento_tipo' => $productoData['descuento_tipo'] ?? 'm',
            'descuento' => $productoData['descuento'] ?? 0,
        ]);

        // Si se debe reservar stock, descontarlo
        if ($reservarStock) {
            $cantidadEnFraccion = $productoData['cantidad'] * $productoData['unidad_derivada_factor'];
            $productoAlmacen->decrement('stock_fraccion', $cantidadEnFraccion);
        }
    }

    /**
     * Obtener el siguiente número de cotización (sin crear la cotización)
     */
    public function siguienteNumero(): JsonResponse
    {
        $siguienteNumero = $this->generarNumeroCotizacion();

        return response()->json([
            'numero' => $siguienteNumero,
        ]);
    }

    /**
     * Generar número de cotización único
     */
    private function generarNumeroCotizacion(): string
    {
        $year = date('Y');
        $lastCotizacion = Cotizacion::where('numero', 'like', "COT-{$year}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastCotizacion) {
            $lastNumber = (int) substr($lastCotizacion->numero, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('COT-%s-%03d', $year, $newNumber);
    }

    /**
     * Mostrar una cotización específica
     */
    public function show(string $id): JsonResponse
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'user',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable.unidadDerivada',
        ])->findOrFail($id);

        return response()->json(['data' => $cotizacion]);
    }

    /**
     * Actualizar una cotización
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // TODO: Implementar lógica de actualización
        // Considerar: devolver stock si se cambia reservar_stock de true a false
        return response()->json(['message' => 'Actualización pendiente de implementar'], 501);
    }

    /**
     * Cancelar una cotización (devolver stock si estaba reservado)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::findOrFail($id);

            // Si tenía stock reservado, devolverlo
            if ($cotizacion->reservar_stock) {
                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidadDerivada) {
                        $cantidadEnFraccion = $unidadDerivada->cantidad * $unidadDerivada->factor;
                        $productoAlmacenCotizacion->productoAlmacen->increment('stock_fraccion', $cantidadEnFraccion);
                    }
                }
            }

            // Cambiar estado a cancelado
            $cotizacion->update(['estado_cotizacion' => 'ca']);

            DB::commit();

            return response()->json([
                'message' => 'Cotización cancelada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cancelar la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }
}
