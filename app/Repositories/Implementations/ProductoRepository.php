<?php

namespace App\Repositories\Implementations;

use App\Models\Producto;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\ProductoAlmacenVenta;
use App\Models\ProductoAlmacenCompra;
use App\Models\Compra;
use App\Repositories\Interfaces\ProductoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductoRepository implements ProductoRepositoryInterface
{
    /**
     * Find a product by ID with optional relations
     */
    public function findById(int $id, array $relations = []): ?Producto
    {
        $query = Producto::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Find a product by code
     */
    public function findByCode(string $code): ?Producto
    {
        return Producto::where('cod_producto', $code)->first();
    }

    /**
     * Find a product by barcode
     */
    public function findByBarcode(string $barcode): ?Producto
    {
        return Producto::where('cod_barra', $barcode)->first();
    }

    /**
     * Listado LIGERO de productos por almacén para el modal de búsqueda.
     *
     * Optimizado para devolver miles de productos rápido:
     *  - Sin `compras` (solo se usa en el modal de detalle del producto SELECCIONADO)
     *  - Sin `productoComplementario` en unidadesDerivadas (solo en el modal de detalle)
     *  - Sin `tiene_ingresos` (3 subqueries EXISTS que no aportan a la grilla)
     *  - Sin ubicación (no se renderiza en la grilla del modal)
     *
     * Devuelve solo los productos ACTIVOS que existen en el almacén.
     */
    public function findListadoLigeroByAlmacen(int $almacenId): \Illuminate\Database\Eloquent\Collection
    {
        return Producto::select([
            'producto.id',
            'producto.cod_producto',
            'producto.cod_barra',
            'producto.name',
            'producto.name_ticket',
            'producto.categoria_id',
            'producto.marca_id',
            'producto.unidad_medida_id',
            'producto.accion_tecnica',
            'producto.stock_min',
            'producto.stock_max',
            'producto.unidades_contenidas',
            'producto.estado',
            // Necesario para la advertencia "sin unidades derivadas" del modal de
            // búsqueda: sin este campo llega null y la alerta sale para todos.
            'producto.permitido',
        ])
            ->where('producto.estado', 1)
            ->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId);
            })
            ->with([
                'marca:id,name',
                'categoria:id,name',
                'unidadMedida:id,name',
                'productoEnAlmacenes' => function ($q) use ($almacenId) {
                    $q->select([
                        'id', 'producto_id', 'almacen_id',
                        'stock_fraccion', 'costo',
                        'costo_anterior', 'costo_actual', 'costo_con_flete',
                    ])
                    ->where('almacen_id', $almacenId)
                    ->with([
                        'unidadesDerivadas' => function ($udq) {
                            // Solo lo que la grilla necesita para los 4 precios.
                            // ⚠️ NO incluir `activador_publico` — la columna no
                            // existe en la tabla (es bug preexistente del
                            // findByAlmacen original).
                            $udq->select([
                                'id', 'producto_almacen_id', 'unidad_derivada_id', 'factor',
                                'precio_publico', 'comision_publico',
                                'precio_especial', 'comision_especial', 'activador_especial',
                                'precio_minimo', 'comision_minimo', 'activador_minimo',
                                'precio_ultimo', 'comision_ultimo', 'activador_ultimo',
                            ])
                            ->with(['unidadDerivada:id,name'])
                            ->orderBy('orden', 'asc')
                            ->orderBy('factor', 'desc');
                        },
                    ]);
                },
            ])
            ->orderBy('producto.name', 'asc')
            ->get();
    }

    /**
     * Listado COMPLETO de la vista "Mi Almacén": TODOS los productos del almacén
     * (ambos estados), en un solo request, para que el front cargue todo de una
     * y filtre en memoria (igual que el modal de búsqueda).
     *
     * Diferencias con findListadoLigeroByAlmacen (modal):
     *  - Incluye AMBOS estados (la vista tiene filtro Activo/Inactivo).
     *  - Incluye `tiene_ingresos` (columna "Tiene Movimientos" + bloqueo de borrado).
     *  - Incluye `img`, `ficha_tecnica`, `permitido`.
     *  - Incluye TODOS los `productoEnAlmacenes` (no solo el actual) porque la
     *    columna Stock tiene un popover con el stock/precio en otros almacenes.
     *  - Incluye `ubicacion`, `costo_anterior/actual`, `stock_costo_anterior/actual`.
     *
     * Igual que el modal: SIN `compras` (no se renderizan en la grilla).
     */
    public function findListadoCompletoByAlmacen(int $almacenId): \Illuminate\Database\Eloquent\Collection
    {
        return Producto::select([
            'producto.id',
            'producto.cod_producto',
            'producto.cod_barra',
            'producto.name',
            'producto.name_ticket',
            'producto.categoria_id',
            'producto.marca_id',
            'producto.unidad_medida_id',
            'producto.accion_tecnica',
            'producto.img',
            'producto.ficha_tecnica',
            'producto.stock_min',
            'producto.stock_max',
            'producto.unidades_contenidas',
            'producto.estado',
            'producto.permitido',
        ])
            ->addSelect(DB::raw('(
                EXISTS (SELECT 1 FROM productoalmaceningresosalida pai JOIN productoalmacen pa ON pa.id = pai.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacenventa pav JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacencompra pac JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id WHERE pa.producto_id = producto.id)
            ) as tiene_ingresos'))
            // Solo productos que existen en el almacén seleccionado (ambos estados).
            ->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId);
            })
            ->with([
                'marca:id,name',
                'categoria:id,name',
                'unidadMedida:id,name',
                // TODOS los almacenes (para el popover "otros almacenes" de la columna Stock).
                'productoEnAlmacenes' => function ($q) {
                    $q->select([
                        'id', 'producto_id', 'almacen_id', 'ubicacion_id',
                        'stock_fraccion', 'costo',
                        'costo_anterior', 'costo_actual', 'costo_con_flete',
                        'stock_costo_anterior', 'stock_costo_actual',
                    ])
                    ->with([
                        'almacen:id,name',
                        'ubicacion:id,name',
                        'unidadesDerivadas' => function ($udq) {
                            $udq->select([
                                'id', 'producto_almacen_id', 'unidad_derivada_id', 'factor',
                                'precio_publico', 'comision_publico',
                                'precio_especial', 'comision_especial', 'activador_especial',
                                'precio_minimo', 'comision_minimo', 'activador_minimo',
                                'precio_ultimo', 'comision_ultimo', 'activador_ultimo',
                            ])
                            ->with(['unidadDerivada:id,name'])
                            ->orderBy('orden', 'asc')
                            ->orderBy('factor', 'desc');
                        },
                    ]);
                },
            ])
            ->orderBy('producto.name', 'asc')
            ->get();
    }

    /**
     * Versión RÁPIDA de findListadoCompletoByAlmacen que devuelve un array PHP
     * plano con el MISMO shape JSON, construido con Query Builder (sin hidratar
     * modelos Eloquent ni aplicar casts).
     *
     * Motivo: serializar ~5000 modelos Eloquent con relaciones anidadas vía
     * toArray()/json_encode tarda ~4.5s, de los cuales ~1.5s son los casts
     * `decimal` (Brick\Math\BigDecimal, ~17x más lento que number_format) y el
     * resto es la recursión de attributesToArray/relationsToArray. Construyendo
     * el array a mano el costo baja a ~1.3s (solo la query) + ~60ms de encode.
     *
     * Las escalas de las columnas decimales en la BD coinciden EXACTAMENTE con
     * las de los casts (p.ej. costo decimal(9,4) == cast decimal:4), por lo que
     * los strings que devuelve MySQL son idénticos a los que producía Eloquent.
     * Solo se castean manualmente los enteros y booleanos.
     *
     * @return array<int, array<string, mixed>>  Lista de productos (shape idéntico al de Eloquent->toArray()).
     */
    public function findListadoCompletoArrayByAlmacen(int $almacenId): array
    {
        // Subquery: ids de productos que existen en el almacén seleccionado.
        $productosDelAlmacen = DB::table('productoalmacen')
            ->select('producto_id')
            ->where('almacen_id', $almacenId);

        // ── 1) Productos base + marca/categoria/unidad_medida + tiene_ingresos ──
        $productos = DB::table('producto')
            ->select([
                'producto.id',
                'producto.cod_producto',
                'producto.cod_barra',
                'producto.name',
                'producto.name_ticket',
                'producto.categoria_id',
                'producto.marca_id',
                'producto.unidad_medida_id',
                'producto.accion_tecnica',
                'producto.img',
                'producto.ficha_tecnica',
                'producto.stock_min',
                'producto.stock_max',
                'producto.unidades_contenidas',
                'producto.estado',
                'producto.permitido',
                'marca.name as marca_name',
                'categoria.name as categoria_name',
                'unidadmedida.name as unidad_medida_name',
            ])
            ->selectRaw('(
                EXISTS (SELECT 1 FROM productoalmaceningresosalida pai JOIN productoalmacen pa ON pa.id = pai.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacenventa pav JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacencompra pac JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id WHERE pa.producto_id = producto.id)
            ) as tiene_ingresos')
            ->leftJoin('marca', 'marca.id', '=', 'producto.marca_id')
            ->leftJoin('categoria', 'categoria.id', '=', 'producto.categoria_id')
            ->leftJoin('unidadmedida', 'unidadmedida.id', '=', 'producto.unidad_medida_id')
            ->whereIn('producto.id', $productosDelAlmacen)
            ->orderBy('producto.name', 'asc')
            ->get();

        if ($productos->isEmpty()) {
            return [];
        }

        $productoIds = $productos->pluck('id')->all();

        // ── 2) productoEnAlmacenes (TODOS los almacenes) + almacen/ubicacion ──
        $almacenesRows = DB::table('productoalmacen as pa')
            ->select([
                'pa.id',
                'pa.producto_id',
                'pa.almacen_id',
                'pa.ubicacion_id',
                'pa.stock_fraccion',
                'pa.costo',
                'pa.costo_anterior',
                'pa.costo_actual',
                'pa.costo_con_flete',
                'pa.stock_costo_anterior',
                'pa.stock_costo_actual',
                'almacen.name as almacen_name',
                'ubicacion.name as ubicacion_name',
            ])
            ->leftJoin('almacen', 'almacen.id', '=', 'pa.almacen_id')
            ->leftJoin('ubicacion', 'ubicacion.id', '=', 'pa.ubicacion_id')
            ->whereIn('pa.producto_id', $productoIds)
            ->orderBy('pa.id', 'asc')
            ->get();

        $productoAlmacenIds = $almacenesRows->pluck('id')->all();

        // ── 3) unidadesDerivadas + unidad_derivada ──
        $udRows = DB::table('productoalmacenunidadderivada as ud')
            ->select([
                'ud.id',
                'ud.producto_almacen_id',
                'ud.unidad_derivada_id',
                'ud.factor',
                'ud.precio_publico',
                'ud.comision_publico',
                'ud.precio_especial',
                'ud.comision_especial',
                'ud.activador_especial',
                'ud.precio_minimo',
                'ud.comision_minimo',
                'ud.activador_minimo',
                'ud.precio_ultimo',
                'ud.comision_ultimo',
                'ud.activador_ultimo',
                'unidadderivada.name as unidad_derivada_name',
            ])
            ->leftJoin('unidadderivada', 'unidadderivada.id', '=', 'ud.unidad_derivada_id')
            ->whereIn('ud.producto_almacen_id', $productoAlmacenIds)
            ->orderBy('ud.orden', 'asc')
            ->orderBy('ud.factor', 'desc')
            ->get();

        // ── Agrupar unidades derivadas por producto_almacen_id ──
        $udPorAlmacen = [];
        foreach ($udRows as $ud) {
            $udPorAlmacen[$ud->producto_almacen_id][] = [
                'id' => (int) $ud->id,
                'producto_almacen_id' => (int) $ud->producto_almacen_id,
                'unidad_derivada_id' => (int) $ud->unidad_derivada_id,
                'factor' => $ud->factor,
                'precio_publico' => $ud->precio_publico,
                'comision_publico' => $ud->comision_publico,
                'precio_especial' => $ud->precio_especial,
                'comision_especial' => $ud->comision_especial,
                'activador_especial' => $ud->activador_especial,
                'precio_minimo' => $ud->precio_minimo,
                'comision_minimo' => $ud->comision_minimo,
                'activador_minimo' => $ud->activador_minimo,
                'precio_ultimo' => $ud->precio_ultimo,
                'comision_ultimo' => $ud->comision_ultimo,
                'activador_ultimo' => $ud->activador_ultimo,
                'unidad_derivada' => $ud->unidad_derivada_id === null ? null : [
                    'id' => (int) $ud->unidad_derivada_id,
                    'name' => $ud->unidad_derivada_name,
                ],
            ];
        }

        // ── Agrupar productoEnAlmacenes por producto_id ──
        $almacenesPorProducto = [];
        foreach ($almacenesRows as $pa) {
            $almacenesPorProducto[$pa->producto_id][] = [
                'id' => (int) $pa->id,
                'producto_id' => (int) $pa->producto_id,
                'almacen_id' => (int) $pa->almacen_id,
                'ubicacion_id' => $pa->ubicacion_id === null ? null : (int) $pa->ubicacion_id,
                'stock_fraccion' => $pa->stock_fraccion,
                'costo' => $pa->costo,
                'costo_anterior' => $pa->costo_anterior,
                'costo_actual' => $pa->costo_actual,
                'costo_con_flete' => $pa->costo_con_flete,
                'stock_costo_anterior' => $pa->stock_costo_anterior,
                'stock_costo_actual' => $pa->stock_costo_actual,
                'almacen' => $pa->almacen_id === null ? null : [
                    'id' => (int) $pa->almacen_id,
                    'name' => $pa->almacen_name,
                ],
                'ubicacion' => $pa->ubicacion_id === null ? null : [
                    'id' => (int) $pa->ubicacion_id,
                    'name' => $pa->ubicacion_name,
                ],
                'unidades_derivadas' => $udPorAlmacen[$pa->id] ?? [],
            ];
        }

        // ── Ensamblar productos finales ──
        $result = [];
        foreach ($productos as $p) {
            $result[] = [
                'id' => (int) $p->id,
                'cod_producto' => $p->cod_producto,
                'cod_barra' => $p->cod_barra,
                'name' => $p->name,
                'name_ticket' => $p->name_ticket,
                'categoria_id' => $p->categoria_id === null ? null : (int) $p->categoria_id,
                'marca_id' => $p->marca_id === null ? null : (int) $p->marca_id,
                'unidad_medida_id' => $p->unidad_medida_id === null ? null : (int) $p->unidad_medida_id,
                'accion_tecnica' => $p->accion_tecnica,
                'img' => $p->img,
                'ficha_tecnica' => $p->ficha_tecnica,
                'stock_min' => $p->stock_min,
                'stock_max' => $p->stock_max === null ? null : (int) $p->stock_max,
                'unidades_contenidas' => $p->unidades_contenidas,
                'estado' => (bool) $p->estado,
                'permitido' => (bool) $p->permitido,
                'tiene_ingresos' => (bool) $p->tiene_ingresos,
                'marca' => $p->marca_id === null ? null : [
                    'id' => (int) $p->marca_id,
                    'name' => $p->marca_name,
                ],
                'categoria' => $p->categoria_id === null ? null : [
                    'id' => (int) $p->categoria_id,
                    'name' => $p->categoria_name,
                ],
                'unidad_medida' => $p->unidad_medida_id === null ? null : [
                    'id' => (int) $p->unidad_medida_id,
                    'name' => $p->unidad_medida_name,
                ],
                'producto_en_almacenes' => $almacenesPorProducto[$p->id] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Get paginated products by warehouse with filters
     */
    public function findByAlmacen(?int $almacenId, array $filters = [], int $perPage = 100): LengthAwarePaginator
    {
        $query = Producto::select([
            'id',
            'cod_producto',
            'cod_barra',
            'name',
            'name_ticket',
            'categoria_id',
            'marca_id',
            'unidad_medida_id',
            'accion_tecnica',
            'img',
            'ficha_tecnica',
            'stock_min',
            'stock_max',
            'unidades_contenidas',
            'estado',
            'permitido',
        ])
            ->with([
                'marca:id,name',
                'categoria:id,name',
                'unidadMedida:id,name',
                'productoEnAlmacenes' => function ($q) {
                    $q->select('id', 'producto_id', 'almacen_id', 'ubicacion_id', 'stock_fraccion', 'costo', 'costo_anterior', 'costo_actual', 'costo_con_flete', 'stock_costo_anterior', 'stock_costo_actual')
                        ->with([
                            'almacen:id,name',
                            'ubicacion:id,name',
                            'unidadesDerivadas' => function ($udq) {
                                $udq->select('id', 'producto_almacen_id', 'unidad_derivada_id', 'factor', 'precio_publico', 'comision_publico', 'precio_especial', 'comision_especial', 'activador_especial', 'precio_minimo', 'comision_minimo', 'activador_minimo', 'precio_ultimo', 'comision_ultimo', 'activador_ultimo', 'producto_complementario_id', 'producto_complementario_cantidad')
                                    ->with([
                                        'unidadDerivada:id,name',
                                        'productoComplementario:id,name,cod_producto',
                                    ])
                                    ->orderBy('orden', 'asc')
                                    ->orderBy('factor', 'desc');
                            },
                            // ⚠️ Quitado `activador_publico` que no existe en la tabla.
                            // (Bug preexistente en el findByAlmacen original)
                            'compras' => function ($cq) {
                                $cq->select('id', 'producto_almacen_id', 'costo', 'compra_id')
                                    ->with([
                                        'compra:id,fecha,proveedor_id,user_id,tipo_documento,serie,numero,estado_de_compra',
                                        'compra.proveedor:id,razon_social',
                                        'compra.user:id,name',
                                        'unidadesDerivadas' => function ($udq) {
                                            $udq->select('id', 'producto_almacen_compra_id', 'unidad_derivada_inmutable_id', 'factor', 'cantidad', 'lote', 'vencimiento', 'flete', 'bonificacion')
                                                ->with('unidadDerivadaInmutable:id,name');
                                        },
                                    ])
                                    ->orderBy('id', 'desc')
                                    ->limit(6);
                            },
                        ]);
                },
            ]);

        $isSearch = isset($filters['search']) || isset($filters['accion_tecnica']) || isset($filters['cod_barra']);

        // Calcular tiene_ingresos solo cuando NO hay búsqueda activa (pesado: 3 EXISTS subqueries)
        if (!$isSearch) {
            $query->addSelect(DB::raw('(
                EXISTS (SELECT 1 FROM productoalmaceningresosalida pai JOIN productoalmacen pa ON pa.id = pai.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacenventa pav JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id WHERE pa.producto_id = producto.id)
                OR EXISTS (SELECT 1 FROM productoalmacencompra pac JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id WHERE pa.producto_id = producto.id)
            ) as tiene_ingresos'));
        } else {
            $query->addSelect(DB::raw('0 as tiene_ingresos'));
        }

        // Solo productos que existen en el almacén seleccionado (si se proporcionó)
        if ($almacenId) {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId);
            });
        }

        // Apply filters
        $this->applyFilters($query, $filters, $almacenId);

        // Orden: simple name ASC cuando hay búsqueda (más rápido), completo cuando no
        if ($isSearch) {
            return $query->orderBy('name', 'asc')->paginate($perPage);
        }

        return $query->orderByRaw('
            CASE
                WHEN LEFT(LOWER(name), 1) BETWEEN "a" AND "z" THEN 0
                ELSE 1
            END,
            name ASC
        ')->paginate($perPage);
    }   

    /**
     * Get all products (no pagination)
     */
    public function getAll(array $relations = []): Collection
    {
        $query = Producto::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * Create a new product
     */
    public function create(array $data): Producto
    {
        return Producto::create($data);
    }

    /**
     * Update an existing product
     */
    public function update(int $id, array $data): Producto
    {
        $producto = Producto::findOrFail($id);
        $producto->update($data);

        return $producto->fresh();
    }

    /**
     * Delete a product
     */
    public function delete(int $id): bool
    {
        $producto = Producto::findOrFail($id);

        return $producto->delete();
    }

    /**
     * Check if a product exists by field
     */
    public function exists(string $field, $value, ?int $excludeId = null): bool
    {
        $query = Producto::where($field, $value);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if product has inventory movements (ingresos/salidas)
     */
    public function hasMovements(int $id): bool
    {
        return ProductoAlmacenIngresoSalida::whereHas('productoAlmacen', function ($q) use ($id) {
            $q->where('producto_id', $id);
        })->exists();
    }

    /**
     * Check if product has sales
     */
    public function hasSales(int $id): bool
    {
        return ProductoAlmacenVenta::whereHas('productoAlmacen', function ($q) use ($id) {
            $q->where('producto_id', $id);
        })->exists();
    }

    /**
     * Check if product has purchases
     */
    public function hasPurchases(int $id): bool
    {
        return ProductoAlmacenCompra::whereHas('productoAlmacen', function ($q) use ($id) {
            $q->where('producto_id', $id);
        })->exists();
    }

    /**
     * Get the count of purchases for a product
     */
    public function getPurchasesCount(int $id): int
    {
        return Compra::whereHas('productosPorAlmacen', function ($q) use ($id) {
            $q->whereHas('productoAlmacen', function ($paq) use ($id) {
                $paq->where('producto_id', $id);
            });
        })->count();
    }

    /**
     * Get the first purchase for a product
     */
    public function getFirstPurchase(int $id): ?object
    {
        return Compra::whereHas('productosPorAlmacen', function ($q) use ($id) {
            $q->whereHas('productoAlmacen', function ($paq) use ($id) {
                $paq->where('producto_id', $id);
            });
        })
            ->orderBy('created_at', 'asc')
            ->select('id', 'descripcion')
            ->first();
    }

    /**
     * Generate the next product code
     */
    public function generateNextCode(): string
    {
        // Buscar el código de producto más alto (numérico)
        // Filtrar solo códigos numéricos para evitar problemas con códigos alfanuméricos
        $ultimoCodigo = Producto::whereRaw('cod_producto REGEXP "^[0-9]+$"')
            ->orderByRaw('CAST(cod_producto AS UNSIGNED) DESC')
            ->value('cod_producto');

        // Si no hay productos o todos tienen códigos no numéricos, empezar desde 1
        if (!$ultimoCodigo) {
            return '1';
        }

        // Incrementar el ultimo código encontrado
        return (string) ((int) $ultimoCodigo + 1);
    }

    /**
     * Search products by term (name, code, barcode)
     */
    public function search(string $term, int $almacenId, int $limit = 20): Collection
    {
        return Producto::with([
            'marca:id,name',
            'categoria:id,name',
            'unidadMedida:id,name',
            'productoEnAlmacenes' => function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId);
            },
        ])
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('cod_producto', 'like', "%{$term}%")
                    ->orWhere('cod_barra', 'like', "%{$term}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Apply filters to the product query
     */
    private function applyFilters($query, array $filters, ?int $almacenId): void
    {
        // Search filter - busca coincidencias parciales (contains)
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cod_producto', 'like', "%{$search}%")
                    ->orWhere('cod_barra', 'like', "%{$search}%")
                    ->orWhere('name_ticket', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (isset($filters['estado'])) {
            $estadoValue = filter_var($filters['estado'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estadoValue !== null) {
                $query->where('estado', $estadoValue);
            }
        }

        // Category filter
        if (isset($filters['categoria_id'])) {
            $query->where('categoria_id', $filters['categoria_id']);
        }

        // Brand filter
        if (isset($filters['marca_id'])) {
            $query->where('marca_id', $filters['marca_id']);
        }

        // Unit of measure filter
        if (isset($filters['unidad_medida_id'])) {
            $query->where('unidad_medida_id', $filters['unidad_medida_id']);
        }

        // Technical action filter
        if (isset($filters['accion_tecnica'])) {
            $query->where('accion_tecnica', 'like', "%{$filters['accion_tecnica']}%");
        }

        // Location filter
        if (isset($filters['ubicacion_id']) && $almacenId) {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId, $filters) {
                $q->where('almacen_id', $almacenId)
                    ->where('ubicacion_id', $filters['ubicacion_id']);
            });
        }

        // Stock filter
        if (isset($filters['cs_stock']) && $almacenId) {
            $this->applyStockFilter($query, $filters['cs_stock'], $almacenId);
        }

        // Commission filter
        if (isset($filters['cs_comision']) && $almacenId) {
            $this->applyCommissionFilter($query, $filters['cs_comision'], $almacenId);
        }
    }

    /**
     * Apply stock filter to query
     */
    private function applyStockFilter($query, string $stockFilter, int $almacenId): void
    {
        if ($stockFilter === 'con_stock') {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)->where('stock_fraccion', '>', 0);
            });
        } elseif ($stockFilter === 'sin_stock') {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)->where('stock_fraccion', '<=', 0);
            });
        }
    }

    /**
     * Apply commission filter to query
     */
    private function applyCommissionFilter($query, string $commissionFilter, int $almacenId): void
    {
        if ($commissionFilter === 'con_comision') {
            // Productos que tienen AL MENOS UNA unidad derivada con AL MENOS UNA comisión > 0
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)->whereHas('unidadesDerivadas', function ($udq) {
                    $udq->where(function ($orQuery) {
                        $orQuery
                            ->where('comision_publico', '>', 0)
                            ->orWhere('comision_especial', '>', 0)
                            ->orWhere('comision_minimo', '>', 0)
                            ->orWhere('comision_ultimo', '>', 0);
                    });
                });
            });
        } elseif ($commissionFilter === 'sin_comision') {
            // Productos donde NINGUNA unidad derivada tiene comisión > 0
            // Incluye productos sin unidades derivadas o con todas las comisiones <= 0 o NULL
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)
                    // NO debe tener ninguna unidad derivada con comisión > 0
                    ->whereDoesntHave('unidadesDerivadas', function ($udq) {
                        $udq->where(function ($orQuery) {
                            $orQuery
                                ->where('comision_publico', '>', 0)
                                ->orWhere('comision_especial', '>', 0)
                                ->orWhere('comision_minimo', '>', 0)
                                ->orWhere('comision_ultimo', '>', 0);
                        });
                    });
            });
        }
        // Si es 'all', no aplicar ningún filtro de comisión
    }

    /**
     * Get products with batches nearing expiration
     */
    public function getVencimientos(int $almacenId, int $dias): \Illuminate\Support\Collection
    {
        $today = now()->startOfDay();
        $todayDate = $today->toDateString();
        $fechaLimite = $dias > 0 ? $today->copy()->addDays($dias)->toDateString() : null;

        // Helper to map data
        $mapper = function ($item, $dateField, $qtyField) use ($today) {
            $vencimiento = \Carbon\Carbon::parse($item->{$dateField})->startOfDay();
            return (object) [
                'name' => $item->name,
                'cod_producto' => $item->cod_producto ?? '',
                'cantidad' => (float) $item->{$qtyField},
                'stock_min' => $item->stock_min,
                'almacen' => $item->almacen,
                'vencimiento' => $item->{$dateField},
                'lote' => $item->lote,
                'unidad' => $item->unidad,
                'estado' => $vencimiento->lte($today) ? 'Vencido' : 'Por Vencer',
                'dias_restantes' => (int) $today->diffInDays($vencimiento, false)
            ];
        };

        $executeQuery = function ($table, $idField, $pivotTable, $qtyField) use ($almacenId, $dias, $todayDate, $fechaLimite) {
            $query = DB::table("$table as ud")
                ->join("$pivotTable as pt", 'pt.id', '=', "ud.$idField")
                ->join('productoalmacen as pa', 'pa.id', '=', 'pt.producto_almacen_id')
                ->join('producto as p', 'p.id', '=', 'pa.producto_id')
                ->join('almacen as a', 'a.id', '=', 'pa.almacen_id')
                ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'ud.unidad_derivada_inmutable_id')
                ->where('pa.almacen_id', $almacenId)
                ->where("ud.$qtyField", '>', 0)
                ->whereNotNull('ud.vencimiento');

            if ($dias === 0) {
                // Vencidos = ya expiraron o vencen hoy, comparando por fecha calendario.
                $query->whereDate('ud.vencimiento', '<=', $todayDate);
            } elseif ($dias > 0 && $dias < 3650) {
                // Próximos a vencer = desde mañana hasta hoy + N días, comparando por fecha.
                $query->whereDate('ud.vencimiento', '>', $todayDate)
                    ->whereDate('ud.vencimiento', '<=', $fechaLimite);
            }
            // else: dias = -1 or very large -> Show All (No filter)

            return $query->select([
                'p.id as producto_id',
                'p.name as name',
                'p.cod_producto as cod_producto',
                "ud.$qtyField as cantidad",
                'p.stock_min',
                'a.name as almacen',
                'ud.vencimiento',
                'ud.lote',
                'udi.name as unidad',
            ])->get();
        };

        $ingresos = $executeQuery(
            'unidadderivadainmutableingresosalida',
            'producto_almacen_ingreso_salida_id',
            'productoalmaceningresosalida',
            'cantidad_restante'
        )->map(fn($item) => $mapper($item, 'vencimiento', 'cantidad'));

        $recepciones = $executeQuery(
            'unidadderivadainmutablerecepcion',
            'producto_almacen_recepcion_id',
            'productoalmacenrecepcion',
            'cantidad_restante'
        )->map(fn($item) => $mapper($item, 'vencimiento', 'cantidad'));

        $compras = $executeQuery(
            'unidadderivadainmutablecompra',
            'producto_almacen_compra_id',
            'productoalmacencompra',
            'cantidad_pendiente'
        )->map(fn($item) => $mapper($item, 'vencimiento', 'cantidad'));

        return $ingresos
            ->concat($recepciones)
            ->concat($compras)
            // Agrupar SOLO por producto + almacen + unidad + fecha de vencimiento
            // NO incluir lote para que todos los lotes del mismo producto se agrupen
            // El frontend mostrará la cantidad total agrupada y el detalle de cada lote
            ->groupBy(function ($item) {
                $fecha = \Carbon\Carbon::parse($item->vencimiento)->toDateString();
                return implode('|', [
                    $item->producto_id ?? '',
                    $item->almacen ?? '',
                    $item->unidad ?? '',
                    $fecha,
                ]);
            })
            ->map(function ($group) {
                $first = clone $group->first();
                $first->cantidad = round((float) $group->sum('cantidad'), 3);
                return $first;
            })
            ->sortBy([
                ['vencimiento', 'asc'],
                ['name', 'asc'],
                ['lote', 'asc'],
            ])
            ->values();
    }
}
