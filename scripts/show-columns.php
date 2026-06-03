<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM productoalmacenunidadderivada');
foreach ($columns as $col) {
    echo $col->Field . ' (' . $col->Type . ')' . PHP_EOL;
}
