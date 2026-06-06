<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Repositories\Interfaces\ProductoRepositoryInterface;
use Illuminate\Support\Facades\DB;

function ms($t){ return round((microtime(true)-$t)*1000,1).' ms'; }
$repo = $app->make(ProductoRepositoryInterface::class);
$almacenId = 1;

// total productos del almacen
$total = DB::table('productoalmacen')->where('almacen_id',$almacenId)->count();
echo "Total productos en almacen $almacenId: $total\n\n";

// 1) Una pagina de 1000 (lo que hace hoy mi-almacen)
$t = microtime(true);
$p = $repo->findByAlmacen($almacenId, ['almacen_id'=>$almacenId], 1000);
echo "1) findByAlmacen per_page=1000 (1 pagina): " . $p->count() . " items en " . ms($t) . "\n";
$t = microtime(true);
$json = json_encode($p);
echo "   json_encode pagina: " . round(strlen($json)/1024) . " KB en " . ms($t) . "\n\n";

// 2) TODO de una (per_page enorme)
$t = microtime(true);
$all = $repo->findByAlmacen($almacenId, ['almacen_id'=>$almacenId], 100000);
echo "2) findByAlmacen TODO (per_page=100000): " . $all->count() . " items en " . ms($t) . "\n";
$t = microtime(true);
$jsonAll = json_encode($all);
echo "   json_encode TODO: " . round(strlen($jsonAll)/1024) . " KB en " . ms($t) . "\n\n";

// 3) Caso por defecto: filtro marca ACEROS AREQUIPA
$marca = DB::table('marca')->whereRaw('UPPER(name)=?',['ACEROS AREQUIPA'])->first();
if ($marca) {
    $t = microtime(true);
    $am = $repo->findByAlmacen($almacenId, ['almacen_id'=>$almacenId,'marca_id'=>$marca->id], 100000);
    echo "3) findByAlmacen marca ACEROS AREQUIPA TODO: " . $am->count() . " items en " . ms($t) . "\n";
    $t = microtime(true);
    $jm = json_encode($am);
    echo "   json_encode: " . round(strlen($jm)/1024) . " KB en " . ms($t) . "\n";
}
