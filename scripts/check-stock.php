<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pa = \App\Models\ProductoAlmacen::find(5);
echo "Producto id=5 stock_fraccion = {$pa->stock_fraccion}\n";
