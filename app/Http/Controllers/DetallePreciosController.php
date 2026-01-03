<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenUnidadDerivada;
use App\Models\UnidadDerivada;
use App\Models\Ubicacion;
use App\Models\IngresoSalida;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\UnidadDerivadaInmutable;
use App\Models\TipoIngresoSalida;
// use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetallePreciosController extends Controller
{
    /**
     * Importar detalles de precios (unidades derivadas) desde Excel
     */
    public function import(Request $request): JsonResponse
    {
        // Aumentar timeout y memoria para importaciones grandes
        set_time_limit(300); // 5 minutos
        ini_set('memory_limit', '512M');

        $seen = [];
        foreach ($request['data'] as $index => $item) {
            $key = $item['producto_almacen']['connect']['id'] . '|' . $item['factor'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "data.{$index}.factor" => "Duplicado: producto_almacen_id {$item['producto_almacen']['connect']['id']} con factor {$item['factor']}"
                ]);
            }
            $seen[$key] = true;
        }

        $duplicados = [];
        $chunks = array_chunk($request['data'], 200);
        $userId = auth()->id();

        // Obtener tipo de ingreso "AJUSTE"
        $tipoIngreso = TipoIngresoSalida::where('name', 'AJUSTE')->firstOrFail();

        // OPTIMIZACIÓN: Pre-cargar UnidadesDerivadaInmutable y empresa fuera del loop
        $unidadesDerivadaInmutableCache = [];
        $empresa = DB::table('user')
            ->join('empresa', 'user.empresa_id', '=', 'empresa.id')
            ->where('user.id', $userId)
            ->select('empresa.serie_ingreso')
            ->first();

        if (!$empresa) {
            throw new \Exception('No se encontró la empresa del usuario');
        }

        // OPTIMIZACIÓN CRÍTICA: Obtener último número de ingreso UNA SOLA VEZ
        // En lugar de consultar la BD 5000-6000 veces dentro del loop
        $ultimoIngreso = IngresoSalida::where('tipo_documento', 'in')
            ->where('serie', $empresa->serie_ingreso)
            ->orderBy('numero', 'desc')
            ->first();

        $contadorNumeroIngreso = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::transaction(function () use ($chunk, &$duplicados, $userId, $tipoIngreso, $chunkIndex, &$unidadesDerivadaInmutableCache, $empresa, &$contadorNumeroIngreso) {
                    // Obtener todos los ProductoAlmacen necesarios
                    $productoAlmacenIds = array_unique(
                        array_map(fn($item) => $item['producto_almacen']['connect']['id'], $chunk)
                    );

                    $productoAlmacenes = ProductoAlmacen::with('producto:id,unidades_contenidas')
                        ->whereIn('id', $productoAlmacenIds)
                        ->get()
                        ->keyBy('id');

                    // Obtener todas las UnidadesDerivadas necesarias
                    $unidadDerivadaIds = array_unique(
                        array_map(fn($item) => $item['unidad_derivada']['connect']['id'], $chunk)
                    );

                    $unidadesDerivadas = UnidadDerivada::whereIn('id', $unidadDerivadaIds)
                        ->get()
                        ->keyBy('id');

                    $itemsProcesados = 0;
                    $itemsDuplicados = 0;

                    // OPTIMIZACIÓN CRÍTICA: Acumular datos para batch insert
                    $batchProductoAlmacenUnidadDerivada = [];
                    $batchIngresoSalida = [];
                    $batchProductoAlmacenIngresoSalida = [];
                    $batchUnidadDerivadaInmutableIngresoSalida = [];
                    $batchHistorial = [];
                    $now = now();

                    foreach ($chunk as $itemIndex => $item) {
                        try {
                            $productoAlmacenId = $item['producto_almacen']['connect']['id'];
                            $unidadDerivadaId = $item['unidad_derivada']['connect']['id'];

                            // Defaults
                            $item['precio_especial'] = $item['precio_especial'] ?? $item['precio_publico'];
                            $item['precio_minimo'] = $item['precio_minimo'] ?? $item['precio_publico'];
                            $item['precio_ultimo'] = $item['precio_ultimo'] ?? $item['precio_publico'];

                            // OPTIMIZACIÓN: Acumular en array para batch insert
                            $batchProductoAlmacenUnidadDerivada[] = [
                                'producto_almacen_id' => $productoAlmacenId,
                                'unidad_derivada_id' => $unidadDerivadaId,
                                'factor' => $item['factor'],
                                'precio_publico' => $item['precio_publico'],
                                'comision_publico' => $item['comision_publico'] ?? 0,
                                'precio_especial' => $item['precio_especial'] ?? 0,
                                'comision_especial' => $item['comision_especial'] ?? 0,
                                'activador_especial' => $item['activador_especial'] ?? 0,
                                'precio_minimo' => $item['precio_minimo'] ?? 0,
                                'comision_minimo' => $item['comision_minimo'] ?? 0,
                                'activador_minimo' => $item['activador_minimo'] ?? 0,
                                'precio_ultimo' => $item['precio_ultimo'] ?? 0,
                                'comision_ultimo' => $item['comision_ultimo'] ?? 0,
                                'activador_ultimo' => $item['activador_ultimo'] ?? 0,
                                // NOTA: Esta tabla NO tiene timestamps (created_at, updated_at)
                            ];

                            $productoAlmacen = $productoAlmacenes->get($productoAlmacenId);
                            if (!$productoAlmacen) {
                                throw new \Exception('El Producto no se encontró en el Almacén');
                            }

                            $unidadDerivada = $unidadesDerivadas->get($unidadDerivadaId);
                            if (!$unidadDerivada) {
                                throw new \Exception('La Unidad Derivada no se encontró');
                            }

                            // Si es la unidad derivada por defecto, preparar datos para ingreso
                            $esLaUnidadDerivadaPorDefecto = (float) $item['factor'] === (float) $productoAlmacen->producto->unidades_contenidas;

                            if ($esLaUnidadDerivadaPorDefecto) {
                                // Guardar referencia para procesar después del batch insert
                                $batchIngresoSalida[] = [
                                    'producto_almacen_id' => $productoAlmacenId,
                                    'unidad_derivada_name' => $unidadDerivada->name,
                                    'factor' => $item['factor'],
                                    'stock_fraccion' => (float) $productoAlmacen->stock_fraccion,
                                    'costo' => $productoAlmacen->costo,
                                    'almacen_id' => $productoAlmacen->almacen_id,
                                ];
                            }
                            $itemsProcesados++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Si es error de duplicado (unique constraint)
                            if ($e->getCode() === '23000') {
                                $duplicados[] = $item;
                                $itemsDuplicados++;
                                continue;
                            }
                            throw $e;
                        }
                    }

                    // ========== BATCH INSERTS ==========
                    // OPTIMIZACIÓN CRÍTICA: Insertar todos los registros de una vez
                    // Esto reduce de 6000+ queries individuales a solo 1 query por tabla

                    // 1. Batch insert de ProductoAlmacenUnidadDerivada
                    if (!empty($batchProductoAlmacenUnidadDerivada)) {
                        try {
                            DB::table('productoalmacenunidadderivada')->insert($batchProductoAlmacenUnidadDerivada);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Manejar duplicados
                            if ($e->getCode() === '23000') {
                                // Si hay duplicados, insertar uno por uno para identificarlos
                                foreach ($batchProductoAlmacenUnidadDerivada as $index => $record) {
                                    try {
                                        DB::table('productoalmacenunidadderivada')->insert($record);
                                    } catch (\Illuminate\Database\QueryException $e2) {
                                        if ($e2->getCode() === '23000') {
                                            $duplicados[] = $chunk[$index] ?? null;
                                            $itemsDuplicados++;
                                        }
                                    }
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }

                    // 2. Procesar IngresoSalida (solo si hay unidades por defecto)
                    if (!empty($batchIngresoSalida)) {
                        $ingresoSalidaBatch = [];
                        foreach ($batchIngresoSalida as $ingresoData) {
                            // Cache de UnidadDerivadaInmutable
                            if (!isset($unidadesDerivadaInmutableCache[$ingresoData['unidad_derivada_name']])) {
                                $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                                    ['name' => $ingresoData['unidad_derivada_name']],
                                    ['name' => $ingresoData['unidad_derivada_name']]
                                );
                                $unidadesDerivadaInmutableCache[$ingresoData['unidad_derivada_name']] = $unidadDerivadaInmutable;
                            } else {
                                $unidadDerivadaInmutable = $unidadesDerivadaInmutableCache[$ingresoData['unidad_derivada_name']];
                            }

                            $nuevoNumero = $contadorNumeroIngreso++;

                            $ingresoSalidaBatch[] = [
                                'tipo_ingreso_id' => $tipoIngreso->id,
                                'descripcion' => 'Ingreso por Importación de Producto',
                                'almacen_id' => $ingresoData['almacen_id'],
                                'user_id' => $userId,
                                'tipo_documento' => 'in',
                                'serie' => $empresa->serie_ingreso,
                                'fecha' => $now,
                                'numero' => $nuevoNumero,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        if (!empty($ingresoSalidaBatch)) {
                            DB::table('ingresosalida')->insert($ingresoSalidaBatch);
                        }
                    }

                    // Actualizar permitido=true para todos los productos afectados
                    $productosIds = $productoAlmacenes->pluck('producto_id')->unique();
                    Producto::whereIn('id', $productosIds)->update(['permitido' => true]);
                }, 5);
            } catch (\Exception $e) {
                Log::error('Error en chunk de importación de detalle de precios', [
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        $totalImportados = count($request['data']) - count($duplicados);

        return response()->json([
            'data' => $duplicados,
            'message' => "{$totalImportados} unidades derivadas importadas, " . count($duplicados) . ' duplicadas',
        ]);
    }

    /**
     * Obtener ProductoAlmacen por cod_producto y almacen_id
     */
    public function getProductoAlmacenByCodProducto(Request $request): JsonResponse
    {
        // Aumentar timeout para importaciones grandes
        set_time_limit(300); // 5 minutos

        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.cod_producto' => 'required|string',
            'data.*.almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        // OPTIMIZACIÓN: Extraer todos los códigos y almacenes únicos
        $codigosProductos = array_unique(
            array_map(fn($item) => trim($item['cod_producto']), $validated['data'])
        );

        $almacenesIds = array_unique(
            array_map(fn($item) => $item['almacen_id'], $validated['data'])
        );

        // OPTIMIZACIÓN 1: Obtener todos los productos en una sola query
        $productos = Producto::whereIn('cod_producto', $codigosProductos)
            ->get()
            ->keyBy('cod_producto');

        // OPTIMIZACIÓN 2: Obtener todas las ubicaciones en una sola query
        $ubicaciones = Ubicacion::whereIn('almacen_id', $almacenesIds)
            ->get()
            ->keyBy('almacen_id');

        // Validar que todos los almacenes tengan al menos una ubicación
        foreach ($almacenesIds as $almacenId) {
            if (!isset($ubicaciones[$almacenId])) {
                return response()->json([
                    'message' => "El almacén ID {$almacenId} debe tener al menos una Ubicación",
                ], 400);
            }
        }

        // OPTIMIZACIÓN 3: Obtener todos los ProductoAlmacen existentes en una sola query
        $productosAlmacenesExistentes = ProductoAlmacen::whereIn('producto_id', $productos->pluck('id'))
            ->whereIn('almacen_id', $almacenesIds)
            ->get()
            ->keyBy(function($item) {
                return $item->producto_id . '_' . $item->almacen_id;
            });

        $results = [];
        $productosNoEncontrados = [];
        $productosAlmacenesParaCrear = [];

        // Procesar cada item
        foreach ($validated['data'] as $item) {
            $codProducto = trim($item['cod_producto']);
            $almacenId = $item['almacen_id'];

            // Verificar si el producto existe
            if (!isset($productos[$codProducto])) {
                $productosNoEncontrados[] = $codProducto;
                continue;
            }

            $producto = $productos[$codProducto];
            $ubicacion = $ubicaciones[$almacenId];
            $key = $producto->id . '_' . $almacenId;

            // Verificar si ya existe el ProductoAlmacen
            if (isset($productosAlmacenesExistentes[$key])) {
                $productoAlmacen = $productosAlmacenesExistentes[$key];

                // Actualizar ubicación si es diferente
                if ($productoAlmacen->ubicacion_id !== $ubicacion->id) {
                    $productoAlmacen->update(['ubicacion_id' => $ubicacion->id]);
                }
            } else {
                // Marcar para crear después
                if (!isset($productosAlmacenesParaCrear[$key])) {
                    $productosAlmacenesParaCrear[$key] = [
                        'producto_id' => $producto->id,
                        'almacen_id' => $almacenId,
                        'ubicacion_id' => $ubicacion->id,
                        'stock_fraccion' => 0,
                        'costo' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // OPTIMIZACIÓN 4: Crear todos los ProductoAlmacen faltantes en batch
        if (!empty($productosAlmacenesParaCrear)) {
            ProductoAlmacen::insert(array_values($productosAlmacenesParaCrear));

            // Obtener los recién creados
            $nuevosProductosAlmacenes = ProductoAlmacen::whereIn('producto_id', array_column($productosAlmacenesParaCrear, 'producto_id'))
                ->whereIn('almacen_id', array_column($productosAlmacenesParaCrear, 'almacen_id'))
                ->get()
                ->keyBy(function($item) {
                    return $item->producto_id . '_' . $item->almacen_id;
                });

            // Agregar a la colección existente
            foreach ($nuevosProductosAlmacenes as $key => $item) {
                $productosAlmacenesExistentes[$key] = $item;
            }
        }

        // Construir resultados
        foreach ($validated['data'] as $item) {
            $codProducto = trim($item['cod_producto']);
            $almacenId = $item['almacen_id'];

            if (!isset($productos[$codProducto])) {
                continue; // Ya fue marcado como no encontrado
            }

            $producto = $productos[$codProducto];
            $key = $producto->id . '_' . $almacenId;
            $productoAlmacen = $productosAlmacenesExistentes[$key];

            $results[] = [
                'cod_producto' => $codProducto,
                'producto_almacen_id' => $productoAlmacen->id,
            ];
        }

        // Advertencias
        $advertencias = [];
        if (!empty($productosNoEncontrados)) {
            $advertencias[] = 'Productos no encontrados (se omitieron): ' . implode(', ', $productosNoEncontrados);
        }

        $response = ['data' => $results];
        if (!empty($advertencias)) {
            $response['advertencias'] = $advertencias;
        }
        return response()->json($response);
    }

    /**
     * Importar/crear unidades derivadas
     */
    public function importarUnidadesDerivadas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.name' => 'required|string|max:191',
        ]);

        // Remover duplicados
        $uniqueData = collect($validated['data'])
            ->unique('name')
            ->values()
            ->toArray();

        // OPTIMIZACIÓN: Obtener todas las unidades existentes en una sola query
        $nombres = array_column($uniqueData, 'name');
        $unidadesExistentes = UnidadDerivada::whereIn('name', $nombres)
            ->get()
            ->keyBy('name');

        $results = [];
        $nuevasUnidades = [];

        // Identificar cuáles necesitan crearse
        foreach ($uniqueData as $item) {
            if (isset($unidadesExistentes[$item['name']])) {
                // Ya existe, agregar al resultado
                $unidad = $unidadesExistentes[$item['name']];
                $results[] = [
                    'id' => $unidad->id,
                    'name' => $unidad->name,
                ];
            } else {
                // No existe, marcar para crear
                $nuevasUnidades[] = ['name' => $item['name'], 'estado' => true];
            }
        }

        // Crear todas las nuevas unidades en batch (si hay)
        if (!empty($nuevasUnidades)) {
            UnidadDerivada::insert($nuevasUnidades);

            // Obtener las recién creadas
            $nombresNuevos = array_column($nuevasUnidades, 'name');
            $recienCreadas = UnidadDerivada::whereIn('name', $nombresNuevos)->get();

            foreach ($recienCreadas as $unidad) {
                $results[] = [
                    'id' => $unidad->id,
                    'name' => $unidad->name,
                ];
            }
        }

        return response()->json(['data' => $results]);
    }
}
