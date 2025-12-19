<?php

namespace App\Http\Controllers;

use App\Models\IngresoSalida;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\UnidadDerivadaInmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class IngresoSalidaController extends Controller
{
    /**
     * Display a listing of ingresos/salidas.
     *
     * GET /api/ingresos-salidas?almacen_id=X&tipo_documento=Ingreso
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'nullable|integer|exists:almacen,id',
            'tipo_documento' => 'nullable|string|in:Ingreso,Salida',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = IngresoSalida::with([
            'almacen:id,name',
            'proveedor:id,razon_social',
            'tipoIngreso:id,name',
            'user:id,name',
            'productosPorAlmacen' => function ($q) {
                $q->with([
                    'productoAlmacen.producto:id,name,cod_producto',
                    'unidadesDerivadas' => function ($uq) {
                        $uq->with([
                            'unidadDerivadaInmutable:id,name',
                            'historial',
                        ]);
                    },
                ]);
            },
        ]);

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $result = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
            'per_page' => $result->perPage(),
            'total' => $result->total(),
        ]);
    }

    /**
     * Store a newly created ingreso/salida.
     *
     * POST /api/ingresos-salidas
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_documento' => 'required|string|in:Ingreso,Salida',
            'almacen_id' => 'required|integer|exists:almacen,id',
            'producto_id' => 'required|integer|exists:producto,id',
            'unidad_derivada_id' => 'required|integer|exists:unidadderivada,id',
            'cantidad' => 'required|numeric|min:0',
            'fecha' => 'nullable|date',
            'tipo_ingreso_id' => 'required|integer|exists:tipoingresosalida,id',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'descripcion' => 'nullable|string',
            'lote' => 'nullable|string',
            'vencimiento' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $tipoDocumento = $validated['tipo_documento'];
            $almacenId = $validated['almacen_id'];
            $productoId = $validated['producto_id'];
            $unidadDerivadaId = $validated['unidad_derivada_id'];
            $cantidad = $validated['cantidad'];

            // PASO 1: Obtener ProductoAlmacen con unidades derivadas
            $productoAlmacen = ProductoAlmacen::where('producto_id', $productoId)
                ->where('almacen_id', $almacenId)
                ->with(['unidadesDerivadas' => function ($q) use ($unidadDerivadaId) {
                    $q->where('unidad_derivada_id', $unidadDerivadaId)
                        ->with('unidadDerivada:id,name');
                }])
                ->firstOrFail();

            $unidadDerivada = $productoAlmacen->unidadesDerivadas->first();
            if (!$unidadDerivada) {
                return response()->json([
                    'message' => 'Unidad derivada no encontrada para este producto',
                ], 404);
            }

            // PASO 2: Calcular cantidad en fracciones
            $esIngreso = $tipoDocumento === 'Ingreso';
            $factor = (float) $unidadDerivada->factor;
            $cantidadFraccion = $factor * $cantidad * ($esIngreso ? 1 : -1);

            // PASO 3: Validar stock si es salida
            if (!$esIngreso && (float) $productoAlmacen->stock_fraccion + $cantidadFraccion < 0) {
                return response()->json([
                    'message' => 'Stock insuficiente para realizar la salida',
                ], 400);
            }

            // PASO 4: Obtener último número de documento
            $ultimoIngreso = IngresoSalida::where('tipo_documento', $tipoDocumento)
                ->orderBy('numero', 'desc')
                ->first();
            $numero = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

            // PASO 5: Obtener serie (hardcoded por ahora, debería venir de empresa)
            $serie = $esIngreso ? 1 : 2;

            // PASO 6: Obtener user_id del usuario autenticado
            $userId = Auth::id();

            // PASO 7: Crear IngresoSalida
            $ingresoSalida = IngresoSalida::create([
                'tipo_documento' => $tipoDocumento,
                'serie' => $serie,
                'numero' => $numero,
                'fecha' => $validated['fecha'] ?? now(),
                'almacen_id' => $almacenId,
                'tipo_ingreso_id' => $validated['tipo_ingreso_id'],
                'proveedor_id' => $validated['proveedor_id'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'user_id' => $userId,
                'estado' => true,
            ]);

            // PASO 8: Crear ProductoAlmacenIngresoSalida
            $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
                'ingreso_id' => $ingresoSalida->id,
                'producto_almacen_id' => $productoAlmacen->id,
                'costo' => $productoAlmacen->costo,
            ]);

            // PASO 9: Crear UnidadDerivadaInmutable si no existe
            $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                ['name' => $unidadDerivada->unidadDerivada->name],
                ['estado' => true]
            );

            // PASO 10: Crear UnidadDerivadaInmutableIngresoSalida
            $unidadDerivadaInmutableIngresoSalida = UnidadDerivadaInmutableIngresoSalida::create([
                'producto_almacen_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                'factor' => $factor,
                'cantidad' => $cantidad,
                'cantidad_restante' => $cantidad,
                'lote' => $validated['lote'] ?? null,
                'vencimiento' => $validated['vencimiento'] ?? null,
            ]);

            // PASO 11: Crear Historial
            $stockAnterior = (float) $productoAlmacen->stock_fraccion;
            $stockNuevo = $stockAnterior + $cantidadFraccion;

            HistorialUnidadDerivadaInmutableIngresoSalida::create([
                'unidad_derivada_inmutable_ingreso_salida_id' => $unidadDerivadaInmutableIngresoSalida->id,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
            ]);

            // PASO 12: Actualizar ProductoAlmacen (stock y costo)
            $nuevoCosto = $productoAlmacen->costo;
            if ($stockAnterior <= 0 && $esIngreso) {
                // Si el stock era 0 o negativo y es un ingreso, usar el costo actual
                $nuevoCosto = $productoAlmacen->costo;
            }

            $productoAlmacen->update([
                'stock_fraccion' => $stockNuevo,
                'costo' => $nuevoCosto,
            ]);

            // PASO 13: Retornar resultado con relaciones
            $result = IngresoSalida::with([
                'almacen:id,name',
                'proveedor:id,razon_social',
                'tipoIngreso:id,name',
                'user:id,name',
                'productosPorAlmacen' => function ($q) {
                    $q->with([
                        'productoAlmacen.producto:id,name,cod_producto',
                        'unidadesDerivadas' => function ($uq) {
                            $uq->with([
                                'unidadDerivadaInmutable:id,name',
                                'historial',
                            ]);
                        },
                    ]);
                },
            ])->findOrFail($ingresoSalida->id);

            return response()->json(['data' => $result], 201);
        }, 5);
    }
}
