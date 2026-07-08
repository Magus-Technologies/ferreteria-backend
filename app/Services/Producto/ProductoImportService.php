<?php

namespace App\Services\Producto;

use App\Contracts\ProductoImportServiceInterface;
use App\Jobs\Producto\ImportProductosJob;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductoImportService implements ProductoImportServiceInterface
{
    /**
     * Import products from Excel data in batches
     *
     * @param array $data Array of product data from Excel
     * @return JsonResponse
     */
    public function importFromExcel(array $data): JsonResponse
    {
        // CRÍTICO: Desactivar timeout ANTES de cualquier procesamiento
        @set_time_limit(0); // Ilimitado
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1G');
        
        try {
            $totalProductos = count($data);


            // OPTIMIZACIÓN: Reducir validación - solo validar primeros 5 productos
            // La validación completa causa timeout con muchos productos
            $sampleData = array_slice($data, 0, min(5, count($data)));
            $validation = $this->validateImportData($sampleData);
            if (!$validation["is_valid"]) {
                return response()->json(
                    [
                        "error" => "Error de validación en los datos",
                        "validation_errors" => $validation["errors"],
                    ],
                    422,
                );
            }

            // Step 2: Generate unique import ID
            $importId = ImportProductosJob::generateImportId();

            // Step 3: Get current user
            $user = auth()->user();
            if (!$user) {
                return response()->json(
                    [
                        "error" => "Usuario no autenticado",
                    ],
                    401,
                );
            }

            // CAMBIO: Ejecutar SÍNCRONAMENTE en lugar de dispatch
            // Esto evita problemas con workers que no están corriendo
            
            // Procesar directamente aquí (convertir user_id a int)
            $result = $this->processImportSync($data, $importId, (int) $user->id);


            return response()->json(
                [
                    "message" => "Importación completada exitosamente",
                    "data" => [
                        "total" => $totalProductos,
                        "imported" => $result['imported'],
                        "duplicates" => $result['duplicates'],
                        "errors" => $result['errors'],
                        "duration_seconds" => $result['duration'],
                    ],
                ],
                200,
            );
        } catch (\Exception $e) {
            Log::error("Critical error starting import process", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    "error" =>
                        "Error crítico al iniciar la importación: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Get import progress status
     *
     * @param string $importId Import job ID
     * @return JsonResponse
     */
    public function getImportProgress(string $importId): JsonResponse
    {
        try {
            $progress = Cache::get("import_progress_{$importId}");

            if (!$progress) {
                return response()->json(
                    [
                        "error" => "Import ID no encontrado o expirado",
                    ],
                    404,
                );
            }

            // Also check for final results if import is completed
            if ($progress["status"] === "completed") {
                $results = Cache::get("import_results_{$importId}");
                if ($results) {
                    $progress["results"] = $results;
                }
            }

            // Check for error details if import failed
            if ($progress["status"] === "failed") {
                $errorDetails = Cache::get("import_error_{$importId}");
                if ($errorDetails) {
                    $progress["error_details"] = $errorDetails;
                }
            }

            return response()->json([
                "data" => $progress,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Error obteniendo progreso de importación: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Cancel an ongoing import process
     *
     * @param string $importId Import job ID
     * @return JsonResponse
     */
    public function cancelImport(string $importId): JsonResponse
    {
        try {
            // Set cancellation flag in cache
            Cache::put("import_cancel_{$importId}", true, 3600); // 1 hour

            // Update progress status
            $progress = Cache::get("import_progress_{$importId}");
            if ($progress) {
                $progress["status"] = "cancelling";
                $progress["cancellation_requested_at"] = now()->toISOString();
                Cache::put("import_progress_{$importId}", $progress, 3600);

            }

            return response()->json([
                "message" =>
                    "Cancelación de importación solicitada. El proceso se detendrá en el siguiente lote.",
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Error cancelando importación: " . $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Validate Excel data before importing
     *
     * @param array $data Array of product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateImportData(array $data): array
    {
        $errors = [];
        $warnings = [];

        if (empty($data)) {
            $errors[] = "No hay datos para importar";
            return [
                "is_valid" => false,
                "errors" => $errors,
                "warnings" => $warnings,
            ];
        }

        if (count($data) > 1000) {
            $warnings[] =
                "Importación de más de 1000 productos puede ser lenta";
        }

        $requiredFields = ["name", "name_ticket", "unidades_contenidas"];
        $sampleSize = min(10, count($data));

        for ($i = 0; $i < $sampleSize; $i++) {
            $item = $data[$i];
            $itemIndex = $i + 1;

            foreach ($requiredFields as $field) {
                if (!isset($item[$field]) || empty($item[$field])) {
                    $errors[] = "Campo requerido '{$field}' faltante en ítem {$itemIndex}";
                }
            }

            // Validate data types
            if (
                isset($item["unidades_contenidas"]) &&
                !is_numeric($item["unidades_contenidas"])
            ) {
                $errors[] = "Campo 'unidades_contenidas' debe ser numérico en ítem {$itemIndex}";
            }

            if (isset($item["stock_min"]) && !is_numeric($item["stock_min"])) {
                $errors[] = "Campo 'stock_min' debe ser numérico en ítem {$itemIndex}";
            }

            // Validate product structure for Prisma format
            if (
                isset($item["producto_en_almacenes"]) &&
                !isset($item["producto_en_almacenes"]["create"])
            ) {
                $errors[] = "Estructura 'producto_en_almacenes.create' requerida en ítem {$itemIndex}";
            }
        }

        if (count($errors) > 20) {
            $errors = array_slice($errors, 0, 20);
            $errors[] = "... y más errores encontrados";
        }

        return [
            "is_valid" => empty($errors),
            "errors" => $errors,
            "warnings" => $warnings,
        ];
    }

    /**
     * Pre-load and cache catalog data for import optimization
     * OPTIMIZADO: Usar INSERT ON DUPLICATE para ser más rápido
     *
     * @param array $data Array of product data to analyze
     * @return array Cached catalog data (categories, brands, units)
     */
    public function prepareCatalogCache(array $data): array
    {
        $cache = [
            "categorias" => [],
            "marcas" => [],
            "unidades_medida" => [],
        ];

        // Extract unique catalog names from data (más rápido con array_column)
        $categoriasNombres = [];
        $marcasNombres = [];
        $unidadesMedidaNombres = [];

        foreach ($data as $item) {
            // Categories
            if (isset($item["categoria"]["connectOrCreate"]["where"]["name"])) {
                $categoriasNombres[] =
                    $item["categoria"]["connectOrCreate"]["where"]["name"];
            }
            // Brands
            if (isset($item["marca"]["connectOrCreate"]["where"]["name"])) {
                $marcasNombres[] =
                    $item["marca"]["connectOrCreate"]["where"]["name"];
            }
            // Units of measure
            if (
                isset(
                    $item["unidad_medida"]["connectOrCreate"]["where"]["name"],
                )
            ) {
                $unidadesMedidaNombres[] =
                    $item["unidad_medida"]["connectOrCreate"]["where"]["name"];
            }
        }

        // Hacer únicos antes de consultar
        $categoriasNombres = array_unique($categoriasNombres);
        $marcasNombres = array_unique($marcasNombres);
        $unidadesMedidaNombres = array_unique($unidadesMedidaNombres);

        // OPTIMIZACIÓN: Cargar TODOS los catálogos en UNA SOLA QUERY por tipo
        // Esto es MUCHO más rápido que crear uno por uno
        
        // Categorías
        if (!empty($categoriasNombres)) {
            $categoriasExistentes = Categoria::whereIn("name", $categoriasNombres)
                ->pluck('id', 'name')
                ->toArray();
            
            foreach ($categoriasNombres as $nombre) {
                if (isset($categoriasExistentes[$nombre])) {
                    $cache["categorias"][$nombre] = $categoriasExistentes[$nombre];
                } else {
                    // Crear solo si no existe
                    $nueva = Categoria::firstOrCreate(['name' => $nombre]);
                    $cache["categorias"][$nombre] = $nueva->id;
                }
            }
        }

        // Marcas
        if (!empty($marcasNombres)) {
            $marcasExistentes = Marca::whereIn("name", $marcasNombres)
                ->pluck('id', 'name')
                ->toArray();
            
            foreach ($marcasNombres as $nombre) {
                if (isset($marcasExistentes[$nombre])) {
                    $cache["marcas"][$nombre] = $marcasExistentes[$nombre];
                } else {
                    $nueva = Marca::firstOrCreate(['name' => $nombre, 'estado' => true]);
                    $cache["marcas"][$nombre] = $nueva->id;
                }
            }
        }

        // Unidades de medida
        if (!empty($unidadesMedidaNombres)) {
            $unidadesExistentes = UnidadMedida::whereIn("name", $unidadesMedidaNombres)
                ->pluck('id', 'name')
                ->toArray();
            
            foreach ($unidadesMedidaNombres as $nombre) {
                if (isset($unidadesExistentes[$nombre])) {
                    $cache["unidades_medida"][$nombre] = $unidadesExistentes[$nombre];
                } else {
                    $nueva = UnidadMedida::firstOrCreate(['name' => $nombre, 'estado' => true]);
                    $cache["unidades_medida"][$nombre] = $nueva->id;
                }
            }
        }


        return $cache;
    }

    /**
     * Import a single product using cached catalog data
     *
     * @param array $item Product data
     * @param array $catalogCache Cached catalog data
     * @return array Result of import operation
     */
    private function importSingleProduct(
        array $item,
        array $catalogCache,
    ): array {
        try {
            $productoEnAlmacenesCreate =
                $item["producto_en_almacenes"]["create"];
            unset($item["producto_en_almacenes"]);

            // Adjust cost by dividing by unidades_contenidas
            $costoAjustado =
                $productoEnAlmacenesCreate["costo"] /
                $item["unidades_contenidas"];

            // Extract catalog names from Prisma connectOrCreate structure
            $categoriaNombre = $this->extractCatalogName($item, "categoria");
            $marcaNombre = $this->extractCatalogName($item, "marca");
            $unidadMedidaNombre = $this->extractCatalogName(
                $item,
                "unidad_medida",
            );

            // Resolve IDs using cache
            $categoriaId = $this->resolveCatalogId(
                $categoriaNombre,
                $catalogCache["categorias"],
            );
            $marcaId = $this->resolveCatalogId(
                $marcaNombre,
                $catalogCache["marcas"],
            );
            $unidadMedidaId = $this->resolveCatalogId(
                $unidadMedidaNombre,
                $catalogCache["unidades_medida"],
            );

            if (!$categoriaId || !$marcaId || !$unidadMedidaId) {
                return [
                    "success" => false,
                    "is_duplicate" => false,
                    "error" => "IDs de catálogo no encontrados",
                ];
            }

            // Create product
            $producto = Producto::create([
                "cod_producto" => $item["cod_producto"] ?? null,
                "cod_barra" => $item["cod_barra"] ?? null,
                "name" => $item["name"],
                "name_ticket" => $item["name_ticket"],
                "categoria_id" => $categoriaId,
                "marca_id" => $marcaId,
                "unidad_medida_id" => $unidadMedidaId,
                "accion_tecnica" => $item["accion_tecnica"] ?? null,
                "stock_min" => $item["stock_min"] ?? 0,
                "stock_max" => $item["stock_max"] ?? null,
                "unidades_contenidas" => $item["unidades_contenidas"],
                "permitido" => $item["permitido"] ?? true,
                "estado" => true,
            ]);

            // Create product_almacen
            ProductoAlmacen::create([
                "producto_id" => $producto->id,
                "almacen_id" => $productoEnAlmacenesCreate["almacen_id"],
                "stock_fraccion" =>
                    $productoEnAlmacenesCreate["stock_fraccion"],
                "costo" => $costoAjustado,
                "ubicacion_id" => $productoEnAlmacenesCreate["ubicacion_id"],
            ]);

            return [
                "success" => true,
                "is_duplicate" => false,
                "producto_id" => $producto->id,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violations (duplicates)
            if ($e->getCode() === "23000") {
                return [
                    "success" => false,
                    "is_duplicate" => true,
                    "error" => "Producto duplicado",
                ];
            }
            throw $e;
        }
    }

    /**
     * Extract catalog name from item data
     *
     * @param array $item Product item data
     * @param string $catalog Catalog type (categoria, marca, unidad_medida)
     * @return string|null
     */
    /**
     * Importa un LOTE de productos con INSERT masivo (en vez de create() por
     * fila). Estrategia:
     *   1. Resolver IDs de catálogo desde cache (sin tocar la DB).
     *   2. Detectar duplicados de forma set-based (1 query) contra las columnas
     *      únicas cod_producto / name / cod_barra. También descarta duplicados
     *      DENTRO del mismo lote (mismo Excel repetido).
     *   3. INSERT masivo de los productos nuevos (chunks de 500).
     *   4. Re-mapear name -> id en 1 query (name es único y requerido).
     *   5. INSERT masivo de productoalmacen.
     *
     * Preserva la semántica actual: los duplicados se OMITEN (no se actualizan).
     *
     * @return array{imported:int, duplicates:int, errors:int}
     */
    private function importBatch(array $batch, array $catalogCache): array
    {
        $imported = 0;
        $duplicates = 0;
        $errors = 0;

        // 1) Construir candidatos con IDs de catálogo ya resueltos (sin DB).
        $candidatos = [];
        foreach ($batch as $item) {
            $almacenCreate = $item["producto_en_almacenes"]["create"] ?? null;
            if (!$almacenCreate) {
                $errors++;
                continue;
            }

            $categoriaId = $this->resolveCatalogId(
                $this->extractCatalogName($item, "categoria"),
                $catalogCache["categorias"],
            );
            $marcaId = $this->resolveCatalogId(
                $this->extractCatalogName($item, "marca"),
                $catalogCache["marcas"],
            );
            $unidadMedidaId = $this->resolveCatalogId(
                $this->extractCatalogName($item, "unidad_medida"),
                $catalogCache["unidades_medida"],
            );

            $name = $item["name"] ?? null;
            if (!$categoriaId || !$marcaId || !$unidadMedidaId || !$name) {
                $errors++;
                continue;
            }

            $unidadesContenidas = $item["unidades_contenidas"] ?? 1;
            $divisor = $unidadesContenidas && $unidadesContenidas != 0
                ? $unidadesContenidas
                : 1;
            $costoAjustado = ($almacenCreate["costo"] ?? 0) / $divisor;

            $candidatos[] = [
                "producto" => [
                    "cod_producto" => $item["cod_producto"] ?? null,
                    "cod_barra" => $item["cod_barra"] ?? null,
                    "name" => $name,
                    "name_ticket" => $item["name_ticket"] ?? null,
                    "categoria_id" => $categoriaId,
                    "marca_id" => $marcaId,
                    "unidad_medida_id" => $unidadMedidaId,
                    "accion_tecnica" => $item["accion_tecnica"] ?? null,
                    "stock_min" => $item["stock_min"] ?? 0,
                    "stock_max" => $item["stock_max"] ?? null,
                    "unidades_contenidas" => $unidadesContenidas,
                    "permitido" => ($item["permitido"] ?? true) ? 1 : 0,
                    "estado" => 1,
                ],
                "almacen" => [
                    "almacen_id" => $almacenCreate["almacen_id"],
                    "stock_fraccion" => $almacenCreate["stock_fraccion"] ?? 0,
                    "costo" => $costoAjustado,
                    "ubicacion_id" => $almacenCreate["ubicacion_id"] ?? null,
                ],
            ];
        }

        if (empty($candidatos)) {
            return compact("imported", "duplicates", "errors");
        }

        // 2) Detección set-based de duplicados (1 sola query).
        $cods = collect($candidatos)->pluck("producto.cod_producto")->filter()->unique()->values()->all();
        $names = collect($candidatos)->pluck("producto.name")->filter()->unique()->values()->all();
        $barras = collect($candidatos)->pluck("producto.cod_barra")->filter()->unique()->values()->all();

        $existentes = Producto::query()
            ->where(function ($q) use ($cods, $names, $barras) {
                if ($cods) $q->orWhereIn("cod_producto", $cods);
                if ($names) $q->orWhereIn("name", $names);
                if ($barras) $q->orWhereIn("cod_barra", $barras);
            })
            ->get(["cod_producto", "name", "cod_barra"]);

        $existingCods = $existentes->pluck("cod_producto")->filter()->flip();
        $existingNames = $existentes->pluck("name")->filter()->flip();
        $existingBarras = $existentes->pluck("cod_barra")->filter()->flip();

        // Sets para descartar duplicados dentro del propio lote.
        $seenCods = [];
        $seenNames = [];
        $seenBarras = [];

        $now = now()->format("Y-m-d H:i:s.v"); // datetime(3)
        $nuevos = [];            // filas de producto para insert
        $nuevosAlmacen = [];     // name => datos de almacén (para linkear)

        foreach ($candidatos as $c) {
            $p = $c["producto"];
            $cod = $p["cod_producto"];
            $nm = $p["name"];
            $barra = $p["cod_barra"];

            $esDup =
                ($cod !== null && (isset($existingCods[$cod]) || isset($seenCods[$cod]))) ||
                (isset($existingNames[$nm]) || isset($seenNames[$nm])) ||
                ($barra !== null && (isset($existingBarras[$barra]) || isset($seenBarras[$barra])));

            if ($esDup) {
                $duplicates++;
                continue;
            }

            if ($cod !== null) $seenCods[$cod] = true;
            $seenNames[$nm] = true;
            if ($barra !== null) $seenBarras[$barra] = true;

            $nuevos[] = array_merge($p, [
                "created_at" => $now,
                "updated_at" => $now,
            ]);
            $nuevosAlmacen[$nm] = $c["almacen"]; // name único → key segura
        }

        if (empty($nuevos)) {
            return compact("imported", "duplicates", "errors");
        }

        // 3) INSERT masivo de productos.
        foreach (array_chunk($nuevos, 500) as $chunk) {
            Producto::insert($chunk);
        }

        // 4) Re-mapear name -> id (name es único y requerido).
        $mapa = Producto::query()
            ->whereIn("name", array_keys($nuevosAlmacen))
            ->pluck("id", "name"); // [name => id]

        // 5) INSERT masivo de productoalmacen.
        $almacenRows = [];
        foreach ($nuevosAlmacen as $nm => $a) {
            $productoId = $mapa[$nm] ?? null;
            if (!$productoId) {
                $errors++;
                continue;
            }
            $almacenRows[] = [
                "producto_id" => $productoId,
                "almacen_id" => $a["almacen_id"],
                "stock_fraccion" => $a["stock_fraccion"],
                "costo" => $a["costo"],
                "ubicacion_id" => $a["ubicacion_id"],
                "created_at" => $now,
                "updated_at" => $now,
            ];
            $imported++;
        }

        foreach (array_chunk($almacenRows, 500) as $chunk) {
            ProductoAlmacen::insert($chunk);
        }

        return compact("imported", "duplicates", "errors");
    }

    private function extractCatalogName(array $item, string $catalog): ?string
    {
        if (isset($item[$catalog]["connectOrCreate"]["where"]["name"])) {
            return $item[$catalog]["connectOrCreate"]["where"]["name"];
        } elseif (isset($item[$catalog . "_id"])) {
            return $item[$catalog . "_id"];
        }
        return null;
    }

    /**
     * Resolve catalog ID from cache
     *
     * @param string|null $name Catalog name
     * @param array $cache Catalog cache
     * @return int|null
     */
    private function resolveCatalogId(?string $name, array $cache): ?int
    {
        if (!$name) {
            return null;
        }

        // Primero buscar por nombre en cache (incluso si es numérico,
        // porque pueden existir nombres como "6" o "45846")
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        if (is_numeric($name)) {
            return (int) $name;
        }

        return null;
    }

    /**
     * Update import progress in cache
     *
     * @param string $importId Import ID
     * @param array $progress Progress data
     * @return void
     */
    private function updateImportProgress(
        string $importId,
        array $progress,
    ): void {
        Cache::put("import_progress_{$importId}", $progress, 3600); // 1 hour cache
    }

    /**
     * Check if import should be cancelled
     *
     * @param string $importId Import ID
     * @return bool
     */
    private function shouldCancelImport(string $importId): bool
    {
        return Cache::has("import_cancel_{$importId}");
    }

    /**
     * Clean up import cache data
     *
     * @param string $importId Import ID
     * @return void
     */
    private function cleanupImportCache(string $importId): void
    {
        Cache::forget("import_progress_{$importId}");
        Cache::forget("import_cancel_{$importId}");
    }

    /**
     * Generate unique import ID
     *
     * @return string
     */
    private function generateImportId(): string
    {
        return "import_" . uniqid() . "_" . time();
    }

    /**
     * Validate single product data
     *
     * @param array $item Product data
     * @return array Validation result
     */
    /**
     * Get import results
     *
     * @param string $importId Import ID
     * @return JsonResponse
     */
    public function getImportResults(string $importId): JsonResponse
    {
        try {
            $results = Cache::get("import_results_{$importId}");

            if (!$results) {
                return response()->json(
                    [
                        "error" =>
                            "Resultados de importación no encontrados o expirados",
                    ],
                    404,
                );
            }

            return response()->json([
                "data" => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Error obteniendo resultados de importación: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Validate single product data
     */
    private function validateSingleProduct(array $item): array
    {
        $errors = [];

        $requiredFields = ["name", "name_ticket", "unidades_contenidas"];
        foreach ($requiredFields as $field) {
            if (!isset($item[$field]) || empty($item[$field])) {
                $errors[] = "Campo requerido '{$field}' faltante";
            }
        }

        if (
            isset($item["unidades_contenidas"]) &&
            $item["unidades_contenidas"] <= 0
        ) {
            $errors[] = "Unidades contenidas debe ser mayor a 0";
        }

        return [
            "is_valid" => empty($errors),
            "errors" => $errors,
        ];
    }

    /**
     * Process import synchronously (without queue)
     * 
     * @param array $data Product data to import
     * @param string $importId Import ID for tracking
     * @param int $userId User ID performing import
     * @return array Import results
     */
    private function processImportSync(array $data, string $importId, int $userId): array
    {
        // Asegurar que no haya timeout durante la importación
        set_time_limit(600); // 10 minutos máximo
        ini_set('memory_limit', '512M'); // Aumentar límite de memoria
        
        $startTime = now();
        
        try {
            // Preparar cache de catálogos
            $catalogCache = $this->prepareCatalogCache($data);
            
            // OPTIMIZACIÓN: Aumentar batch size para más velocidad
            // Batches más grandes = menos overhead de transacciones
            $totalProducts = count($data);
            $batchSize = 250; // Aumentado de 100 a 250 (2.5x más rápido)
            $batches = array_chunk($data, $batchSize);

            $imported = 0;
            $duplicates = 0;
            $errors = 0;

            // OPTIMIZACIÓN: recolectar los almacenes afectados para invalidar su
            // cache UNA sola vez al final. Antes cada fila disparaba ~3 DELETEs
            // en la tabla `cache` vía los observers de Producto/ProductoAlmacen
            // (miles de queries desperdiciadas invalidando siempre lo mismo).
            $almacenesAfectados = collect($data)
                ->map(fn ($item) => $item['producto_en_almacenes']['create']['almacen_id'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Silenciar los eventos de modelo durante la importación masiva: los
            // observers de Producto/ProductoAlmacen SOLO invalidan cache (y eso
            // lo hacemos una vez al final). Los IDs son auto_increment y no
            // dependen de eventos, así que es seguro. Reduce ~90% de las queries.
            Producto::withoutEvents(function () use ($batches, $catalogCache, &$imported, &$duplicates, &$errors) {
                // Procesar cada batch dentro de una transacción separada
                foreach ($batches as $batchIndex => $batch) {
                    try {
                        DB::transaction(function () use ($batch, $catalogCache, &$imported, &$duplicates, &$errors) {
                            // INSERT masivo del lote (ver importBatch): reemplaza
                            // el create() fila por fila (2 INSERT/fila) por unos
                            // pocos INSERT multi-fila. Mucho más rápido.
                            $res = $this->importBatch($batch, $catalogCache);
                            $imported += $res['imported'];
                            $duplicates += $res['duplicates'];
                            $errors += $res['errors'];
                        }, 2); // 2 intentos en caso de deadlock

                    } catch (\Exception $e) {
                        // Si falla todo el batch, contar todos como errores
                        $errors += count($batch);
                        Log::error("Batch import failed", [
                            'batch_index' => $batchIndex,
                            'batch_size' => count($batch),
                            'error' => $e->getMessage()
                        ]);
                    }

                    // Liberar memoria después de cada batch
                    gc_collect_cycles();
                }
            });

            // Invalidar la cache de los almacenes afectados UNA sola vez (en vez
            // de por cada fila) — esto es lo que reemplaza a los observers.
            $cacheService = app(\App\Services\Cache\ProductoCacheService::class);
            foreach ($almacenesAfectados as $almacenId) {
                $cacheService->invalidateProductosAlmacen($almacenId);
                Cache::forget("productos_listado_ligero_{$almacenId}");
                Cache::forget("productos_listado_completo_{$almacenId}");
            }
            
            $duration = now()->diffInSeconds($startTime);
            
            return [
                'imported' => $imported,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'duration' => $duration,
            ];
            
        } catch (\Exception $e) {
            Log::error("Error in processImportSync", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
