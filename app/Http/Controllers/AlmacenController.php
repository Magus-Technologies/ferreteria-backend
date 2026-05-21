<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenUnidadDerivada;
use App\Services\Cache\ProductoCacheService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlmacenController extends Controller
{
    /**
     * Display a listing of almacenes.
     *
     * Required permission: ALMACEN_LISTADO
     */
    public function index(Request $request): JsonResponse
    {
        $query = Almacen::orderBy('name', 'asc');

        // Por defecto solo activos, pero si piden todos (para gestión)
        if (!$request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        $almacenes = $query->get();

        return response()->json([
            'data' => $almacenes,
        ]);
    }

    /**
     * Devuelve el estado de replicación de cada almacén destino respecto al origen.
     * GET /api/almacenes/{id}/estado-replicacion
     */
    public function estadoReplicacion(int $id): JsonResponse
    {
        $totalOrigen = ProductoAlmacen::where('almacen_id', $id)->count();

        $almacenes = Almacen::where('id', '!=', $id)
            ->get()
            ->map(function ($almacen) use ($id, $totalOrigen) {
                $totalDestino = ProductoAlmacen::where('almacen_id', $almacen->id)->count();
                return [
                    'id'              => $almacen->id,
                    'name'            => $almacen->name,
                    'total_productos' => $totalDestino,
                    'replicado'       => $totalDestino >= $totalOrigen && $totalOrigen > 0,
                ];
            });

        return response()->json([
            'data' => [
                'total_origen' => $totalOrigen,
                'almacenes'    => $almacenes,
            ],
        ]);
    }

    /**
     * Toggle activo status of an almacen.
     */
    public function toggleActivo(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);
        $almacen->update(['activo' => !$almacen->activo]);

        return response()->json([
            'data' => $almacen,
        ]);
    }

    /**
     * Store a newly created almacen.
     *
     * Required permission: ALMACEN_CREATE
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:almacen',
            'direccion' => 'nullable|string|max:500',
        ]);

        try {
            $almacen = Almacen::create($validated);

            return response()->json([
                'data' => $almacen,
            ], 201);
        } catch (QueryException $e) {
            // Manejo de error de duplicado (código 23000 para MySQL)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Ya existe un almacén con ese nombre',
                    'errors' => [
                        'name' => ['El nombre ya está en uso'],
                    ],
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        return response()->json([
            'data' => $almacen,
        ]);
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:almacen,name,' . $id,
            'direccion' => 'nullable|string|max:500',
            'empresa_dir_slot' => 'nullable|in:D1,D2,D3,D4',
        ]);

        try {
            $almacen->update($validated);

            return response()->json([
                'data' => $almacen,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Ya existe un almacén con ese nombre',
                    'errors' => [
                        'name' => ['El nombre ya está en uso'],
                    ],
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Replica todos los productos del almacén origen a los demás almacenes activos.
     * Crea ProductoAlmacen con stock 0 y copia todas las unidades derivadas (precios).
     * No sobreescribe registros que ya existen.
     */
    public function replicarProductos(Request $request, int $id): JsonResponse
    {
        Almacen::findOrFail($id);

        $productosOrigen = ProductoAlmacen::where('almacen_id', $id)
            ->with('unidadesDerivadas')
            ->get();

        $destinoIds = $request->input('almacenes_destino', []);

        $query = Almacen::where('id', '!=', $id);
        if (!empty($destinoIds)) {
            $query->whereIn('id', $destinoIds);
        }
        $almacenesDestino = $query->get();

        if ($almacenesDestino->isEmpty()) {
            return response()->json([
                'message' => 'No hay otros almacenes activos para replicar.',
                'data' => ['creados' => 0, 'ya_existian' => 0, 'almacenes_actualizados' => 0],
            ]);
        }

        $creados = 0;
        $yaExistian = 0;

        DB::transaction(function () use ($productosOrigen, $almacenesDestino, &$creados, &$yaExistian) {
            foreach ($almacenesDestino as $almacenDestino) {
                foreach ($productosOrigen as $paOrigen) {
                    $existe = ProductoAlmacen::where('producto_id', $paOrigen->producto_id)
                        ->where('almacen_id', $almacenDestino->id)
                        ->exists();

                    if ($existe) {
                        $yaExistian++;
                        continue;
                    }

                    $paDestino = ProductoAlmacen::create([
                        'producto_id'          => $paOrigen->producto_id,
                        'almacen_id'           => $almacenDestino->id,
                        'stock_fraccion'       => 0,
                        'costo'                => $paOrigen->costo,
                        'costo_anterior'       => $paOrigen->costo_anterior,
                        'costo_actual'         => $paOrigen->costo_actual,
                        'stock_costo_anterior' => 0,
                        'stock_costo_actual'   => 0,
                        'ubicacion_id'         => null,
                    ]);

                    foreach ($paOrigen->unidadesDerivadas as $ud) {
                        ProductoAlmacenUnidadDerivada::create([
                            'producto_almacen_id'              => $paDestino->id,
                            'unidad_derivada_id'               => $ud->unidad_derivada_id,
                            'factor'                           => $ud->factor,
                            'orden'                            => $ud->orden,
                            'peso'                             => $ud->peso,
                            'precio_publico'                   => $ud->precio_publico,
                            'comision_publico'                 => $ud->comision_publico,
                            'precio_especial'                  => $ud->precio_especial,
                            'comision_especial'                => $ud->comision_especial,
                            'activador_especial'               => $ud->activador_especial,
                            'precio_minimo'                    => $ud->precio_minimo,
                            'comision_minimo'                  => $ud->comision_minimo,
                            'activador_minimo'                 => $ud->activador_minimo,
                            'precio_ultimo'                    => $ud->precio_ultimo,
                            'comision_ultimo'                  => $ud->comision_ultimo,
                            'activador_ultimo'                 => $ud->activador_ultimo,
                            'producto_complementario_id'       => $ud->producto_complementario_id,
                            'producto_complementario_cantidad' => $ud->producto_complementario_cantidad,
                        ]);
                    }

                    $creados++;
                }

                app(ProductoCacheService::class)->invalidateProductosAlmacen($almacenDestino->id);
            }
        });

        $msg = "Replicación completada: {$creados} productos creados en {$almacenesDestino->count()} almacén(es). {$yaExistian} ya existían.";

        return response()->json([
            'message' => $msg,
            'data' => [
                'creados'               => $creados,
                'ya_existian'           => $yaExistian,
                'almacenes_actualizados' => $almacenesDestino->count(),
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $almacen = Almacen::findOrFail($id);

        // Verificar si tiene productos asociados
        if ($almacen->productosEnAlmacen()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el almacén porque tiene productos asociados',
            ], 422);
        }

        $almacen->delete();

        return response()->json([
            'message' => 'Almacén eliminado exitosamente',
        ]);
    }
}
