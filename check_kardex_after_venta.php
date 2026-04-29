<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN DE KARDEX DESPUÉS DE CREAR VENTA ===\n\n";

// Verificar kardex_facturacions
$kardexFacturacion = DB::table('kardex_facturacions')->get();
echo "Total registros en kardex_facturacions: " . $kardexFacturacion->count() . "\n\n";

if ($kardexFacturacion->count() > 0) {
    echo "Registros encontrados:\n";
    foreach ($kardexFacturacion as $registro) {
        echo "  - ID: {$registro->id}\n";
        echo "    Venta ID: {$registro->referencia_id}\n";
        echo "    Producto: {$registro->producto_nombre}\n";
        echo "    Cliente: {$registro->cliente_nombre}\n";
        echo "    Movimiento: {$registro->movimiento}\n";
        echo "    Fecha: {$registro->fecha}\n\n";
    }
} else {
    echo "❌ NO hay registros en kardex_facturacions\n\n";
}

// Verificar la venta que acabas de crear
$ventaId = '01KQD3HACAJQBAMWAH8F1WD5BJ';
$venta = DB::table('venta')->where('id', $ventaId)->first();

if ($venta) {
    echo "=== VENTA ENCONTRADA ===\n";
    echo "ID: {$venta->id}\n";
    echo "Estado: {$venta->estado_de_venta}\n";
    echo "Cliente ID: {$venta->cliente_id}\n";
    echo "Almacén ID: {$venta->almacen_id}\n";
    echo "Fecha: {$venta->fecha}\n\n";
    
    // Verificar productos de la venta
    $productos = DB::table('producto_almacen_ventas')->where('venta_id', $ventaId)->get();
    echo "Productos en la venta: " . $productos->count() . "\n";
    foreach ($productos as $prod) {
        echo "  - Producto Almacén ID: {$prod->producto_almacen_id}\n";
    }
} else {
    echo "❌ Venta NO encontrada\n";
}
