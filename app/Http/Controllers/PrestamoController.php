<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumento;
use App\Models\IngresoSalida;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\Prestamo;
use App\Models\PagoPrestamo;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenPrestamo;
use App\Models\UnidadDerivadaInmutablePrestamo;
use App\Models\UnidadDerivada;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\PrestamoDevolucion;
use App\Models\PrestamoProductoDevuelto;
use App\Services\Kardex\KardexInventarioService;
use App\Services\Producto\ComplementarioStockService;
use App\Services\Cache\ProductoCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PrestamoController extends Controller
{
    /**
     * Listar todos los préstamos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Prestamo::with([
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
            'cliente.direcciones',
            'proveedor:id,razon_social,ruc,direccion,telefono,email',
            'user:id,name',
            'almacen:id,name',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas',
            'pagos.user:id,name',
        ]);

        // Filtros opcionales
        if ($request->has('estado_prestamo')) {
            $query->where('estado_prestamo', $request->estado_prestamo);
        }

        if ($request->has('tipo_operacion')) {
            $query->where('tipo_operacion', $request->tipo_operacion);
        }

        if ($request->has('tipo_entidad')) {
            $query->where('tipo_entidad', $request->tipo_entidad);
        }

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
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
        $prestamos = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($prestamos);
    }

    /**
     * Crear un nuevo préstamo
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer',
            'productos.*.unidad_derivada_factor' => 'required|numeric|min:0',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.costo' => 'nullable|numeric|min:0', // Opcional: Solo se maneja por cantidad

            'fecha' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha',
            'tipo_operacion' => 'required|in:PRESTAR,PEDIR_PRESTADO',
            'tipo_entidad' => 'required|in:CLIENTE,PROVEEDOR',
            'tipo_moneda' => 'required|in:s,d',
            'tipo_de_cambio' => 'nullable|numeric|min:0',

            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'ruc_dni' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',

            'monto_total' => 'nullable|numeric|min:0', // Opcional: Se calcula automáticamente si no se proporciona
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'tipo_interes' => ['nullable', Rule::in(['SIMPLE', 'COMPUESTO'])],
            'dias_gracia' => 'nullable|integer|min:0',
            'garantia' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'vendedor' => 'nullable|string|max:191',

            'almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        // Validar que cliente_id o proveedor_id esté presente según tipo_entidad
        if ($validated['tipo_entidad'] === 'CLIENTE' && empty($validated['cliente_id'])) {
            return response()->json([
                'message' => 'El campo cliente_id es requerido cuando tipo_entidad es CLIENTE',
            ], 422);
        }

        if ($validated['tipo_entidad'] === 'PROVEEDOR' && empty($validated['proveedor_id'])) {
            return response()->json([
                'message' => 'El campo proveedor_id es requerido cuando tipo_entidad es PROVEEDOR',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Calcular monto_total como SUMA DE CANTIDADES (no monto monetario)
            // El cliente NO maneja precios en préstamos, solo cantidades
            if (!isset($validated['monto_total']) || $validated['monto_total'] === null) {
                $validated['monto_total'] = 0;
                foreach ($validated['productos'] as $productoData) {
                    $cantidad = $productoData['cantidad'] ?? 0;
                    $factor = $productoData['unidad_derivada_factor'] ?? 1;
                    // Sumar cantidades en unidad base (cantidad * factor)
                    $validated['monto_total'] += $cantidad * $factor;
                }
            }

            // Generar ID y número de préstamo
            $prestamoId = 'pre' . Str::random(10);
            $numero = $this->generarNumeroPrestamo();

            // Crear el préstamo
            $prestamo = Prestamo::create([
                'id' => $prestamoId,
                'numero' => $numero,
                'fecha' => $validated['fecha'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'tipo_operacion' => $validated['tipo_operacion'],
                'tipo_entidad' => $validated['tipo_entidad'],
                'cliente_id' => $validated['tipo_entidad'] === 'CLIENTE' ? $validated['cliente_id'] : null,
                'proveedor_id' => $validated['tipo_entidad'] === 'PROVEEDOR' ? $validated['proveedor_id'] : null,
                'ruc_dni' => $validated['ruc_dni'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'tipo_moneda' => $validated['tipo_moneda'],
                'tipo_de_cambio' => $validated['tipo_de_cambio'] ?? 1.0000,
                'monto_total' => $validated['monto_total'],
                'monto_pagado' => 0.00,
                'monto_pendiente' => $validated['monto_total'],
                'tasa_interes' => $validated['tasa_interes'] ?? null,
                'tipo_interes' => $validated['tipo_interes'] ?? null,
                'dias_gracia' => $validated['dias_gracia'] ?? 0,
                'garantia' => $validated['garantia'] ?? null,
                'estado_prestamo' => 'pendiente',
                'observaciones' => $validated['observaciones'] ?? null,
                'user_id' => auth()->id(),
                'vendedor' => $validated['vendedor'] ?? null,
                'almacen_id' => $validated['almacen_id'],
            ]);

            // Procesar productos y收集 producto_almacen_ids para movimiento de inventario
            $productosAlmacenIds = [];
            foreach ($validated['productos'] as $productoData) {
                $result = $this->agregarProductoAPrestamo(
                    $prestamo,
                    $productoData,
                    $validated['almacen_id']
                );
                $productosAlmacenIds[] = $result;
            }

            // Crear movimiento de inventario (Ingreso o Salida según tipo_operacion)
            $this->crearMovimientoInventario(
                $prestamo,
                $productosAlmacenIds,
                $validated['productos'],
                $validated['almacen_id']
            );

            DB::commit();

            // Cargar relaciones para la respuesta
$prestamo->load([
                'cliente',
                'proveedor',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto.marca',
                'productosPorAlmacen.unidadesDerivadas',
                'pagos',
                'devoluciones.ingresoSalida',
                'devoluciones.productosDevueltos',
            ]);

            return response()->json([
                'data' => $prestamo,
                'message' => 'Préstamo creado exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agregar un producto al préstamo
     * @return array{producto_almacen_id: int, unidad_derivada_inmutable_prestamo_id: int, cantidad: float, factor: float}
     */
    private function agregarProductoAPrestamo(
        Prestamo $prestamo,
        array $productoData,
        int $almacenId
    ): array {
        // Buscar o crear ProductoAlmacen
        $productoAlmacen = ProductoAlmacen::firstOrCreate(
            [
                'producto_id' => $productoData['producto_id'],
                'almacen_id' => $almacenId,
            ],
            [
                'stock_fraccion' => 0,
                'costo' => 0,
                'ubicacion_id' => 1,
            ]
        );

        // Crear ProductoAlmacenPrestamo
        $productoAlmacenPrestamo = ProductoAlmacenPrestamo::create([
            'prestamo_id' => $prestamo->id,
            'producto_almacen_id' => $productoAlmacen->id,
            'costo' => $productoData['costo'] ?? 0, // Usar costo proporcionado o 0
        ]);

        // Buscar la unidad derivada para obtener su nombre
        $unidadDerivada = UnidadDerivada::find($productoData['unidad_derivada_id']);
        $nombreUnidad = $unidadDerivada ? $unidadDerivada->name : 'UNIDAD';

        // Crear UnidadDerivadaInmutablePrestamo
        $unidadDerivadaInmutablePrestamo = UnidadDerivadaInmutablePrestamo::create([
            'name' => $nombreUnidad,
            'factor' => $productoData['unidad_derivada_factor'],
            'cantidad' => $productoData['cantidad'],
            'producto_almacen_prestamo_id' => $productoAlmacenPrestamo->id,
            'unidad_derivada_id' => $productoData['unidad_derivada_id'],
        ]);

        return [
            'producto_almacen_id' => $productoAlmacen->id,
            'producto_almacen_prestamo_id' => $productoAlmacenPrestamo->id,
            'unidad_derivada_inmutable_prestamo_id' => $unidadDerivadaInmutablePrestamo->id,
            'cantidad' => $productoData['cantidad'],
            'factor' => $productoData['unidad_derivada_factor'],
        ];
    }

    /**
     * Crear movimiento de inventario (Ingreso o Salida) según tipo de operación
     */
    private function crearMovimientoInventario(
        Prestamo $prestamo,
        array $productosAlmacenIds,
        array $productosOriginales,
        int $almacenId
    ): void {
        $esIngreso = $prestamo->tipo_operacion === 'PEDIR_PRESTADO';

        // Determinar tipo_documento
        $tipoDocumento = $esIngreso ? TipoDocumento::Ingreso : TipoDocumento::Salida;

        // Obtener numeración
        $ultimoDocumento = IngresoSalida::where('tipo_documento', $tipoDocumento->value)
            ->orderBy('numero', 'desc')
            ->first();
        $numero = $ultimoDocumento ? $ultimoDocumento->numero + 1 : 1;
        $serie = $esIngreso ? 1 : 2;

        // Crear descripción
        $descripcion = $esIngreso
            ? "Ingreso por préstamo recibido {$prestamo->numero}"
            : "Salida por préstamo {$prestamo->numero}";

        // Crear IngresoSalida
        $ingresoSalida = IngresoSalida::create([
            'tipo_documento' => $tipoDocumento,
            'serie' => $serie,
            'numero' => $numero,
            'fecha' => $prestamo->fecha,
            'almacen_id' => $almacenId,
            'tipo_ingreso_id' => 4, // PRESTAMO
            'proveedor_id' => $esIngreso ? $prestamo->proveedor_id : null,
            'descripcion' => $descripcion,
            'user_id' => auth()->id(),
            'estado' => true,
        ]);

        // Procesar cada producto
        foreach ($productosAlmacenIds as $index => $productoData) {
            $productoOriginal = $productosOriginales[$index];

            // Obtener ProductoAlmacen
            $productoAlmacen = ProductoAlmacen::find($productoData['producto_almacen_id']);

            // Calcular cantidad en fracción
            $factor = $productoData['factor'];
            $cantidad = $productoData['cantidad'];
            $cantidadFraccion = $factor * $cantidad;

            // Crear ProductoAlmacenIngresoSalida
            $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
                'ingreso_id' => $ingresoSalida->id,
                'producto_almacen_id' => $productoAlmacen->id,
                'costo' => $productoAlmacen->costo,
            ]);

            // Crear o buscar UnidadDerivadaInmutable
            $unidadInmutable = UnidadDerivadaInmutable::firstOrCreate(
                ['name' => $productoOriginal['unidad_derivada_name'] ?? 'UNIDAD'],
                ['estado' => true]
            );

            // Crear UnidadDerivadaInmutableIngresoSalida
            UnidadDerivadaInmutableIngresoSalida::create([
                'producto_almacen_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                'unidad_derivada_inmutable_id' => $unidadInmutable->id,
                'factor' => $factor,
                'cantidad' => $cantidad,
                'cantidad_restante' => $cantidad,
                'lote' => null,
                'vencimiento' => null,
            ]);

            // Obtener unidad derivada para el kardex
            $unidadDerivada = UnidadDerivada::find($productoOriginal['unidad_derivada_id']);

            // Actualizar stock
            $stockAnterior = (float) $productoAlmacen->stock_fraccion;
            $stockNuevo = $esIngreso
                ? $stockAnterior + $cantidadFraccion
                : $stockAnterior - $cantidadFraccion;

            // Registrar historial
            HistorialUnidadDerivadaInmutableIngresoSalida::create([
                'unidad_derivada_inmutable_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
            ]);

            // Actualizar stock_fraccion
            $productoAlmacen->update([
                'stock_fraccion' => $stockNuevo,
            ]);

            // Registrar en Kardex
            $kardexService = app(KardexInventarioService::class);
            $productoAlmacenRefresh = ProductoAlmacen::with('producto')->find($productoAlmacen->id);
            $unidadForKardex = (object)[
                'cantidad' => $cantidad,
                'factor' => $factor,
                'unidadDerivadaInmutable' => (object)['name' => $unidadInmutable->name],
            ];

            if ($esIngreso) {
                $kardexService->registrarIngreso($ingresoSalida, $productoAlmacenRefresh, $unidadForKardex, $productoAlmacen->costo, 3, $stockAnterior);
            } else {
                $kardexService->registrarSalida($ingresoSalida, $productoAlmacenRefresh, $unidadForKardex, $productoAlmacen->costo, 4, $stockAnterior);
            }

            // Procesar complementario
            ComplementarioStockService::procesarComplementario(
                $productoAlmacen->id,
                $productoOriginal['unidad_derivada_id'],
                $cantidad,
                $almacenId,
                $esIngreso,
                $ingresoSalida
            );
        }

        // Invalidar cache
        app(ProductoCacheService::class)->invalidateProductosAlmacen($almacenId);
    }

    /**
     * Obtener el siguiente número de préstamo
     */
    public function siguienteNumero(): JsonResponse
    {
        $siguienteNumero = $this->generarNumeroPrestamo();

        return response()->json([
            'numero' => $siguienteNumero,
        ]);
    }

    /**
     * Generar número de préstamo único
     */
    private function generarNumeroPrestamo(): string
    {
        $year = date('Y');
        $lastPrestamo = Prestamo::where('numero', 'like', "PRE-{$year}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastPrestamo) {
            $lastNumber = (int) substr($lastPrestamo->numero, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('PRE-%s-%03d', $year, $newNumber);
    }

    /**
     * Mostrar un préstamo específico
     */
    public function show(string $id): JsonResponse
    {
        $prestamo = Prestamo::with([
            'cliente',
            'proveedor',
            'user' => function ($query) {
                $query->with(['empresa' => function ($q) {
                    $q->select('id', 'ruc', 'razon_social', 'direccion', 'distrito', 'celular', 'email', 'logo');
                }]);
            },
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas',
            'pagos.user',
        ])->findOrFail($id);

        return response()->json(['data' => $prestamo]);
    }

    /**
     * Registrar un pago para un préstamo
     */
    public function registrarPago(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|string|max:50',
            'numero_operacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $prestamo = Prestamo::findOrFail($id);

            // Validar que el monto no exceda el pendiente
            if ($validated['monto'] > $prestamo->monto_pendiente) {
                return response()->json([
                    'message' => 'El monto del pago excede el monto pendiente',
                ], 422);
            }

            // Generar ID y número de pago
            $pagoId = 'pag' . Str::random(10);
            $numeroPago = $this->generarNumeroPago($prestamo->id);

            // Crear el pago
            $pago = PagoPrestamo::create([
                'id' => $pagoId,
                'prestamo_id' => $prestamo->id,
                'numero_pago' => $numeroPago,
                'monto' => $validated['monto'],
                'fecha_pago' => $validated['fecha_pago'],
                'metodo_pago' => $validated['metodo_pago'],
                'numero_operacion' => $validated['numero_operacion'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'user_id' => auth()->id(),
            ]);

            // Los triggers de la base de datos actualizarán automáticamente
            // monto_pagado, monto_pendiente y estado_prestamo

            DB::commit();

            // Recargar el préstamo con los valores actualizados
            $prestamo->refresh();
            $prestamo->load(['pagos.user']);

            return response()->json([
                'data' => $pago,
                'prestamo' => $prestamo,
                'message' => 'Pago registrado exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar número de pago único para un préstamo
     */
    private function generarNumeroPago(string $prestamoId): string
    {
        $count = PagoPrestamo::where('prestamo_id', $prestamoId)->count();
        $newNumber = $count + 1;

        $prestamo = Prestamo::find($prestamoId);
        return sprintf('%s-PAG-%03d', $prestamo->numero, $newNumber);
    }

    /**
     * Listar pagos de un préstamo
     */
    public function listarPagos(string $id): JsonResponse
    {
        $prestamo = Prestamo::findOrFail($id);
        $pagos = $prestamo->pagos()->with('user:id,name')->orderBy('fecha_pago', 'desc')->get();

        return response()->json([
            'data' => $pagos,
        ]);
    }

    /**
     * Eliminar un pago
     */
    public function eliminarPago(string $prestamoId, string $pagoId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $pago = PagoPrestamo::where('id', $pagoId)
                ->where('prestamo_id', $prestamoId)
                ->firstOrFail();

            $pago->delete();

            // El trigger actualizará automáticamente el préstamo

            DB::commit();

            $prestamo = Prestamo::find($prestamoId);

            return response()->json([
                'message' => 'Pago eliminado exitosamente',
                'prestamo' => $prestamo,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un préstamo
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // TODO: Implementar lógica de actualización si es necesaria
        return response()->json(['message' => 'Actualización pendiente de implementar'], 501);
    }

    /**
     * Cancelar un préstamo
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $prestamo = Prestamo::findOrFail($id);

            // Validar que no tenga pagos registrados
            if ($prestamo->pagos()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar un préstamo con pagos registrados',
                ], 422);
            }

            $prestamo->delete();

            DB::commit();

            return response()->json([
                'message' => 'Préstamo eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar el préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar una devolución de productos con movimiento de inventario
     */
    public function registrarDevolucion(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_almacen_prestamo_id' => 'required|integer|exists:productoalmacenprestamo,id',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.factor' => 'required|numeric|min:0',
            'fecha_devolucion' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $prestamo = Prestamo::findOrFail($id);

            // Determinar si es INGRESO o SALIDA basado en tipo_operacion
            // PRESTAR: devolución = INGRESO (el cliente me devuelve, sumo stock)
            // PEDIR_PRESTADO: devolución = SALIDA (yo devuelvo al proveedor, resto stock)
            $esIngreso = $prestamo->tipo_operacion === 'PRESTAR';

            // Crear movimiento de inventario
            $tipoDocumento = $esIngreso ? TipoDocumento::Ingreso : TipoDocumento::Salida;

            // Obtener numeración
            $ultimoDocumento = IngresoSalida::where('tipo_documento', $tipoDocumento->value)
                ->orderBy('numero', 'desc')
                ->first();
            $numero = $ultimoDocumento ? $ultimoDocumento->numero + 1 : 1;
            $serie = $esIngreso ? 1 : 2;

            // Crear descripción
            $descripcion = $esIngreso
                ? "Ingreso por devolución préstamo {$prestamo->numero}"
                : "Salida por devolución préstamo {$prestamo->numero}";

            // Crear IngresoSalida
            $ingresoSalida = IngresoSalida::create([
                'tipo_documento' => $tipoDocumento,
                'serie' => $serie,
                'numero' => $numero,
                'fecha' => $validated['fecha_devolucion'],
                'almacen_id' => $prestamo->almacen_id,
                'tipo_ingreso_id' => 4, // PRESTAMO
                'cliente_id' => $esIngreso ? $prestamo->cliente_id : null,
                'proveedor_id' => !$esIngreso ? $prestamo->proveedor_id : null,
                'descripcion' => $descripcion,
                'user_id' => auth()->id(),
                'estado' => true,
            ]);

            // Generar número de devolución
            $numeroDevolucion = $this->generarNumeroDevolucion($prestamo->id);

            // Crear PrestamoDevolucion
            $prestamoDevolucion = PrestamoDevolucion::create([
                'prestamo_id' => $prestamo->id,
                'ingreso_salida_id' => $ingresoSalida->id,
                'fecha_devolucion' => $validated['fecha_devolucion'],
                'user_id' => auth()->id(),
                'numero_devolucion' => $numeroDevolucion,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            // Procesar cada producto devuelto
            $totalDevuelto = 0;
            foreach ($validated['productos'] as $productoData) {
                $productoAlmacenPrestamo = ProductoAlmacenPrestamo::with('unidadesDerivadas')->find($productoData['producto_almacen_prestamo_id']);

                if (!$productoAlmacenPrestamo) {
                    continue;
                }

                $productoAlmacen = ProductoAlmacen::find($productoAlmacenPrestamo->producto_almacen_id);
                $factor = $productoData['factor'];
                $cantidad = $productoData['cantidad'];
                $cantidadFraccion = $factor * $cantidad;

                // Crear PrestamoProductoDevuelto
                $unidadDerivadaInmutablePrestamo = $productoAlmacenPrestamo->unidadesDerivadas->first();
                PrestamoProductoDevuelto::create([
                    'prestamo_devolucion_id' => $prestamoDevolucion->id,
                    'producto_almacen_prestamo_id' => $productoAlmacenPrestamo->id,
                    'unidad_derivada_inmutable_prestamo_id' => $unidadDerivadaInmutablePrestamo ? $unidadDerivadaInmutablePrestamo->id : null,
                    'cantidad' => $cantidad,
                    'factor' => $factor,
                    'cantidad_fraccion' => $cantidadFraccion,
                ]);

                // Crear ProductoAlmacenIngresoSalida
                $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
                    'ingreso_id' => $ingresoSalida->id,
                    'producto_almacen_id' => $productoAlmacen->id,
                    'costo' => $productoAlmacen->costo,
                ]);

                // Crear o buscar UnidadDerivadaInmutable
                $unidadInmutableName = $unidadDerivadaInmutablePrestamo ? $unidadDerivadaInmutablePrestamo->name : 'UNIDAD';
                $unidadInmutable = UnidadDerivadaInmutable::firstOrCreate(
                    ['name' => $unidadInmutableName],
                    ['estado' => true]
                );

                // Crear UnidadDerivadaInmutableIngresoSalida
                UnidadDerivadaInmutableIngresoSalida::create([
                    'producto_almacen_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                    'unidad_derivada_inmutable_id' => $unidadInmutable->id,
                    'factor' => $factor,
                    'cantidad' => $cantidad,
                    'cantidad_restante' => $cantidad,
                    'lote' => null,
                    'vencimiento' => null,
                ]);

                // Obtener unidad derivada original
                $unidadDerivadaId = $unidadDerivadaInmutablePrestamo ? $unidadDerivadaInmutablePrestamo->unidad_derivada_id : 1;
                $unidadDerivada = UnidadDerivada::find($unidadDerivadaId);

                // Actualizar stock
                $stockAnterior = (float) $productoAlmacen->stock_fraccion;
                $stockNuevo = $esIngreso
                    ? $stockAnterior + $cantidadFraccion
                    : $stockAnterior - $cantidadFraccion;

                // Validar que no sea negativo para salidas
                if (!$esIngreso && $stockNuevo < 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock insuficiente para devolver {$cantidad} unidades del producto",
                    ], 422);
                }

                // Registrar historial
                HistorialUnidadDerivadaInmutableIngresoSalida::create([
                    'unidad_derivada_inmutable_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                ]);

                // Actualizar stock_fraccion
                $productoAlmacen->update([
                    'stock_fraccion' => $stockNuevo,
                ]);

                // Registrar en Kardex
                $kardexService = app(KardexInventarioService::class);
                $productoAlmacenRefresh = ProductoAlmacen::with('producto')->find($productoAlmacen->id);
                $unidadForKardex = (object)[
                    'cantidad' => $cantidad,
                    'factor' => $factor,
                    'unidadDerivadaInmutable' => (object)['name' => $unidadInmutable->name],
                ];

                if ($esIngreso) {
                    $kardexService->registrarIngreso($ingresoSalida, $productoAlmacenRefresh, $unidadForKardex, $productoAlmacen->costo, 3, $stockAnterior);
                } else {
                    $kardexService->registrarSalida($ingresoSalida, $productoAlmacenRefresh, $unidadForKardex, $productoAlmacen->costo, 4, $stockAnterior);
                }

                // Procesar complementario
                ComplementarioStockService::procesarComplementario(
                    $productoAlmacen->id,
                    $unidadDerivadaId,
                    $cantidad,
                    $prestamo->almacen_id,
                    $esIngreso,
                    $ingresoSalida
                );

                $totalDevuelto += $cantidadFraccion;
            }

            // Crear PagoPrestamo para tracking (legacy support)
            $pagoId = 'pag' . Str::random(10);
            $numeroPago = $this->generarNumeroPago($prestamo->id);
            PagoPrestamo::create([
                'id' => $pagoId,
                'prestamo_id' => $prestamo->id,
                'numero_pago' => $numeroPago,
                'monto' => $totalDevuelto,
                'fecha_pago' => $validated['fecha_devolucion'],
                'metodo_pago' => 'Devolución Física',
                'observaciones' => "Devolución {$numeroDevolucion}. " . ($validated['observaciones'] ?? ''),
                'user_id' => auth()->id(),
            ]);

            // Invalidar cache
            app(ProductoCacheService::class)->invalidateProductosAlmacen($prestamo->almacen_id);

            DB::commit();

            // Recargar el préstamo con relaciones actualizadas
            $prestamo->refresh();
            $prestamo->load([
                'cliente',
                'proveedor',
                'user',
                'almacen',
                'productosPorAlmacen.productoAlmacen.producto',
                'productosPorAlmacen.unidadesDerivadas',
                'pagos',
                'devoluciones.ingresoSalida',
                'devoluciones.productosDevueltos',
            ]);

            return response()->json([
                'data' => [
                    'prestamo' => $prestamo,
                    'devolucion' => $prestamoDevolucion,
                    'ingreso_salida' => $ingresoSalida,
                ],
                'message' => 'Devolución registrada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la devolución: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar número de devolución único para un préstamo
     */
    private function generarNumeroDevolucion(string $prestamoId): string
    {
        $count = PrestamoDevolucion::where('prestamo_id', $prestamoId)->count();
        $newNumber = $count + 1;

        $prestamo = Prestamo::find($prestamoId);
        return sprintf('%s-DEV-%03d', $prestamo->numero, $newNumber);
    }
}
