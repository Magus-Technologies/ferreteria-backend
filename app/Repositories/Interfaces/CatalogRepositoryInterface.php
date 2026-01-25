<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

interface CatalogRepositoryInterface
{
    // ==================== CATEGORIAS ====================

    /**
     * Find or create a category by name
     */
    public function findOrCreateCategoria(string $name): int;

    /**
     * Find category by ID
     */
    public function findCategoriaById(int $id): ?object;

    /**
     * Find category by name
     */
    public function findCategoriaByName(string $name): ?object;

    /**
     * Get all categories
     */
    public function getAllCategorias(): Collection;

    /**
     * Get categories cache (bulk lookup by names)
     */
    public function getCategoriaCache(array $names): array;

    // ==================== MARCAS ====================

    /**
     * Find or create a brand by name
     */
    public function findOrCreateMarca(string $name): int;

    /**
     * Find brand by ID
     */
    public function findMarcaById(int $id): ?object;

    /**
     * Find brand by name
     */
    public function findMarcaByName(string $name): ?object;

    /**
     * Get all brands
     */
    public function getAllMarcas(): Collection;

    /**
     * Get brands cache (bulk lookup by names)
     */
    public function getMarcaCache(array $names): array;

    // ==================== UNIDADES DE MEDIDA ====================

    /**
     * Find or create a unit of measure by name
     */
    public function findOrCreateUnidadMedida(string $name): int;

    /**
     * Find unit of measure by ID
     */
    public function findUnidadMedidaById(int $id): ?object;

    /**
     * Find unit of measure by name
     */
    public function findUnidadMedidaByName(string $name): ?object;

    /**
     * Get all units of measure
     */
    public function getAllUnidadesMedida(): Collection;

    /**
     * Get units of measure cache (bulk lookup by names)
     */
    public function getUnidadMedidaCache(array $names): array;

    // ==================== UNIDADES DERIVADAS ====================

    /**
     * Find or create a derived unit by name
     */
    public function findOrCreateUnidadDerivada(string $name): int;

    /**
     * Find derived unit by ID
     */
    public function findUnidadDerivadaById(int $id): ?object;

    /**
     * Find derived unit by name
     */
    public function findUnidadDerivadaByName(string $name): ?object;

    /**
     * Get all derived units
     */
    public function getAllUnidadesDerivadas(): Collection;

    /**
     * Get derived units cache (bulk lookup by names)
     */
    public function getUnidadDerivadaCache(array $names): array;

    // ==================== UBICACIONES ====================

    /**
     * Find or create a location by name and warehouse
     */
    public function findOrCreateUbicacion(string $name, int $almacenId): int;

    /**
     * Find location by ID
     */
    public function findUbicacionById(int $id): ?object;

    /**
     * Get all locations for a warehouse
     */
    public function getUbicacionesByAlmacen(int $almacenId): Collection;

    // ==================== BULK OPERATIONS ====================

    /**
     * Prepare complete catalog cache for import operations
     * Returns array with categorias, marcas, unidades_medida, unidades_derivadas
     */
    public function prepareCatalogCache(array $data): array;

    /**
     * Clear catalog cache
     */
    public function clearCache(): void;
}
