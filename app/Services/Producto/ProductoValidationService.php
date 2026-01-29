<?php

namespace App\Services\Producto;

use App\Contracts\ProductoValidationServiceInterface;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\ProductoAlmacenVenta;
use App\Models\ProductoAlmacenCompra;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\UnidadMedida;
use App\Models\Compra;
use Illuminate\Http\JsonResponse;

class ProductoValidationService implements ProductoValidationServiceInterface
{
    /**
     * Validate if a product code already exists
     *
     * @param string $codProducto Product code to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateProductCode(string $codProducto, ?int $excludeId = null): JsonResponse
    {
        try {
            $query = Producto::where('cod_producto', $codProducto)
                ->select('cod_producto');

            // Exclude current product if updating
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $producto = $query->first();

            return response()->json([
                'data' => $producto?->cod_producto,
                'exists' => $producto !== null,
                'message' => $producto ? 'El código de producto ya existe' : 'Código de producto disponible'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al validar código de producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate if a barcode already exists
     *
     * @param string $codBarra Barcode to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateBarcode(string $codBarra, ?int $excludeId = null): JsonResponse
    {
        try {
            $query = Producto::where('cod_barra', $codBarra)
                ->select('cod_barra');

            // Exclude current product if updating
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $producto = $query->first();

            return response()->json([
                'data' => $producto?->cod_barra,
                'exists' => $producto !== null,
                'message' => $producto ? 'El código de barras ya existe' : 'Código de barras disponible'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al validar código de barras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate if a product name already exists
     *
     * @param string $name Product name to validate
     * @param int|null $excludeId Product ID to exclude from validation (for updates)
     * @return JsonResponse
     */
    public function validateProductName(string $name, ?int $excludeId = null): JsonResponse
    {
        try {
            $query = Producto::where('name', $name)
                ->select('name');

            // Exclude current product if updating
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $producto = $query->first();

            return response()->json([
                'data' => $producto?->name,
                'exists' => $producto !== null,
                'message' => $producto ? 'El nombre del producto ya existe' : 'Nombre del producto disponible'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al validar nombre del producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate product data before creation
     *
     * @param array $data Product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateProductCreation(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Validate required fields
        if (empty($data['name'])) {
            $errors[] = 'El nombre del producto es requerido';
        }

        if (empty($data['name_ticket'])) {
            $errors[] = 'El nombre para ticket es requerido';
        }

        if (empty($data['categoria_id'])) {
            $errors[] = 'La categoría es requerida';
        }

        if (empty($data['marca_id'])) {
            $errors[] = 'La marca es requerida';
        }

        if (empty($data['unidad_medida_id'])) {
            $errors[] = 'La unidad de medida es requerida';
        }

        if (empty($data['almacen_id'])) {
            $errors[] = 'El almacén es requerido';
        }

        // Validate numeric fields
        if (isset($data['stock_min']) && $data['stock_min'] < 0) {
            $errors[] = 'El stock mínimo no puede ser negativo';
        }

        if (isset($data['stock_max']) && $data['stock_max'] < 0) {
            $errors[] = 'El stock máximo no puede ser negativo';
        }

        if (isset($data['unidades_contenidas']) && $data['unidades_contenidas'] <= 0) {
            $errors[] = 'Las unidades contenidas deben ser mayor a 0';
        }

        // Validate stock_max is greater than stock_min
        if (isset($data['stock_min']) && isset($data['stock_max'])) {
            if ($data['stock_max'] < $data['stock_min']) {
                $warnings[] = 'El stock máximo es menor al stock mínimo';
            }
        }

        // Check for existing duplicates
        if (!empty($data['cod_producto'])) {
            $exists = Producto::where('cod_producto', $data['cod_producto'])->exists();
            if ($exists) {
                $errors[] = 'El código de producto ya existe';
            }
        }

        if (!empty($data['cod_barra'])) {
            $exists = Producto::where('cod_barra', $data['cod_barra'])->exists();
            if ($exists) {
                $errors[] = 'El código de barras ya existe';
            }
        }

        if (!empty($data['name'])) {
            $exists = Producto::where('name', $data['name'])->exists();
            if ($exists) {
                $errors[] = 'El nombre del producto ya existe';
            }
        }

        // Validate catalog data exists
        $catalogValidation = $this->validateCatalogData([
            'categoria_id' => $data['categoria_id'] ?? null,
            'marca_id' => $data['marca_id'] ?? null,
            'unidad_medida_id' => $data['unidad_medida_id'] ?? null,
        ]);

        $errors = array_merge($errors, $catalogValidation['errors']);

        // Validate unit derivatives (prices)
        if (isset($data['unidades_derivadas']) && is_array($data['unidades_derivadas'])) {
            $priceValidation = $this->validateUnitDerivatives($data['unidades_derivadas']);
            $errors = array_merge($errors, $priceValidation['errors']);
            $warnings = array_merge($warnings, $priceValidation['warnings']);
        } else {
            $errors[] = 'Se requiere al menos una unidad derivada con precios';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate product data before update
     *
     * @param int $productId Product ID being updated
     * @param array $data Product data to validate
     * @return array Validation results with errors and warnings
     */
    public function validateProductUpdate(int $productId, array $data): array
    {
        $errors = [];
        $warnings = [];

        // Validate required fields
        if (empty($data['name'])) {
            $errors[] = 'El nombre del producto es requerido';
        }

        if (empty($data['name_ticket'])) {
            $errors[] = 'El nombre para ticket es requerido';
        }

        // Check for existing duplicates (excluding current product)
        if (!empty($data['cod_producto'])) {
            $exists = Producto::where('cod_producto', $data['cod_producto'])
                ->where('id', '!=', $productId)
                ->exists();
            if ($exists) {
                $errors[] = 'El código de producto ya existe';
            }
        }

        if (!empty($data['cod_barra'])) {
            $exists = Producto::where('cod_barra', $data['cod_barra'])
                ->where('id', '!=', $productId)
                ->exists();
            if ($exists) {
                $errors[] = 'El código de barras ya existe';
            }
        }

        if (!empty($data['name'])) {
            $exists = Producto::where('name', $data['name'])
                ->where('id', '!=', $productId)
                ->exists();
            if ($exists) {
                $errors[] = 'El nombre del producto ya existe';
            }
        }

        // Validate catalog data exists
        $catalogValidation = $this->validateCatalogData([
            'categoria_id' => $data['categoria_id'] ?? null,
            'marca_id' => $data['marca_id'] ?? null,
            'unidad_medida_id' => $data['unidad_medida_id'] ?? null,
        ]);

        $errors = array_merge($errors, $catalogValidation['errors']);

        // Check if product can be safely updated (has transactions)
        $hasTransactions = $this->checkProductTransactions($productId);
        if ($hasTransactions['has_transactions']) {
            if (isset($data['unidad_medida_id'])) {
                $currentProduct = Producto::find($productId);
                if ($currentProduct && $currentProduct->unidad_medida_id != $data['unidad_medida_id']) {
                    $warnings[] = 'El producto tiene transacciones, cambiar la unidad de medida podría afectar el inventario';
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate if a product can be deleted
     *
     * @param int $productId Product ID to validate for deletion
     * @return array Validation results with blocking issues
     */
    public function validateProductDeletion(int $productId): array
    {
        $errors = [];
        $warnings = [];

        // Check if product has inventory movements
        $tieneIngresos = ProductoAlmacenIngresoSalida::whereHas('productoAlmacen', function ($q) use ($productId) {
            $q->where('producto_id', $productId);
        })->exists();

        if ($tieneIngresos) {
            $errors[] = 'El producto tiene movimientos de inventario (ingresos/salidas)';
        }

        // Check if product has sales
        $tieneVentas = ProductoAlmacenVenta::whereHas('productoAlmacen', function ($q) use ($productId) {
            $q->where('producto_id', $productId);
        })->exists();

        if ($tieneVentas) {
            $errors[] = 'El producto tiene ventas asociadas';
        }

        // Check purchases
        $compras = Compra::whereHas('productosPorAlmacen', function ($q) use ($productId) {
                $q->whereHas('productoAlmacen', function ($paq) use ($productId) {
                    $paq->where('producto_id', $productId);
                });
            })
            ->orderBy('created_at', 'asc')
            ->select('id', 'descripcion')
            ->take(2)
            ->get();

        if ($compras->count() > 1) {
            $errors[] = 'El producto tiene múltiples compras realizadas';
        } elseif ($compras->count() === 1) {
            $compra = $compras->first();
            if ($compra->descripcion !== 'Stock inicial por creación de producto') {
                $errors[] = 'El producto tiene compras realizadas';
            } else {
                $warnings[] = 'Se eliminará también la compra de stock inicial';
            }
        }

        // Check current stock
        $productoAlmacenes = ProductoAlmacen::where('producto_id', $productId)
            ->where('stock_fraccion', '>', 0)
            ->count();

        if ($productoAlmacenes > 0) {
            $warnings[] = 'El producto tiene stock actual en uno o más almacenes';
        }

        return [
            'can_delete' => empty($errors),
            'blocking_issues' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate stock levels and business rules
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param array $stockData Stock data to validate
     * @return array Validation results
     */
    public function validateStockLevels(int $productId, int $almacenId, array $stockData): array
    {
        $errors = [];
        $warnings = [];

        try {
            $producto = Producto::find($productId);
            if (!$producto) {
                $errors[] = 'Producto no encontrado';
                return ['is_valid' => false, 'errors' => $errors, 'warnings' => $warnings];
            }

            $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                ->where('almacen_id', $almacenId)
                ->first();

            if (!$productoAlmacen) {
                $errors[] = 'El producto no existe en el almacén especificado';
                return ['is_valid' => false, 'errors' => $errors, 'warnings' => $warnings];
            }

            $currentStock = (float) $productoAlmacen->stock_fraccion;
            $newStock = isset($stockData['new_stock']) ? (float) $stockData['new_stock'] : $currentStock;

            // Check minimum stock levels
            if ($newStock < $producto->stock_min) {
                $warnings[] = "El stock resultante ({$newStock}) está por debajo del mínimo ({$producto->stock_min})";
            }

            // Check maximum stock levels
            if ($producto->stock_max && $newStock > $producto->stock_max) {
                $warnings[] = "El stock resultante ({$newStock}) supera el máximo ({$producto->stock_max})";
            }

            // Check for negative stock
            if ($newStock < 0) {
                $errors[] = 'El stock no puede ser negativo';
            }

            // Validate movement quantity
            if (isset($stockData['movement_quantity'])) {
                $movementQty = (float) $stockData['movement_quantity'];
                $movementType = $stockData['movement_type'] ?? 'out';

                if ($movementType === 'out' && $movementQty > $currentStock) {
                    $errors[] = "No hay suficiente stock. Stock actual: {$currentStock}, cantidad solicitada: {$movementQty}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = 'Error al validar niveles de stock: ' . $e->getMessage();
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Check if required catalog data exists (categories, brands, units)
     *
     * @param array $catalogData Catalog IDs to validate
     * @return array Validation results with missing items
     */
    public function validateCatalogData(array $catalogData): array
    {
        $errors = [];

        // Validate category exists
        if (isset($catalogData['categoria_id'])) {
            $exists = Categoria::where('id', $catalogData['categoria_id'])->exists();
            if (!$exists) {
                $errors[] = 'La categoría especificada no existe';
            }
        }

        // Validate brand exists
        if (isset($catalogData['marca_id'])) {
            $exists = Marca::where('id', $catalogData['marca_id'])->exists();
            if (!$exists) {
                $errors[] = 'La marca especificada no existe';
            }
        }

        // Validate unit of measure exists
        if (isset($catalogData['unidad_medida_id'])) {
            $exists = UnidadMedida::where('id', $catalogData['unidad_medida_id'])->exists();
            if (!$exists) {
                $errors[] = 'La unidad de medida especificada no existe';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate unit derivatives (price data)
     *
     * @param array $unitDerivatives Array of unit derivative data
     * @return array Validation results
     */
    private function validateUnitDerivatives(array $unitDerivatives): array
    {
        $errors = [];
        $warnings = [];

        if (empty($unitDerivatives)) {
            $errors[] = 'Se requiere al menos una unidad derivada';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        foreach ($unitDerivatives as $index => $ud) {
            $itemIndex = $index + 1;

            // Required fields
            if (!isset($ud['unidad_derivada_id']) || empty($ud['unidad_derivada_id'])) {
                $errors[] = "Unidad derivada requerida (ítem {$itemIndex})";
            }

            if (!isset($ud['factor']) || $ud['factor'] <= 0) {
                $errors[] = "Factor debe ser mayor a 0 (ítem {$itemIndex})";
            }

            if (!isset($ud['precio_publico']) || $ud['precio_publico'] <= 0) {
                $errors[] = "Precio público requerido y debe ser mayor a 0 (ítem {$itemIndex})";
            }

            if (!isset($ud['costo']) || $ud['costo'] <= 0) {
                $errors[] = "Costo requerido y debe ser mayor a 0 (ítem {$itemIndex})";
            }

            // Price logic validations
            if (isset($ud['precio_publico']) && isset($ud['costo'])) {
                $margen = (($ud['precio_publico'] - $ud['costo']) / $ud['costo']) * 100;
                if ($margen < 0) {
                    $warnings[] = "Precio público menor al costo (pérdida) (ítem {$itemIndex})";
                } elseif ($margen < 10) {
                    $warnings[] = "Margen de ganancia muy bajo ({$margen}%) (ítem {$itemIndex})";
                }
            }

            // Special price should be less than public price
            if (isset($ud['precio_especial']) && isset($ud['precio_publico'])) {
                if ($ud['precio_especial'] > 0 && $ud['precio_especial'] > $ud['precio_publico']) {
                    $warnings[] = "Precio especial mayor al precio público (ítem {$itemIndex})";
                }
            }

            // Commission rates should be reasonable
            $commissionFields = ['comision_publico', 'comision_especial', 'comision_minimo', 'comision_ultimo'];
            foreach ($commissionFields as $field) {
                if (isset($ud[$field]) && ($ud[$field] < 0 || $ud[$field] > 100)) {
                    $warnings[] = "Comisión fuera del rango 0-100% en {$field} (ítem {$itemIndex})";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Check if product has transactions
     *
     * @param int $productId Product ID
     * @return array Transaction check results
     */
    private function checkProductTransactions(int $productId): array
    {
        $hasIngresos = ProductoAlmacenIngresoSalida::whereHas('productoAlmacen', function ($q) use ($productId) {
            $q->where('producto_id', $productId);
        })->exists();

        $hasVentas = ProductoAlmacenVenta::whereHas('productoAlmacen', function ($q) use ($productId) {
            $q->where('producto_id', $productId);
        })->exists();

        $hasCompras = ProductoAlmacenCompra::whereHas('productoAlmacen', function ($q) use ($productId) {
            $q->where('producto_id', $productId);
        })->exists();

        return [
            'has_transactions' => $hasIngresos || $hasVentas || $hasCompras,
            'has_ingresos' => $hasIngresos,
            'has_ventas' => $hasVentas,
            'has_compras' => $hasCompras
        ];
    }
}
