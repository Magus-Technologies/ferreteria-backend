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
        $seen = [];
        foreach ($request['data'] as $index => $item) {
            $key = $item['producto_almacen']['connect']['id'] . '|' . $item['factor'];
            if (isset($seen[$key])) {
                error_log("Duplicado encontrado en índice {$index}: {$key}");
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

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::transaction(function () use ($chunk, &$duplicados, $userId, $tipoIngreso, $chunkIndex) {
                    // Obtener todos los ProductoAlmacen necesarios
                    $productoAlmacenIds = array_unique(
                        array_map(fn($item) => $item['producto_almacen']['connect']['id'], $chunk)
                    );
                    error_log("ProductoAlmacen IDs únicos en chunk: " . implode(', ', $productoAlmacenIds));

                    $productoAlmacenes = ProductoAlmacen::with('producto:id,unidades_contenidas')
                        ->whereIn('id', $productoAlmacenIds)
                        ->get()
                        ->keyBy('id');
                    error_log("ProductoAlmacen encontrados: " . $productoAlmacenes->count());

                    // Obtener todas las UnidadesDerivadas necesarias
                    $unidadDerivadaIds = array_unique(
                        array_map(fn($item) => $item['unidad_derivada']['connect']['id'], $chunk)
                    );
                    error_log("UnidadDerivada IDs únicos en chunk: " . implode(', ', $unidadDerivadaIds));

                    $unidadesDerivadas = UnidadDerivada::whereIn('id', $unidadDerivadaIds)
                        ->get()
                        ->keyBy('id');
                    error_log("UnidadesDerivadas encontradas: " . $unidadesDerivadas->count());

                    $itemsProcesados = 0;
                    $itemsDuplicados = 0;

                    foreach ($chunk as $itemIndex => $item) {
                        try {
                            $productoAlmacenId = $item['producto_almacen']['connect']['id'];
                            $unidadDerivadaId = $item['unidad_derivada']['connect']['id'];

                            error_log("  Item " . ($itemIndex + 1) . ": ProductoAlmacen={$productoAlmacenId}, UnidadDerivada={$unidadDerivadaId}, Factor={$item['factor']}, Precio={$item['precio_publico']}");

                            // Defaults
                            $item['precio_especial'] = $item['precio_especial'] ?? $item['precio_publico'];
                            $item['precio_minimo'] = $item['precio_minimo'] ?? $item['precio_publico'];
                            $item['precio_ultimo'] = $item['precio_ultimo'] ?? $item['precio_publico'];

                            // Crear ProductoAlmacenUnidadDerivada
                            $productoAlmacenUnidadDerivada = ProductoAlmacenUnidadDerivada::create([
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
                            ]);
                            error_log("    ProductoAlmacenUnidadDerivada creado con ID: " . $productoAlmacenUnidadDerivada->id);

                            $productoAlmacen = $productoAlmacenes->get($productoAlmacenId);
                            if (!$productoAlmacen) {
                                error_log("    ERROR: ProductoAlmacen ID {$productoAlmacenId} no encontrado");
                                throw new \Exception('El Producto no se encontró en el Almacén');
                            }

                            $unidadDerivada = $unidadesDerivadas->get($unidadDerivadaId);
                            if (!$unidadDerivada) {
                                error_log("    ERROR: UnidadDerivada ID {$unidadDerivadaId} no encontrada");
                                throw new \Exception('La Unidad Derivada no se encontró');
                            }

                            // Si es la unidad derivada por defecto (factor == unidades_contenidas), crear ingreso
                            $esLaUnidadDerivadaPorDefecto = (float) $item['factor'] === (float) $productoAlmacen->producto->unidades_contenidas;
                            error_log("    Factor: {$item['factor']}, Unidades contenidas: {$productoAlmacen->producto->unidades_contenidas}, Es unidad por defecto: " . ($esLaUnidadDerivadaPorDefecto ? 'SI' : 'NO'));

                            if ($esLaUnidadDerivadaPorDefecto) {
                                error_log("    Creando ingreso inicial para unidad por defecto...");
                                // Obtener empresa del usuario
                                $empresa = DB::table('user')
                                    ->join('empresa', 'user.empresa_id', '=', 'empresa.id')
                                    ->where('user.id', $userId)
                                    ->select('empresa.serie_ingreso')
                                    ->first();

                                if (!$empresa) {
                                    throw new \Exception('No se encontró la empresa del usuario');
                                }

                                // Obtener último número de ingreso
                                $ultimoIngreso = IngresoSalida::where('tipo_documento', 'in')
                                    ->where('serie', $empresa->serie_ingreso)
                                    ->orderBy('numero', 'desc')
                                    ->first();

                                $nuevoNumero = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

                                // Crear UnidadDerivadaInmutable si no existe
                                $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                                    ['name' => $unidadDerivada->name],
                                    ['name' => $unidadDerivada->name]
                                );

                                // Calcular cantidad
                                $cantidadFraccion = (float) $productoAlmacen->stock_fraccion;
                                $cantidad = $cantidadFraccion / (float) $productoAlmacenUnidadDerivada->factor;

                                // Crear IngresoSalida
                                $ingresoSalida = IngresoSalida::create([
                                    'tipo_ingreso_id' => $tipoIngreso->id,
                                    'descripcion' => 'Ingreso por Importación de Producto',
                                    'almacen_id' => $productoAlmacen->almacen_id,
                                    'user_id' => $userId,
                                    'tipo_documento' => 'in',
                                    'serie' => $empresa->serie_ingreso,
                                    'fecha' => now(),
                                    'numero' => $nuevoNumero,
                                ]);

                                // Crear ProductoAlmacenIngresoSalida
                                $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
                                    'ingreso_id' => $ingresoSalida->id,
                                    'costo' => $productoAlmacen->costo,
                                    'producto_almacen_id' => $productoAlmacen->id,
                                ]);

                                // Crear UnidadDerivadaInmutableIngresoSalida
                                $unidadDerivadaInmutableIngresoSalida = UnidadDerivadaInmutableIngresoSalida::create([
                                    'unidad_derivada_inmutable_id' => $unidadDerivadaInmutable->id,
                                    'producto_almacen_ingreso_salida_id' => $productoAlmacenIngresoSalida->id,
                                    'factor' => $productoAlmacenUnidadDerivada->factor,
                                    'cantidad' => $cantidad,
                                    'cantidad_restante' => $cantidad,
                                ]);

                                // Crear Historial
                                HistorialUnidadDerivadaInmutableIngresoSalida::create([
                                    'unidad_derivada_inmutable_ingreso_salida_id' => $unidadDerivadaInmutableIngresoSalida->id,
                                    'stock_anterior' => 0,
                                    'stock_nuevo' => $cantidadFraccion,
                                ]);
                            }
                            $itemsProcesados++;
                            error_log("    Item procesado exitosamente!");
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Si es error de duplicado (unique constraint)
                            if ($e->getCode() === '23000') {
                                error_log("    DUPLICADO: " . $e->getMessage());
                                $duplicados[] = $item;
                                $itemsDuplicados++;
                                continue;
                            }
                            error_log("    ERROR DE QUERY: " . $e->getMessage());
                            throw $e;
                        } catch (\Exception $e) {
                            error_log("    ERROR GENERAL: " . $e->getMessage());
                            throw $e;
                        }
                    }

                    error_log("Chunk procesado: {$itemsProcesados} items exitosos, {$itemsDuplicados} duplicados");

                    // Actualizar permitido=true para todos los productos afectados
                    $productosIds = $productoAlmacenes->pluck('producto_id')->unique();
                    Producto::whereIn('id', $productosIds)->update(['permitido' => true]);
                    error_log("Actualizado permitido=true para " . $productosIds->count() . " productos");
                }, 5);
            } catch (\Exception $e) {
                error_log("ERROR EN CHUNK {$chunkIndex}: " . $e->getMessage());
                error_log("Trace: " . $e->getTraceAsString());
                Log::error('Error en chunk de importación de detalle de precios', [
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.cod_producto' => 'required|string',
            'data.*.almacen_id' => 'required|integer|exists:almacen,id',
        ]);

        $results = [];
        $productosNoEncontrados = [];

        foreach ($validated['data'] as $item) {
            $codProducto = $item['cod_producto'];
            $almacenId = $item['almacen_id'];

            // Buscar producto
            $producto = Producto::where('cod_producto', $codProducto)->first();

            if (!$producto) {
                $productosNoEncontrados[] = $codProducto;
                continue;
            }

            // Buscar ubicación del almacén
            $ubicacion = Ubicacion::where('almacen_id', $almacenId)->first();

            if (!$ubicacion) {
                return response()->json([
                    'message' => 'Este Almacén debe tener al menos una Ubicación',
                ], 400);
            }

            // Crear o encontrar ProductoAlmacen
            $productoAlmacen = ProductoAlmacen::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'almacen_id' => $almacenId,
                ],
                [
                    'ubicacion_id' => $ubicacion->id,
                ]
            );

            $results[] = [
                'cod_producto' => $codProducto,
                'producto_almacen_id' => $productoAlmacen->id,
            ];
        }

        // Verificar si hay productos no encontrados
        // Verificar si hay productos no encontrados (solo advertencia, no error)
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

        $results = [];

        foreach ($uniqueData as $item) {
            $unidadDerivada = UnidadDerivada::firstOrCreate(
                ['name' => $item['name']],
                ['name' => $item['name'], 'estado' => true]
            );

            $results[] = [
                'id' => $unidadDerivada->id,
                'name' => $unidadDerivada->name,
            ];
        }

        $response = ['data' => $results];
        if (!empty($advertencias)) {
            $response['advertencias'] = $advertencias;
        }
        return response()->json($response);
    }
}