<?php
/**
 * Test: invalidación del cache del listado ligero al cambiar stock.
 *
 * Simula: venta (que actualiza stock_fraccion en productoalmacen vía
 * Eloquent) → verifica que el cache "productos_listado_ligero_{id}"
 * se invalida.
 *
 * USO: php scripts/test-cache-invalidation.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;

$almacenId = 1;
$cacheKey = "productos_listado_ligero_{$almacenId}";

echo "=== Test: invalidación de cache al cambiar stock ===\n\n";

// 1) Cargar un producto con stock via Eloquent
$pa = \App\Models\ProductoAlmacen::where('almacen_id', $almacenId)
    ->where('stock_fraccion', '>', 0.5)  // que tenga al menos 0.5
    ->first();

if (!$pa) {
    echo "No hay productos con stock suficiente en almacen {$almacenId}\n";
    exit(0);
}

$stockOriginal = (float) $pa->stock_fraccion;
echo "Producto: id={$pa->id} stock_fraccion={$stockOriginal}\n\n";

// 2) Poblar el cache manualmente
Cache::put($cacheKey, ['data' => 'MOCK_CACHE_VALUE'], 600);
$cached1 = Cache::get($cacheKey);
echo "1) Cache poblado: " . json_encode($cached1) . "\n";

// 3) Simular venta: cambiar stock vía Eloquent (esto SÍ dispara observers)
echo "2) Simulando venta: stock_fraccion -0.5 (vía Eloquent save)\n";
$pa->stock_fraccion = $stockOriginal - 0.5;
$pa->save();

// 4) Verificar que el cache se invalidó
$cached2 = Cache::get($cacheKey);
echo "3) Cache después del save: " . ($cached2 === null ? 'INVALIDADO (null) ✓' : json_encode($cached2) . ' ✗') . "\n";

// 5) Verificar el stock en DB
$newStock = (float) \App\Models\ProductoAlmacen::find($pa->id)->stock_fraccion;
echo "4) Stock en DB ahora: {$newStock}\n\n";

if ($cached2 === null) {
    echo "✅ PASS: El cache se invalidó automáticamente al cambiar stock.\n";
} else {
    echo "❌ FAIL: El cache NO se invalidó. El modal mostraría stock viejo.\n";
}

// 6) Restaurar el stock original
$pa->fresh();
$pa->stock_fraccion = $stockOriginal;
$pa->save();
echo "(stock restaurado al original: {$stockOriginal})\n";
