<?php

namespace App\Repositories\Implementations;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\UnidadMedida;
use App\Models\UnidadDerivada;
use App\Models\Ubicacion;
use App\Repositories\Interfaces\CatalogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogRepository implements CatalogRepositoryInterface
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'catalog_';

    // ==================== CATEGORIAS ====================

    /**
     * Find or create a category by name
     */
    public function findOrCreateCategoria(string $name): int
    {
        $name = trim($name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Category name cannot be empty');
        }

        $categoria = Categoria::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );

        return $categoria->id;
    }

    /**
     * Find category by ID
     */
    public function findCategoriaById(int $id): ?object
    {
        return Categoria::find($id);
    }

    /**
     * Find category by name
     */
    public function findCategoriaByName(string $name): ?object
    {
        return Categoria::where('name', trim($name))->first();
    }

    /**
     * Get all categories
     */
    public function getAllCategorias(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . 'categorias_all', self::CACHE_TTL, function () {
            return Categoria::orderBy('name')->get();
        });
    }

    /**
     * Get categories cache (bulk lookup by names)
     */
    public function getCategoriaCache(array $names): array
    {
        $names = array_unique(array_filter(array_map('trim', $names)));

        if (empty($names)) {
            return [];
        }

        $categorias = Categoria::whereIn('name', $names)->get();
        $cache = [];

        foreach ($categorias as $categoria) {
            $cache[strtolower($categoria->name)] = $categoria->id;
        }

        return $cache;
    }

    // ==================== MARCAS ====================

    /**
     * Find or create a brand by name
     */
    public function findOrCreateMarca(string $name): int
    {
        $name = trim($name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Brand name cannot be empty');
        }

        $marca = Marca::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );

        return $marca->id;
    }

    /**
     * Find brand by ID
     */
    public function findMarcaById(int $id): ?object
    {
        return Marca::find($id);
    }

    /**
     * Find brand by name
     */
    public function findMarcaByName(string $name): ?object
    {
        return Marca::where('name', trim($name))->first();
    }

    /**
     * Get all brands
     */
    public function getAllMarcas(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . 'marcas_all', self::CACHE_TTL, function () {
            return Marca::orderBy('name')->get();
        });
    }

    /**
     * Get brands cache (bulk lookup by names)
     */
    public function getMarcaCache(array $names): array
    {
        $names = array_unique(array_filter(array_map('trim', $names)));

        if (empty($names)) {
            return [];
        }

        $marcas = Marca::whereIn('name', $names)->get();
        $cache = [];

        foreach ($marcas as $marca) {
            $cache[strtolower($marca->name)] = $marca->id;
        }

        return $cache;
    }

    // ==================== UNIDADES DE MEDIDA ====================

    /**
     * Find or create a unit of measure by name
     */
    public function findOrCreateUnidadMedida(string $name): int
    {
        $name = trim($name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Unit of measure name cannot be empty');
        }

        $unidad = UnidadMedida::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );

        return $unidad->id;
    }

    /**
     * Find unit of measure by ID
     */
    public function findUnidadMedidaById(int $id): ?object
    {
        return UnidadMedida::find($id);
    }

    /**
     * Find unit of measure by name
     */
    public function findUnidadMedidaByName(string $name): ?object
    {
        return UnidadMedida::where('name', trim($name))->first();
    }

    /**
     * Get all units of measure
     */
    public function getAllUnidadesMedida(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . 'unidades_medida_all', self::CACHE_TTL, function () {
            return UnidadMedida::orderBy('name')->get();
        });
    }

    /**
     * Get units of measure cache (bulk lookup by names)
     */
    public function getUnidadMedidaCache(array $names): array
    {
        $names = array_unique(array_filter(array_map('trim', $names)));

        if (empty($names)) {
            return [];
        }

        $unidades = UnidadMedida::whereIn('name', $names)->get();
        $cache = [];

        foreach ($unidades as $unidad) {
            $cache[strtolower($unidad->name)] = $unidad->id;
        }

        return $cache;
    }

    // ==================== UNIDADES DERIVADAS ====================

    /**
     * Find or create a derived unit by name
     */
    public function findOrCreateUnidadDerivada(string $name): int
    {
        $name = trim($name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Derived unit name cannot be empty');
        }

        $unidad = UnidadDerivada::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );

        return $unidad->id;
    }

    /**
     * Find derived unit by ID
     */
    public function findUnidadDerivadaById(int $id): ?object
    {
        return UnidadDerivada::find($id);
    }

    /**
     * Find derived unit by name
     */
    public function findUnidadDerivadaByName(string $name): ?object
    {
        return UnidadDerivada::where('name', trim($name))->first();
    }

    /**
     * Get all derived units
     */
    public function getAllUnidadesDerivadas(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . 'unidades_derivadas_all', self::CACHE_TTL, function () {
            return UnidadDerivada::orderBy('name')->get();
        });
    }

    /**
     * Get derived units cache (bulk lookup by names)
     */
    public function getUnidadDerivadaCache(array $names): array
    {
        $names = array_unique(array_filter(array_map('trim', $names)));

        if (empty($names)) {
            return [];
        }

        $unidades = UnidadDerivada::whereIn('name', $names)->get();
        $cache = [];

        foreach ($unidades as $unidad) {
            $cache[strtolower($unidad->name)] = $unidad->id;
        }

        return $cache;
    }

    // ==================== UBICACIONES ====================

    /**
     * Find or create a location by name and warehouse
     */
    public function findOrCreateUbicacion(string $name, int $almacenId): int
    {
        $name = trim($name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Location name cannot be empty');
        }

        $ubicacion = Ubicacion::firstOrCreate(
            ['name' => $name, 'almacen_id' => $almacenId],
            ['name' => $name, 'almacen_id' => $almacenId]
        );

        return $ubicacion->id;
    }

    /**
     * Find location by ID
     */
    public function findUbicacionById(int $id): ?object
    {
        return Ubicacion::find($id);
    }

    /**
     * Get all locations for a warehouse
     */
    public function getUbicacionesByAlmacen(int $almacenId): Collection
    {
        return Ubicacion::where('almacen_id', $almacenId)
            ->orderBy('name')
            ->get();
    }

    // ==================== BULK OPERATIONS ====================

    /**
     * Prepare complete catalog cache for import operations
     * Returns array with categorias, marcas, unidades_medida, unidades_derivadas
     */
    public function prepareCatalogCache(array $data): array
    {
        $categorias = [];
        $marcas = [];
        $unidadesMedida = [];
        $unidadesDerivadas = [];

        // Extract unique names from import data
        foreach ($data as $item) {
            if (!empty($item['categoria'])) {
                $categorias[] = $item['categoria'];
            }
            if (!empty($item['marca'])) {
                $marcas[] = $item['marca'];
            }
            if (!empty($item['unidad_medida'])) {
                $unidadesMedida[] = $item['unidad_medida'];
            }
            if (!empty($item['unidades_derivadas']) && is_array($item['unidades_derivadas'])) {
                foreach ($item['unidades_derivadas'] as $ud) {
                    if (!empty($ud['name'])) {
                        $unidadesDerivadas[] = $ud['name'];
                    }
                }
            }
        }

        return [
            'categorias' => $this->getCategoriaCache($categorias),
            'marcas' => $this->getMarcaCache($marcas),
            'unidades_medida' => $this->getUnidadMedidaCache($unidadesMedida),
            'unidades_derivadas' => $this->getUnidadDerivadaCache($unidadesDerivadas),
        ];
    }

    /**
     * Clear catalog cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'categorias_all');
        Cache::forget(self::CACHE_PREFIX . 'marcas_all');
        Cache::forget(self::CACHE_PREFIX . 'unidades_medida_all');
        Cache::forget(self::CACHE_PREFIX . 'unidades_derivadas_all');
    }
}
