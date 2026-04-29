<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ÚLTIMAS 10 VENTAS ===\n\n";

$ventas = DB::table('venta')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'serie', 'numero', 'cliente_id', 'estado_de_venta', 'created_at']);

echo "Total ventas encontradas: " . $ventas->count() . "\n\n";

foreach ($ventas as $venta) {
    echo "ID: {$venta->id}\n";
    echo "  Serie-Número: {$venta->serie}-{$venta->numero}\n";
    echo "  Cliente ID: {$venta->cliente_id}\n";
    echo "  Estado: {$venta->estado_de_venta}\n";
    echo "  Creada: {$venta->created_at}\n\n";
}

// Buscar específicamente la venta que creaste
echo "=== BUSCANDO VENTA ESPECÍFICA ===\n";
$ventaEspecifica = DB::table('venta')->where('id', '01KQD3HACAJQBAMWAH8F1WD5BJ')->first();
if ($ventaEspecifica) {
    echo "✓ Venta encontrada: {$ventaEspecifica->serie}-{$ventaEspecifica->numero}\n";
} else {
    echo "❌ Venta NO encontrada con ID: 01KQD3HACAJQBAMWAH8F1WD5BJ\n";
}

// Verificar kardex
echo "\n=== KARDEX FACTURACIÓN ===\n";
$kardex = DB::table('kardex_facturacions')->count();
echo "Total registros: {$kardex}\n";
