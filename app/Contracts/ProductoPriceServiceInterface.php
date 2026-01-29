<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;

interface ProductoPriceServiceInterface
{
    /**
     * Get detailed price information for a product in a specific warehouse
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @return JsonResponse
     */
    public function getProductPriceDetails(int $productId, int $almacenId): JsonResponse;

    /**
     * Update prices for a product in a specific warehouse
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param array $pricesData Array of price data for different units
     * @return JsonResponse
     */
    public function updateProductPrices(int $productId, int $almacenId, array $pricesData): JsonResponse;

    /**
     * Calculate price based on cost and margin
     *
     * @param float $cost Product cost
     * @param float $margin Profit margin percentage
     * @param string $priceType Type of price (publico, especial, minimo, ultimo)
     * @return float Calculated price
     */
    public function calculatePrice(float $cost, float $margin, string $priceType): float;

    /**
     * Get price history for a product
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param int $days Number of days to look back
     * @return JsonResponse
     */
    public function getPriceHistory(int $productId, int $almacenId, int $days = 30): JsonResponse;

    /**
     * Bulk update prices for multiple products
     *
     * @param array $productsData Array of products with their new price data
     * @return JsonResponse
     */
    public function bulkUpdatePrices(array $productsData): JsonResponse;

    /**
     * Apply price adjustments (increase/decrease by percentage or amount)
     *
     * @param array $productIds Array of product IDs
     * @param int $almacenId Warehouse ID
     * @param array $adjustment Adjustment data (type, value, price_types)
     * @return JsonResponse
     */
    public function applyPriceAdjustment(array $productIds, int $almacenId, array $adjustment): JsonResponse;

    /**
     * Get products with price inconsistencies or issues
     *
     * @param int $almacenId Warehouse ID
     * @return JsonResponse
     */
    public function getProductsWithPriceIssues(int $almacenId): JsonResponse;

    /**
     * Calculate commission for a sale based on price type and quantity
     *
     * @param int $productId Product ID
     * @param int $almacenId Warehouse ID
     * @param string $priceType Type of price used
     * @param float $quantity Quantity sold
     * @return array Commission calculation details
     */
    public function calculateCommission(int $productId, int $almacenId, string $priceType, float $quantity): array;

    /**
     * Validate price data before saving
     *
     * @param array $pricesData Price data to validate
     * @return array Validation results with errors and warnings
     */
    public function validatePriceData(array $pricesData): array;

    /**
     * Get price comparison between different warehouses
     *
     * @param int $productId Product ID
     * @param array $almacenIds Array of warehouse IDs to compare
     * @return JsonResponse
     */
    public function comparePricesAcrossWarehouses(int $productId, array $almacenIds): JsonResponse;
}
