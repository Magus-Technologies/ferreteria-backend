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
     * Get paginated products by warehouse with filters
     */
    public function findByAlmacen(int $almacenId, array $filters = [], int $perPage = 100): LengthAwarePaginator
    {
        $query = Producto::with([
            'marca:id,name',
            'categoria:id,name',
            'unidadMedida:id,name',
            'productoEnAlmacenes' => function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)
                    ->select('id', 'producto_id', 'almacen_id', 'ubicacion_id', 'stock_fraccion', 'costo')
                    ->with([
                        'ubicacion:id,name',
                        'unidadesDerivadas' => function ($udq) {
                            $udq->select('id', 'producto_almacen_id', 'unidad_derivada_id', 'factor', 'precio_publico', 'precio_especial', 'precio_minimo', 'precio_ultimo')
                                ->with('unidadDerivada:id,name')
                                ->orderBy('factor', 'desc');
                        },
                    ]);
            },
        ]);

        // Apply filters
        $this->applyFilters($query, $filters, $almacenId);

        // Orden alfabético: Primero letras (A-Z), luego números (0-9)
        return $query->orderByRaw('
            CASE 
                WHEN LOWER(name) REGEXP "^[a-z]" THEN 0
                ELSE 1
            END,
            LOWER(name) ASC
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
        $ultimoProducto = Producto::latest('id')->first();

        return (string) ($ultimoProducto ? $ultimoProducto->id + 1 : 1);
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
    private function applyFilters($query, array $filters, int $almacenId): void
    {
        // Search filter
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cod_producto', 'like', "%{$search}%")
                    ->orWhere('cod_barra', 'like', "%{$search}%");
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
        if (isset($filters['ubicacion_id'])) {
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId, $filters) {
                $q->where('almacen_id', $almacenId)
                    ->where('ubicacion_id', $filters['ubicacion_id']);
            });
        }

        // Stock filter
        if (isset($filters['cs_stock'])) {
            $this->applyStockFilter($query, $filters['cs_stock'], $almacenId);
        }

        // Commission filter
        if (isset($filters['cs_comision'])) {
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
            $query->whereHas('productoEnAlmacenes', function ($q) use ($almacenId) {
                $q->where('almacen_id', $almacenId)->whereHas('unidadesDerivadas', function ($udq) {
                    $udq->where(function ($andQuery) {
                        $andQuery
                            ->where('comision_publico', '<=', 0)
                            ->where('comision_especial', '<=', 0)
                            ->where('comision_minimo', '<=', 0)
                            ->where('comision_ultimo', '<=', 0);
                    });
                });
            });
        }
    }
}
