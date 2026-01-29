<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de cache para productos
 * 
 * Usa la tabla 'cache' de MySQL para almacenar resultados
 * y mejorar el performance de consultas pesadas
 */
class ProductoCacheService
{
    /**
     * Tiempo de cache en segundos (5 minutos)
     */
    const CACHE_TTL = 300;

    /**
     * Tamaño máximo de respuesta para cachear (5MB en bytes)
     * Respuestas más grandes no se cachearán para evitar errores
     */
    const MAX_CACHE_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Obtener productos por almacén con cache
     * 
     * @param int $almacenId
     * @param array $filters
     * @param int $perPage
     * @param callable $callback
     * @return mixed
     */
    public function getProductosByAlmacen(int $almacenId, array $filters, int $perPage, callable $callback)
    {
        // Si se solicitan muchos productos, no usar cache (evita error de tamaño)
        if ($perPage > 1000) {
            // \Log::info("Cache deshabilitado: per_page={$perPage} es muy grande");
            $startTime = microtime(true);
            $result = $callback();
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            // \Log::info("Query ejecutada sin cache: {$duration}ms");
            return $result;
        }

        // Generar clave de cache única basada en parámetros
        $cacheKey = $this->generateCacheKey('productos_almacen', [
            'almacen_id' => $almacenId,
            'filters' => $filters,
            'per_page' => $perPage,
        ]);

        // Intentar obtener del cache
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            // \Log::info("Datos obtenidos del cache: {$cacheKey}");
            return $cached;
        }

        // Si no está en cache, ejecutar la query
        $startTime = microtime(true);
        $result = $callback();
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // \Log::info("Query ejecutada (no cache): {$duration}ms");
        
        // Verificar tamaño antes de cachear
        $serialized = serialize($result);
        $size = strlen($serialized);
        
        if ($size > self::MAX_CACHE_SIZE) {
            \Log::warning("Respuesta muy grande para cachear: " . round($size / 1024 / 1024, 2) . "MB");
            return $result;
        }
        
        // Guardar en cache
        try {
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            // \Log::info("Datos guardados en cache: {$cacheKey} (" . round($size / 1024, 2) . "KB)");
        } catch (\Exception $e) {
            \Log::error("Error al guardar en cache: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Invalidar cache de productos por almacén
     * 
     * @param int $almacenId
     * @return void
     */
    public function invalidateProductosAlmacen(int $almacenId): void
    {
        // Limpiar todos los caches relacionados con este almacén
        $pattern = "productos_almacen_{$almacenId}_*";
        
        // Obtener todas las claves que coincidan
        $keys = DB::table('cache')
            ->where('key', 'like', $pattern)
            ->pluck('key');
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        // \Log::info("Cache invalidado para almacén {$almacenId}");
    }

    /**
     * Invalidar todo el cache de productos
     * 
     * @return void
     */
    public function invalidateAll(): void
    {
        $pattern = "productos_almacen_%";
        
        $keys = DB::table('cache')
            ->where('key', 'like', $pattern)
            ->pluck('key');
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        // \Log::info("Todo el cache de productos invalidado");
    }

    /**
     * Generar clave de cache única
     * 
     * @param string $prefix
     * @param array $params
     * @return string
     */
    private function generateCacheKey(string $prefix, array $params): string
    {
        // Ordenar parámetros para consistencia
        ksort($params);
        
        // Generar hash de parámetros
        $hash = md5(json_encode($params));
        
        return "{$prefix}_{$params['almacen_id']}_{$hash}";
    }

    /**
     * Obtener estadísticas de cache
     * 
     * @return array
     */
    public function getStats(): array
    {
        $totalKeys = DB::table('cache')
            ->where('key', 'like', 'productos_almacen_%')
            ->count();
        
        $totalSize = DB::table('cache')
            ->where('key', 'like', 'productos_almacen_%')
            ->sum(DB::raw('LENGTH(value)'));
        
        return [
            'total_keys' => $totalKeys,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        ];
    }
}
