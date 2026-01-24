<?php

namespace App\Services\Producto;

use App\Contracts\ProductoPriceServiceInterface;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenUnidadDerivada;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductoPriceService implements ProductoPriceServiceInterface
{
    /**
     * Get detailed price information for a product in a specific warehouse
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @return JsonResponse
     */
    public function getProductPriceDetails(int $productId, int $almacenId): JsonResponse
    {
        try {
            $producto = Producto::with([
                'marca:id,name',
                'categoria:id,name',
                'unidadMedida:id,name',
                'productoEnAlmacenes' => function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId)
                        ->with([
                            'almacen:id,name',
                            'ubicacion:id,name',
                            'unidadesDerivadas' => function ($udq) {
                                $udq->with('unidadDerivada:id,name')
                                    ->orderBy('factor', 'desc');
                            },
                        ]);
                }
            ])->findOrFail($productId);

            // Extract specific product_almacen
            $productoAlmacen = $producto->productoEnAlmacenes->first();

            if (!$productoAlmacen) {
                return response()->json([
                    'error' => 'El producto no existe en el almacén especificado'
                ], 404);
            }

            return response()->json([
                'data' => [
                    'producto' => [
                        'id' => $producto->id,
                        'name' => $producto->name,
                        'cod_producto' => $producto->cod_producto,
                        'marca' => $producto->marca,
                        'categoria' => $producto->categoria,
                        'unidad_medida' => $producto->unidadMedida,
                    ],
                    'producto_almacen' => [
                        'id' => $productoAlmacen->id,
                        'costo' => (float) $productoAlmacen->costo,
                        'stock_fraccion' => (float) $productoAlmacen->stock_fraccion,
                        'almacen' => $productoAlmacen->almacen,
                        'ubicacion' => $productoAlmacen->ubicacion,
                    ],
                    'unidades_derivadas' => $productoAlmacen->unidadesDerivadas->map(function ($ud) {
                        return [
                            'id' => $ud->id,
                            'producto_almacen_id' => $ud->producto_almacen_id,
                            'unidad_derivada_id' => $ud->unidad_derivada_id,
                            'factor' => (float) $ud->factor,
                            'precio_publico' => (float) $ud->precio_publico,
                            'comision_publico' => (float) $ud->comision_publico,
                            'precio_especial' => (float) $ud->precio_especial,
                            'comision_especial' => (float) $ud->comision_especial,
                            'activador_especial' => $ud->activador_especial ? (float) $ud->activador_especial : null,
                            'precio_minimo' => (float) $ud->precio_minimo,
                            'comision_minimo' => (float) $ud->comision_minimo,
                            'activador_minimo' => $ud->activador_minimo ? (float) $ud->activador_minimo : null,
                            'precio_ultimo' => $ud->precio_ultimo ? (float) $ud->precio_ultimo : null,
                            'comision_ultimo' => (float) $ud->comision_ultimo,
                            'activador_ultimo' => $ud->activador_ultimo ? (float) $ud->activador_ultimo : null,
                            'unidad_derivada' => $ud->unidadDerivada,
                        ];
                    }),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Producto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener detalles de precios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update prices for a product in a specific warehouse
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param array $pricesData Array of price data for different units
     * @return JsonResponse
     */
    public function updateProductPrices(int $productId, int $almacenId, array $pricesData): JsonResponse
    {
        return DB::transaction(function () use ($productId, $almacenId, $pricesData) {
            try {
                // Find ProductoAlmacen
                $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                    ->where('almacen_id', $almacenId)
                    ->firstOrFail();

                // Delete existing prices
                ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacen->id)->delete();

                // Create new prices
                $preciosData = array_map(function($item) use ($productoAlmacen) {
                    return [
                        'producto_almacen_id' => $productoAlmacen->id,
                        'unidad_derivada_id' => $item['unidad_derivada_id'],
                        'factor' => $item['factor'],
                        'precio_publico' => $item['precio_publico'],
                        'comision_publico' => $item['comision_publico'] ?? 0,
                        'precio_especial' => $item['precio_especial'] ?? 0,
                        'comision_especial' => $item['comision_especial'] ?? 0,
                        'activador_especial' => $item['activador_especial'] ?? null,
                        'precio_minimo' => $item['precio_minimo'] ?? 0,
                        'comision_minimo' => $item['comision_minimo'] ?? 0,
                        'activador_minimo' => $item['activador_minimo'] ?? null,
                        'precio_ultimo' => $item['precio_ultimo'] ?? null,
                        'comision_ultimo' => $item['comision_ultimo'] ?? 0,
                        'activador_ultimo' => $item['activador_ultimo'] ?? null,
                    ];
                }, $pricesData);

                ProductoAlmacenUnidadDerivada::insert($preciosData);

                return response()->json([
                    'message' => 'Precios actualizados exitosamente'
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'error' => 'Producto o almacén no encontrado'
                ], 404);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error al actualizar precios: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    /**
     * Calculate price based on cost and margin
     *
     * @param float $cost Product cost
     * @param float $margin Profit margin percentage
     * @param string $priceType Type of price (publico, especial, minimo, ultimo)
     * @return float Calculated price
     */
    public function calculatePrice(float $cost, float $margin, string $priceType): float
    {
        $basePrice = $cost * (1 + ($margin / 100));

        // Apply price type adjustments
        switch ($priceType) {
            case 'especial':
                return $basePrice * 0.95; // 5% discount for special price
            case 'minimo':
                return $basePrice * 0.90; // 10% discount for minimum price
            case 'ultimo':
                return $basePrice * 0.85; // 15% discount for last price
            case 'publico':
            default:
                return $basePrice;
        }
    }

    /**
     * Get price history for a product
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param int $days Number of days to look back
     * @return JsonResponse
     */
    public function getPriceHistory(int $productId, int $almacenId, int $days = 30): JsonResponse
    {
        try {
            // This would require a price_history table to track changes
            // For now, return current prices with creation date
            $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                ->where('almacen_id', $almacenId)
                ->with([
                    'unidadesDerivadas' => function ($q) {
                        $q->with('unidadDerivada:id,name')
                            ->orderBy('created_at', 'desc');
                    }
                ])
                ->firstOrFail();

            $history = $productoAlmacen->unidadesDerivadas->map(function ($ud) {
                return [
                    'fecha' => $ud->created_at,
                    'unidad_derivada' => $ud->unidadDerivada->name,
                    'precio_publico' => (float) $ud->precio_publico,
                    'precio_especial' => (float) $ud->precio_especial,
                    'precio_minimo' => (float) $ud->precio_minimo,
                    'precio_ultimo' => $ud->precio_ultimo ? (float) $ud->precio_ultimo : null,
                ];
            });

            return response()->json([
                'data' => $history
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Producto no encontrado en el almacén especificado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener historial de precios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update prices for multiple products
     *
     * @param array $productsData Array of products with their new price data
     * @return JsonResponse
     */
    public function bulkUpdatePrices(array $productsData): JsonResponse
    {
        return DB::transaction(function () use ($productsData) {
            try {
                $updated = 0;
                $errors = [];

                foreach ($productsData as $productData) {
                    try {
                        $this->updateProductPrices(
                            $productData['product_id'],
                            $productData['almacen_id'],
                            $productData['prices']
                        );
                        $updated++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'product_id' => $productData['product_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }

                return response()->json([
                    'message' => "Precios actualizados para {$updated} productos",
                    'updated_count' => $updated,
                    'errors' => $errors
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error en actualización masiva: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    /**
     * Apply price adjustments (increase/decrease by percentage or amount)
     *
     * @param array $productIds Array of product IDs
     * @param int $almacenId Warehouse ID
     * @param array $adjustment Adjustment data (type, value, price_types)
     * @return JsonResponse
     */
    public function applyPriceAdjustment(array $productIds, int $almacenId, array $adjustment): JsonResponse
    {
        return DB::transaction(function () use ($productIds, $almacenId, $adjustment) {
            try {
                $adjustmentType = $adjustment['type']; // 'percentage' or 'amount'
                $adjustmentValue = $adjustment['value'];
                $priceTypes = $adjustment['price_types'] ?? ['precio_publico', 'precio_especial', 'precio_minimo', 'precio_ultimo'];
                $updated = 0;

                foreach ($productIds as $productId) {
                    $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                        ->where('almacen_id', $almacenId)
                        ->first();

                    if (!$productoAlmacen) {
                        continue;
                    }

                    $unidadesDerivadas = ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacen->id)->get();

                    foreach ($unidadesDerivadas as $ud) {
                        $updateData = [];

                        foreach ($priceTypes as $priceType) {
                            $currentPrice = (float) $ud->$priceType;
                            if ($currentPrice > 0 || $priceType === 'precio_publico') {
                                if ($adjustmentType === 'percentage') {
                                    $newPrice = $currentPrice * (1 + ($adjustmentValue / 100));
                                } else {
                                    $newPrice = $currentPrice + $adjustmentValue;
                                }
                                $updateData[$priceType] = max(0, $newPrice);
                            }
                        }

                        if (!empty($updateData)) {
                            $ud->update($updateData);
                        }
                    }

                    $updated++;
                }

                return response()->json([
                    'message' => "Ajuste aplicado a {$updated} productos",
                    'updated_count' => $updated
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error al aplicar ajuste de precios: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    /**
     * Get products with price inconsistencies or issues
     *
     * @param int $almacenId Warehouse ID
     * @return JsonResponse
     */
    public function getProductsWithPriceIssues(int $almacenId): JsonResponse
    {
        try {
            $issues = [];

            // Products with zero public price
            $zeroPublicPrice = ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId);
                })
                ->where('precio_publico', '<=', 0)
                ->with(['productoAlmacen.producto:id,name,cod_producto', 'unidadDerivada:id,name'])
                ->get()
                ->map(function ($ud) {
                    return [
                        'issue_type' => 'precio_publico_cero',
                        'product' => $ud->productoAlmacen->producto,
                        'unit' => $ud->unidadDerivada->name,
                        'current_price' => (float) $ud->precio_publico
                    ];
                });

            $issues = array_merge($issues, $zeroPublicPrice->toArray());

            // Products where special price is higher than public price
            $invalidSpecialPrice = ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function ($q) use ($almacenId) {
                    $q->where('almacen_id', $almacenId);
                })
                ->whereColumn('precio_especial', '>', 'precio_publico')
                ->where('precio_especial', '>', 0)
                ->with(['productoAlmacen.producto:id,name,cod_producto', 'unidadDerivada:id,name'])
                ->get()
                ->map(function ($ud) {
                    return [
                        'issue_type' => 'precio_especial_mayor',
                        'product' => $ud->productoAlmacen->producto,
                        'unit' => $ud->unidadDerivada->name,
                        'precio_publico' => (float) $ud->precio_publico,
                        'precio_especial' => (float) $ud->precio_especial
                    ];
                });

            $issues = array_merge($issues, $invalidSpecialPrice->toArray());

            return response()->json([
                'data' => $issues,
                'total_issues' => count($issues)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener productos con problemas de precios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate commission for a sale based on price type and quantity
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param string $priceType Type of price used
     * @param float $quantity Quantity sold
     * @return array Commission calculation details
     */
    public function calculateCommission(int $productId, int $almacenId, string $priceType, float $quantity): array
    {
        try {
            $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                ->where('almacen_id', $almacenId)
                ->with('unidadesDerivadas')
                ->firstOrFail();

            $commissions = [];

            foreach ($productoAlmacen->unidadesDerivadas as $ud) {
                $commissionField = 'comision_' . str_replace('precio_', '', $priceType);
                $commissionRate = (float) $ud->$commissionField;
                $price = (float) $ud->$priceType;

                $commissionAmount = ($price * $quantity * $commissionRate) / 100;

                $commissions[] = [
                    'unidad_derivada' => $ud->unidadDerivada->name ?? 'N/A',
                    'factor' => (float) $ud->factor,
                    'commission_rate' => $commissionRate,
                    'price_used' => $price,
                    'quantity' => $quantity,
                    'commission_amount' => $commissionAmount
                ];
            }

            return $commissions;

        } catch (\Exception $e) {
            return [
                'error' => 'Error al calcular comisión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate price data before saving
     *
     * @param array $pricesData Price data to validate
     * @return array Validation results with errors and warnings
     */
    public function validatePriceData(array $pricesData): array
    {
        $errors = [];
        $warnings = [];

        foreach ($pricesData as $index => $priceData) {
            // Required fields
            if (!isset($priceData['precio_publico']) || $priceData['precio_publico'] <= 0) {
                $errors[] = "Precio público requerido y debe ser mayor a 0 (ítem {$index})";
            }

            if (!isset($priceData['factor']) || $priceData['factor'] <= 0) {
                $errors[] = "Factor requerido y debe ser mayor a 0 (ítem {$index})";
            }

            // Special price should not exceed public price
            if (isset($priceData['precio_especial']) && $priceData['precio_especial'] > 0) {
                if ($priceData['precio_especial'] > $priceData['precio_publico']) {
                    $warnings[] = "Precio especial mayor al precio público (ítem {$index})";
                }
            }

            // Minimum price should not exceed special or public price
            if (isset($priceData['precio_minimo']) && $priceData['precio_minimo'] > 0) {
                $publicPrice = $priceData['precio_publico'];
                $specialPrice = $priceData['precio_especial'] ?? 0;

                $comparePrice = $specialPrice > 0 ? $specialPrice : $publicPrice;

                if ($priceData['precio_minimo'] > $comparePrice) {
                    $warnings[] = "Precio mínimo mayor al precio de referencia (ítem {$index})";
                }
            }

            // Commission rates should be reasonable (0-100%)
            $commissionFields = ['comision_publico', 'comision_especial', 'comision_minimo', 'comision_ultimo'];
            foreach ($commissionFields as $field) {
                if (isset($priceData[$field]) && ($priceData[$field] < 0 || $priceData[$field] > 100)) {
                    $warnings[] = "Comisión fuera del rango 0-100% en {$field} (ítem {$index})";
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
     * Get price comparison between different warehouses
     *
     * @param int $productId Product ID
     * @param array $almacenIds Array of warehouse IDs to compare
     * @return JsonResponse
     */
    public function comparePricesAcrossWarehouses(int $productId, array $almacenIds): JsonResponse
    {
        try {
            $comparison = [];

            foreach ($almacenIds as $almacenId) {
                $productoAlmacen = ProductoAlmacen::where('producto_id', $productId)
                    ->where('almacen_id', $almacenId)
                    ->with(['almacen:id,name', 'unidadesDerivadas.unidadDerivada:id,name'])
                    ->first();

                if ($productoAlmacen) {
                    $comparison[] = [
                        'almacen' => $productoAlmacen->almacen,
                        'costo' => (float) $productoAlmacen->costo,
                        'stock_fraccion' => (float) $productoAlmacen->stock_fraccion,
                        'unidades_derivadas' => $productoAlmacen->unidadesDerivadas->map(function ($ud) {
                            return [
                                'unidad_derivada' => $ud->unidadDerivada->name,
                                'factor' => (float) $ud->factor,
                                'precio_publico' => (float) $ud->precio_publico,
                                'precio_especial' => (float) $ud->precio_especial,
                                'precio_minimo' => (float) $ud->precio_minimo,
                                'precio_ultimo' => $ud->precio_ultimo ? (float) $ud->precio_ultimo : null,
                            ];
                        })
                    ];
                }
            }

            return response()->json([
                'data' => $comparison
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al comparar precios: ' . $e->getMessage()
            ], 500);
        }
    }
}
