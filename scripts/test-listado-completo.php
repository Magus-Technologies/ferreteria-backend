<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s = $app->make(App\Contracts\ProductoServiceInterface::class);

// Cache miss (primera vez)
$start = microtime(true);
$r = $s->getListadoCompletoPorAlmacen(1);
$dur = round((microtime(true) - $start) * 1000, 2);
$data = json_decode($r->getContent(), true);

echo "=== CACHE MISS ===\n";
echo "Productos: " . count($data['data']) . "\n";
echo "TTFB: {$dur} ms\n";
echo "Size: " . round(strlen($r->getContent()) / 1024 / 1024, 2) . " MB\n\n";

// Cache hit (segunda vez)
$start = microtime(true);
$r = $s->getListadoCompletoPorAlmacen(1);
$dur = round((microtime(true) - $start) * 1000, 2);
echo "=== CACHE HIT ===\n";
echo "TTFB: {$dur} ms\n";
echo "Size: " . round(strlen($r->getContent()) / 1024 / 1024, 2) . " MB\n";
