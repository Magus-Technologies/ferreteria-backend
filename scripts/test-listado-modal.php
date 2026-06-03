<?php
/**
 * Script de diagnóstico para medir el endpoint ANTES y DESPUÉS.
 *
 * USO: php scripts/test-listado-modal.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Test de performance: listado-modal ===\n\n";

// 1) Cuántos productos hay
$totalProductos = DB::table('producto')->where('estado', 1)->count();
echo "Total productos activos: {$totalProductos}\n";

$almacenes = DB::table('almacen')->limit(5)->get(['id', 'name']);
echo "Almacenes: \n";
foreach ($almacenes as $a) {
    $count = DB::table('producto as p')
        ->join('productoalmacen as pa', 'pa.producto_id', '=', 'p.id')
        ->where('p.estado', 1)
        ->where('pa.almacen_id', $a->id)
        ->distinct('p.id')
        ->count('p.id');
    echo "  - Almacén {$a->id} ({$a->name}): {$count} productos\n";
}

echo "\n";

// 2) Test del NUEVO endpoint (listado ligero, sin compras ni productoComplementario)
echo "--- NUEVO endpoint (listado-modal) ---\n";
$start = microtime(true);
$productos = App\Models\Producto::select([
    'producto.id', 'producto.cod_producto', 'producto.cod_barra', 'producto.name',
    'producto.name_ticket', 'producto.categoria_id', 'producto.marca_id',
    'producto.unidad_medida_id', 'producto.accion_tecnica',
    'producto.stock_min', 'producto.stock_max', 'producto.unidades_contenidas', 'producto.estado',
])
    ->where('producto.estado', 1)
    ->whereHas('productoEnAlmacenes', function ($q) {
        $q->where('almacen_id', 1);
    })
    ->with([
        'marca:id,name',
        'categoria:id,name',
        'unidadMedida:id,name',
        'productoEnAlmacenes' => function ($q) {
            $q->select('id', 'producto_id', 'almacen_id', 'stock_fraccion', 'costo', 'costo_anterior', 'costo_actual')
                ->where('almacen_id', 1)
                ->with([
                    'unidadesDerivadas' => function ($udq) {
                        $udq->select('id', 'producto_almacen_id', 'unidad_derivada_id', 'factor',
                            'precio_publico', 'comision_publico', 'activador_publico',
                            'precio_especial', 'comision_especial', 'activador_especial',
                            'precio_minimo', 'comision_minimo', 'activador_minimo',
                            'precio_ultimo', 'comision_ultimo', 'activador_ultimo')
                            ->with(['unidadDerivada:id,name'])
                            ->orderBy('orden', 'asc')
                            ->orderBy('factor', 'desc');
                    },
                ]);
        },
    ])
    ->orderBy('producto.name', 'asc')
    ->get();

$elapsed = round((microtime(true) - $start) * 1000, 2);
$queries = count(DB::getQueryLog());
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
echo "  Tiempo: {$elapsed}ms\n";
echo "  Productos devueltos: " . $productos->count() . "\n";
echo "  Mem peak: {$memory} MB\n";

echo "\n";

// 3) Test del VIEJO endpoint (con compras y todas las relaciones)
echo "--- VIEJO endpoint (findByAlmacen) ---\n";
DB::flushQueryLog();
DB::enableQueryLog();

$start = microtime(true);
$query = App\Models\Producto::select([
    'id', 'cod_producto', 'cod_barra', 'name', 'name_ticket',
    'categoria_id', 'marca_id', 'unidad_medida_id', 'accion_tecnica',
    'img', 'ficha_tecnica', 'stock_min', 'stock_max', 'unidades_contenidas',
    'estado', 'permitido',
])
    ->with([
        'marca:id,name', 'categoria:id,name', 'unidadMedida:id,name',
        'productoEnAlmacenes' => function ($q) {
            $q->select('id', 'producto_id', 'almacen_id', 'ubicacion_id', 'stock_fraccion', 'costo',
                'costo_anterior', 'costo_actual', 'stock_costo_anterior', 'stock_costo_actual')
                ->where('almacen_id', 1)
                ->with([
                    'almacen:id,name', 'ubicacion:id,name',
                    'unidadesDerivadas' => function ($udq) {
                        $udq->select('id', 'producto_almacen_id', 'unidad_derivada_id', 'factor',
                            'precio_publico', 'comision_publico', 'precio_especial', 'comision_especial',
                            'activador_especial', 'precio_minimo', 'comision_minimo', 'activador_minimo',
                            'precio_ultimo', 'comision_ultimo', 'activador_ultimo',
                            'producto_complementario_id', 'producto_complementario_cantidad')
                            ->with(['unidadDerivada:id,name', 'productoComplementario:id,name,cod_producto'])
                            ->orderBy('orden', 'asc')
                            ->orderBy('factor', 'desc');
                    },
                    'compras' => function ($cq) {
                        $cq->select('id', 'producto_almacen_id', 'costo', 'compra_id')
                            ->with([
                                'compra:id,fecha,proveedor_id,user_id,tipo_documento,serie,numero',
                                'compra.proveedor:id,razon_social',
                                'compra.user:id,name',
                                'unidadesDerivadas' => function ($udq) {
                                    $udq->select('id', 'producto_almacen_compra_id', 'unidad_derivada_inmutable_id',
                                        'factor', 'cantidad', 'lote', 'vencimiento', 'flete', 'bonificacion')
                                        ->with('unidadDerivadaInmutable:id,name');
                                },
                            ])
                            ->orderBy('id', 'desc')
                            ->limit(6);
                    },
                ]);
        },
    ])
    ->whereHas('productoEnAlmacenes', function ($q) {
        $q->where('almacen_id', 1);
    });

$query->addSelect(DB::raw('(
    EXISTS (SELECT 1 FROM productoalmaceningresosalida pai JOIN productoalmacen pa ON pa.id = pai.producto_almacen_id WHERE pa.producto_id = producto.id)
    OR EXISTS (SELECT 1 FROM productoalmacenventa pav JOIN productoalmacen pa ON pa.id = pav.producto_almacen_id WHERE pa.producto_id = producto.id)
    OR EXISTS (SELECT 1 FROM productoalmacencompra pac JOIN productoalmacen pa ON pa.id = pac.producto_almacen_id WHERE pa.producto_id = producto.id)
) as tiene_ingresos'));

// Tomar solo los primeros 1000 (lo que pedía el front)
$productos = $query->orderBy('name', 'asc')->limit(1000)->get();

$elapsed = round((microtime(true) - $start) * 1000, 2);
$queries = count(DB::getQueryLog());
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
echo "  Tiempo: {$elapsed}ms\n";
echo "  Queries ejecutadas: {$queries}\n";
echo "  Productos devueltos: " . $productos->count() . "\n";
echo "  Mem peak: {$memory} MB\n";
