<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN DE CONEXIÓN ===\n\n";

try {
    $pdo = DB::connection()->getPdo();
    echo "✓ Conexión exitosa\n\n";
    
    // Verificar tablas de kardex
    echo "=== CONTEO DE REGISTROS ===\n";
    $kardexInventario = DB::table('kardex_inventarios')->count();
    $kardexFacturacion = DB::table('kardex_facturacions')->count();
    
    echo "kardex_inventarios: {$kardexInventario}\n";
    echo "kardex_facturacions: {$kardexFacturacion}\n\n";
    
    // Verificar ventas recientes
    echo "=== ÚLTIMAS 5 VENTAS ===\n";
    $ventas = DB::table('venta')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['id', 'serie', 'numero', 'created_at']);
    
    foreach ($ventas as $venta) {
        echo "{$venta->serie}-{$venta->numero} (ID: {$venta->id}) - {$venta->created_at}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
