<?php
/**
 * Script de diagnóstico para medir el NUEVO endpoint.
 * Carga TODOS los 5167 productos del almacen 1 con el shape LIGERO.
 *
 * USO: php scripts/test-nuevo-endpoint.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Test NUEVO endpoint: listado-modal ===\n\n";

$startAll = microtime(true);

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
                            'precio_publico', 'comision_publico',
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

$elapsed = round((microtime(true) - $startAll) * 1000, 2);
$queries = count(DB::getQueryLog());
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);

echo "Tiempo total: {$elapsed}ms\n";
echo "Productos devueltos: " . $productos->count() . "\n";
echo "Mem peak: {$memory} MB\n";

// Tamaño de la respuesta JSON simulada
$json = json_encode($productos->take(100));
echo "Tamaño JSON de 100 productos: " . round(strlen($json) / 1024, 1) . " KB\n";
$fullJson = json_encode($productos);
echo "Tamaño JSON completo: " . round(strlen($fullJson) / 1024 / 1024, 2) . " MB\n";
