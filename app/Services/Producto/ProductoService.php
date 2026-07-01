<?php

namespace App\Services\Producto;

use App\Contracts\ProductoServiceInterface;
use App\Events\ProductoCreated;
use App\Events\ProductoUpdated;
use App\Events\ProductoDeleted;
use App\Repositories\Interfaces\ProductoRepositoryInterface;
use App\Repositories\Interfaces\ProductoAlmacenRepositoryInterface;
use App\Repositories\Interfaces\ProductoPrecioRepositoryInterface;
use App\Services\Cache\ProductoCacheService;
use App\Models\IngresoSalida;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\UnidadDerivadaInmutable;
use App\Models\TipoIngresoSalida;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductoService implements ProductoServiceInterface
{
    public function __construct(
        private ProductoRepositoryInterface $productoRepository,
        private ProductoAlmacenRepositoryInterface $productoAlmacenRepository,
        private ProductoPrecioRepositoryInterface $precioRepository,
        private ProductoCacheService $cacheService,
    ) {}

    /**
     * Get paginated list of products with filters and relations
     *
     * @param array $filters Filters for the query (almacen_id is required)
     * @return JsonResponse
     */
    public function getAllByAlmacen(array $filters): JsonResponse
    {
        $almacenId = $filters["almacen_id"] ?? null;
        $perPage = $filters["per_page"] ?? 100;

        // Si hay almacen_id, usar cache. Si no, listar todo (sin cache por ahora para evitar colisiones)
        if ($almacenId) {
            $productos = $this->cacheService->getProductosByAlmacen(
                $almacenId,
                $filters,
                $perPage,
                function () use ($almacenId, $filters, $perPage) {
                    return $this->productoRepository->findByAlmacen(
                        $almacenId,
                        $filters,
                        $perPage,
                    );
                },
            );
        } else {
            $productos = $this->productoRepository->findByAlmacen(
                null,
                $filters,
                $perPage,
            );
        }

        return response()->json($productos);
    }

    /**
     * Get a single product by ID with all its relations
     *
     * @param int $id Product ID
     * @return JsonResponse
     */
    public function getListadoLigeroPorAlmacen(int $almacenId): JsonResponse
    {
        $cacheKey = "productos_listado_ligero_{$almacenId}";

        // IMPORTANTE: cacheamos el STRING JSON ya serializado, NO la Collection
        // de Eloquent. Cachear la Collection obliga a re-encodear ~5.8MB de JSON
        // en CADA cache hit (≈1.6s) + deserializar los modelos (≈0.5s), lo que
        // dejaba cada request "cacheado" en ~2s. Guardando el JSON listo, el hit
        // baja a ~20ms (solo lee el string y lo devuelve sin re-encodear).
        $cachedJson = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedJson !== null) {
            // fromJsonString devuelve el JSON ya serializado tal cual,
            // sin volver a encodear (evita ~2s por hit).
            return JsonResponse::fromJsonString($cachedJson);
        }

        $startTime = microtime(true);
        $productos = $this->productoRepository->findListadoLigeroByAlmacen($almacenId);
        $json = json_encode(['data' => $productos]);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // TTL 10 minutos. Se invalida explícitamente en create/update/delete
        // (ver booted() en Producto model y observer de ProductoAlmacen).
        try {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $json, 600);
        } catch (\Exception $e) {
            Log::warning("Cache listado ligero no guardado: " . $e->getMessage());
        }

        // \Log::info("Listado ligero calculado: {$productos->count()} productos en {$duration}ms (cache miss)");

        return JsonResponse::fromJsonString($json);
    }

    /**
     * Listado COMPLETO de productos por almacén para la vista "Mi Almacén".
     *
     * Cachea el STRING JSON ya serializado (igual que getListadoLigeroPorAlmacen)
     * para evitar re-encodear ~12MB en cada cache hit.
     */
    public function getListadoCompletoPorAlmacen(int $almacenId): JsonResponse
    {
        $cacheKey = "productos_listado_completo_{$almacenId}";

        $cachedJson = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedJson !== null) {
            return JsonResponse::fromJsonString($cachedJson);
        }

        $startTime = microtime(true);
        // findListadoCompletoArrayByAlmacen construye el array con Query Builder
        // (sin hidratar modelos Eloquent ni aplicar casts decimal de Brick\Math),
        // lo que reduce el cache miss de ~4.5s a ~1.3s con un JSON byte-idéntico.
        $productos = $this->productoRepository->findListadoCompletoArrayByAlmacen($almacenId);
        $json = json_encode(['data' => $productos]);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        try {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $json, 600);
        } catch (\Exception $e) {
            Log::warning("Cache listado completo no guardado: " . $e->getMessage());
        }

        // \Log::info("Listado completo calculado: " . count($productos) . " productos en {$duration}ms (cache miss)");

        return JsonResponse::fromJsonString($json);
    }

    public function getById(int $id): JsonResponse
    {
        $producto = $this->productoRepository->findById($id, [
            "categoria",
            "marca",
            "unidadMedida",
            "productoEnAlmacenes.almacen",
            "productoEnAlmacenes.ubicacion",
            "productoEnAlmacenes.unidadesDerivadas" => function ($udq) {
                // Ordenar por `orden` (drag-and-drop del usuario), con factor
                // como desempate para registros viejos sin orden definido.
                $udq->with(["productoComplementario:id,name,cod_producto", "unidadDerivada:id,name"])
                    ->orderBy("orden", "asc")
                    ->orderBy("factor", "desc");
            },
        ]);

        if (!$producto) {
            return response()->json(
                [
                    "error" => "Producto no encontrado",
                ],
                404,
            );
        }

        return response()->json($producto);
    }

    /**
     * Create a new product with all related data
     *
     * @param array $data Validated product data
     * @return JsonResponse
     */
    public function create(array $data): JsonResponse
    {
        return DB::transaction(function () use ($data) {
            try {
                // Step 1: Auto-generate product code if not provided
                if (empty($data["cod_producto"])) {
                    $data["cod_producto"] = $this->productoRepository->generateNextCode();
                }

                // Step 2: Create Product
                $producto = $this->productoRepository->create([
                    "cod_producto" => $data["cod_producto"],
                    "cod_barra" => $data["cod_barra"] ?? null,
                    "name" => $data["name"],
                    "name_ticket" => $data["name_ticket"],
                    "categoria_id" => $data["categoria_id"],
                    "marca_id" => $data["marca_id"],
                    "unidad_medida_id" => $data["unidad_medida_id"],
                    "accion_tecnica" => $data["accion_tecnica"] ?? null,
                    "img" => $data["img"] ?? null,
                    "ficha_tecnica" => $data["ficha_tecnica"] ?? null,
                    "stock_min" => $data["stock_min"],
                    "stock_max" => $data["stock_max"] ?? null,
                    "unidades_contenidas" => $data["unidades_contenidas"],
                    "estado" => $data["estado"] ?? true,
                    "permitido" => $data["permitido"] ?? true,
                ]);

                // Step 3: Create ProductoAlmacen
                $unidadesDerivadas = $data["unidades_derivadas"];
                $costoUnidad =
                    count($unidadesDerivadas) > 0
                    ? $unidadesDerivadas[0]["costo"] /
                    $unidadesDerivadas[0]["factor"]
                    : 0;

                $productoAlmacen = $this->productoAlmacenRepository->create([
                    "producto_id" => $producto->id,
                    "almacen_id" => $data["almacen_id"],
                    "costo" => $costoUnidad,
                    "ubicacion_id" => $data["producto_almacen"]["ubicacion_id"],
                    "stock_fraccion" => 0,
                ]);

                // Step 4: Create product prices (unit derivatives)
                $this->precioRepository->createBatch(
                    $productoAlmacen->id,
                    $unidadesDerivadas,
                );

                // Step 5: Handle initial stock if provided
                if (
                    isset($data["compra"]) &&
                    $this->hasInitialStock($data["compra"])
                ) {
                    $this->createInitialStock(
                        $producto,
                        $productoAlmacen,
                        $data["compra"],
                        $unidadesDerivadas,
                    );
                }

                // Load relations for response
                $producto->load(["categoria", "marca", "unidadMedida"]);

                // Dispatch product created event
                ProductoCreated::dispatch($producto, (int) Auth::id(), [
                    "almacen_id" => $data["almacen_id"],
                    "has_initial_stock" =>
                    isset($data["compra"]) &&
                        $this->hasInitialStock($data["compra"]),
                    "created_via" => "web_form",
                ]);

                // Invalidar cache
                $this->cacheService->invalidateProductosAlmacen(
                    $data["almacen_id"],
                );
                // Invalidar también el listado ligero y completo
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_ligero_{$data['almacen_id']}"
                );
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_completo_{$data['almacen_id']}"
                );

                return response()->json(
                    [
                        "data" => $producto,
                        "message" => "Producto creado exitosamente",
                    ],
                    201,
                );
            } catch (\Exception $e) {
                return response()->json(
                    [
                        "error" =>
                        "Error al crear el producto: " . $e->getMessage(),
                    ],
                    500,
                );
            }
        });
    }

    /**
     * Update an existing product with all related data
     *
     * @param int $id Product ID
     * @param array $data Validated product data
     * @return JsonResponse
     */
    public function update(int $id, array $data): JsonResponse
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                $producto = $this->productoRepository->findById($id);

                if (!$producto) {
                    return response()->json(
                        [
                            "error" => "Producto no encontrado",
                        ],
                        404,
                    );
                }

                // Step 1: Auto-generate product code if not provided
                if (empty($data["cod_producto"])) {
                    $data["cod_producto"] = $this->productoRepository->generateNextCode();
                }

                // Step 2: Prepare update data
                $dataToUpdate = [
                    "cod_producto" =>
                    $producto->cod_producto === $data["cod_producto"]
                        ? $producto->cod_producto
                        : $data["cod_producto"],
                    "cod_barra" => $data["cod_barra"] ?? null,
                    "name" =>
                    $producto->name === $data["name"]
                        ? $producto->name
                        : $data["name"],
                    "name_ticket" => $data["name_ticket"],
                    "categoria_id" => $data["categoria_id"],
                    "marca_id" => $data["marca_id"],
                    "unidad_medida_id" => $data["unidad_medida_id"],
                    "accion_tecnica" => $data["accion_tecnica"] ?? null,
                    "img" => $data["img"] ?? $producto->img,
                    "ficha_tecnica" => $data["ficha_tecnica"] ?? $producto->ficha_tecnica,
                    "stock_min" => $data["stock_min"],
                    "stock_max" => $data["stock_max"] ?? null,
                    "unidades_contenidas" => $data["unidades_contenidas"],
                    "estado" => $data["estado"] ?? true,
                    "permitido" => $data["permitido"] ?? true,
                ];

                // Step 3: Update Product
                $producto = $this->productoRepository->update(
                    $id,
                    $dataToUpdate,
                );

                // Step 4: Update ProductoAlmacen
                $unidadesDerivadas = $data["unidades_derivadas"];
                $costoUnidad =
                    count($unidadesDerivadas) > 0
                    ? $unidadesDerivadas[0]["costo"] /
                    $unidadesDerivadas[0]["factor"]
                    : 0;

                $productoAlmacen = $this->productoAlmacenRepository->findByProductoAndAlmacen(
                    $id,
                    $data["almacen_id"],
                );

                if (!$productoAlmacen) {
                    return response()->json(
                        [
                            "error" =>
                            "Producto no encontrado en el almacén especificado",
                        ],
                        404,
                    );
                }

                // Actualizar ubicación directamente
                $this->productoAlmacenRepository->update($productoAlmacen->id, [
                    "ubicacion_id" => $data["producto_almacen"]["ubicacion_id"],
                ]);

                // Nota: NO actualizamos el costo aquí porque el costo debe cambiar solo
                // cuando se recibe una compra (en RecepcionAlmacenController).
                // Editar el costo desde el formulario de producto no es la forma correcta
                // de actualizar precios, ya que no hay cantidad asociada.

                // Step 5: Replace all prices (delete and recreate)
                $this->precioRepository->replaceAll(
                    $productoAlmacen->id,
                    $unidadesDerivadas,
                );

                // Load relations for response
                $producto->load([
                    "marca:id,name",
                    "categoria:id,name",
                    "unidadMedida:id,name",
                    "productoEnAlmacenes" => function ($q) use ($data) {
                        $q->where("almacen_id", $data["almacen_id"])->with([
                            "almacen:id,name",
                            "ubicacion:id,name",
                            "unidadesDerivadas" => function ($udq) {
                                $udq->with([
                                    "unidadDerivada:id,name",
                                    "productoComplementario:id,name,cod_producto",
                                ])->orderBy("orden", "asc")->orderBy("factor", "desc");
                            },
                        ]);
                    },
                ]);

                // Dispatch product updated event
                ProductoUpdated::dispatch($producto, (int) Auth::id(), [
                    "almacen_id" => $data["almacen_id"],
                    "updated_fields" => array_keys($dataToUpdate),
                    "updated_via" => "web_form",
                ]);

                // Invalidar cache
                $this->cacheService->invalidateProductosAlmacen(
                    $data["almacen_id"],
                );
                // Invalidar también el listado ligero y completo
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_ligero_{$data['almacen_id']}"
                );
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_completo_{$data['almacen_id']}"
                );

                return response()->json([
                    "data" => $producto,
                    "message" => "Producto actualizado exitosamente",
                ]);
            } catch (\Exception $e) {
                Log::error('Error al actualizar producto', [
                    'producto_id' => $id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(
                    [
                        "error" =>
                        "Error al actualizar el producto: " .
                            $e->getMessage(),
                    ],
                    500,
                );
            }
        });
    }

    /**
     * Delete a product with proper validations
     *
     * @param int $id Product ID
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            try {
                $producto = $this->productoRepository->findById($id);

                if (!$producto) {
                    return response()->json(
                        [
                            "error" => "Producto no encontrado",
                        ],
                        404,
                    );
                }

                // Step 1: Check if product has inventory movements
                if ($this->productoRepository->hasMovements($id)) {
                    return response()->json(
                        [
                            "error" =>
                            "No se puede eliminar el producto porque tiene movimientos de inventario (ingresos/salidas)",
                        ],
                        400,
                    );
                }

                // Step 2: Check if product has sales
                if ($this->productoRepository->hasSales($id)) {
                    return response()->json(
                        [
                            "error" =>
                            "No se puede eliminar el producto porque tiene ventas asociadas",
                        ],
                        400,
                    );
                }

                // Step 3: Check purchases count
                $purchasesCount = $this->productoRepository->getPurchasesCount(
                    $id,
                );

                // Step 4: Validate maximum of 1 purchase
                if ($purchasesCount > 1) {
                    return response()->json(
                        [
                            "error" => "El producto tiene compras realizadas",
                        ],
                        400,
                    );
                }

                // Step 5: If has 1 purchase, verify it's initial stock
                if ($purchasesCount === 1) {
                    $compra = $this->productoRepository->getFirstPurchase($id);
                    if (
                        $compra &&
                        $compra->descripcion !==
                        "Stock inicial por creación de producto"
                    ) {
                        return response()->json(
                            [
                                "error" =>
                                "El producto tiene compras realizadas",
                            ],
                            400,
                        );
                    }

                    // Delete initial stock purchase using the Compra model directly
                    if ($compra) {
                        \App\Models\Compra::find($compra->id)?->delete();
                    }
                }

                // Step 6: Delete product (cascade will handle related data)
                $this->productoRepository->delete($id);

                // Dispatch product deleted event
                ProductoDeleted::dispatch($producto, (int) Auth::id(), [
                    "had_purchases" => $purchasesCount > 0,
                    "deleted_via" => "web_form",
                ]);

                // Invalidar cache de todos los almacenes (no sabemos en cuáles estaba)
                $this->cacheService->invalidateAll();
                // Invalidar también todos los listados (ligero y completo)
                \Illuminate\Support\Facades\Cache::flush();

                return response()->json([
                    "message" => "Producto eliminado exitosamente",
                ]);
            } catch (\Exception $e) {
                return response()->json(
                    [
                        "error" =>
                        "Error al eliminar el producto: " .
                            $e->getMessage(),
                    ],
                    500,
                );
            }
        });
    }

    /**
     * Check if initial stock is provided
     *
     * @param array $compra
     * @return bool
     */
    private function hasInitialStock(array $compra): bool
    {
        return !empty($compra["stock_entero"]) ||
            !empty($compra["stock_fraccion"]);
    }

    /**
     * Create initial stock entry for new product
     *
     * @param object $producto
     * @param object $productoAlmacen
     * @param array $compra
     * @param array $unidadesDerivadas
     * @return void
     */
    private function createInitialStock(
        object $producto,
        object $productoAlmacen,
        array $compra,
        array $unidadesDerivadas,
    ): void {
        // Calculate total quantity in fractions
        $stockEntero = $compra["stock_entero"] ?? 0;
        $stockFraccion = $compra["stock_fraccion"] ?? 0;
        $compraCantidad =
            $stockEntero * $producto->unidades_contenidas + $stockFraccion;
        $compraCosto = $unidadesDerivadas[0]["costo"];

        // Find adjustment type
        $tipoIngreso = TipoIngresoSalida::where(
            "name",
            "AJUSTE",
        )->firstOrFail();

        // Generate entry number
        $ultimoIngreso = IngresoSalida::where("tipo_documento", "in")
            ->latest("numero")
            ->first();
        $numero = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

        // Get user and series
        $user = Auth::user();

        // Create IngresoSalida
        $ingresoSalida = IngresoSalida::create([
            "tipo_ingreso_id" => $tipoIngreso->id,
            "descripcion" => "Ingreso por Creación de Producto",
            "almacen_id" => $productoAlmacen->almacen_id,
            "user_id" => $user->id,
            "tipo_documento" => "in",
            "serie" => 1,
            "fecha" => now(),
            "numero" => $numero,
            "estado" => true,
        ]);

        // Create ProductoAlmacenIngresoSalida
        $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create([
            "ingreso_id" => $ingresoSalida->id,
            "costo" => $compraCosto,
            "producto_almacen_id" => $productoAlmacen->id,
        ]);

        // Find base unit using repository
        $precios = $this->precioRepository->findByProductoAlmacen(
            $productoAlmacen->id,
        );
        $unidadBase = $precios->firstWhere(
            "factor",
            $producto->unidades_contenidas,
        );

        if (!$unidadBase) {
            $unidadBase = $precios->first();
        }

        if (!$unidadBase) {
            throw new \Exception(
                "No se encontró unidad derivada para el producto",
            );
        }

        // Load the unidadDerivada relationship
        $unidadBase->load("unidadDerivada");

        // Create/Connect UnidadDerivadaInmutable
        $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate([
            "name" => $unidadBase->unidadDerivada->name,
        ]);

        // Create UnidadDerivadaInmutableIngresoSalida
        $factor = $unidadesDerivadas[0]["factor"];
        $unidadDerivadaInmutableIngresoSalida = UnidadDerivadaInmutableIngresoSalida::create(
            [
                "unidad_derivada_inmutable_id" => $unidadDerivadaInmutable->id,
                "producto_almacen_ingreso_salida_id" =>
                $productoAlmacenIngresoSalida->id,
                "factor" => $factor,
                "cantidad" => $compraCantidad / $factor,
                "cantidad_restante" => $compraCantidad / $factor,
                "lote" => $compra["lote"] ?? null,
                "vencimiento" => $compra["vencimiento"] ?? null,
            ],
        );

        // Create history record
        HistorialUnidadDerivadaInmutableIngresoSalida::create([
            "unidad_derivada_inmutable_ingreso_salida_id" =>
            $unidadDerivadaInmutableIngresoSalida->id,
            "stock_anterior" => 0,
            "stock_nuevo" => $compraCantidad,
        ]);

        // Update stock in ProductoAlmacen using repository
        $this->productoAlmacenRepository->update($productoAlmacen->id, [
            "stock_fraccion" => $compraCantidad,
            "costo" => $compraCosto / $factor,
        ]);
    }

    /**
     * Get products with batches nearing expiration
     */
    public function getVencimientos(array $filters): JsonResponse
    {
        if (!isset($filters["almacen_id"])) {
            return response()->json(
                [
                    "error" => "El parámetro almacen_id es requerido",
                ],
                422,
            );
        }

        $almacenId = $filters["almacen_id"];
        $dias = $filters["dias"] ?? 30;

        // Log::info("API Vencimientos Call", ['almacen_id' => $almacenId, 'dias' => $dias]);

        $vencimientos = $this->productoRepository->getVencimientos((int) $almacenId, (int) $dias);

        // Log::info("API Vencimientos Results", ['count' => count($vencimientos)]);

        return response()->json([
            "data" => $vencimientos
        ]);
    }
}
