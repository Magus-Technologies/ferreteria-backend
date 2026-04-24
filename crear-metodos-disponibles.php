<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CREAR NUEVOS MÉTODOS DE PAGO DISPONIBLES ===\n\n";

$nuevosMetodos = [
    'Efectivo-Caja-7-A',
    'Efectivo-Caja-7-B',
    'Yape-Nuevo',
    'Plin-Nuevo',
    'Transferencia-Nueva',
];

foreach ($nuevosMetodos as $nombre) {
    // Verificar si ya existe
    $existe = DB::table('desplieguedepago')->where('name', $nombre)->exists();
    
    if ($existe) {
        echo "⚠️  Ya existe: {$nombre}\n";
        continue;
    }
    
    try {
        // Crear método de pago base
        $metodoPagoId = (string) \Illuminate\Support\Str::ulid();
        DB::table('metododepago')->insert([
            'id' => $metodoPagoId,
            'name' => $nombre,
            'monto' => 0,
            'activo' => true,
        ]);
        
        // Crear despliegue de pago
        $desplieguePagoId = (string) \Illuminate\Support\Str::ulid();
        DB::table('desplieguedepago')->insert([
            'id' => $desplieguePagoId,
            'name' => $nombre,
            'metodo_de_pago_id' => $metodoPagoId,
            'activo' => true,
            'mostrar' => true,
            'requiere_numero_serie' => false,
            'sobrecargo_porcentaje' => 0,
            'tipo_sobrecargo' => 'ninguno',
            'adicional' => 0,
        ]);
        
        echo "✓ Creado: {$nombre} (ID: {$desplieguePagoId})\n";
    } catch (\Exception $e) {
        echo "❌ Error al crear {$nombre}: " . $e->getMessage() . "\n";
    }
}

// Verificar disponibles para caja 7
echo "\n\nVERIFICACIÓN - Métodos disponibles para Caja Principal 7:\n";
$cajaPrincipalId = 7;

$usadosPorOtras = DB::table('sub_cajas')
    ->where('caja_principal_id', '!=', $cajaPrincipalId)
    ->get()
    ->pluck('despliegues_pago_ids')
    ->map(fn($ids) => json_decode($ids ?? '[]', true))
    ->flatten()
    ->filter(fn($id) => $id !== '*')
    ->unique()
    ->values()
    ->toArray();

$usadosPorMisma = DB::table('sub_cajas')
    ->where('caja_principal_id', $cajaPrincipalId)
    ->where('estado', 1)
    ->get()
    ->pluck('despliegues_pago_ids')
    ->map(fn($ids) => json_decode($ids ?? '[]', true))
    ->flatten()
    ->filter(fn($id) => $id !== '*')
    ->unique()
    ->values()
    ->toArray();

$usedIds = array_values(array_unique(array_merge($usadosPorOtras, $usadosPorMisma)));

$disponibles = DB::table('desplieguedepago')
    ->where('activo', true)
    ->where('mostrar', true)
    ->whereNotIn('id', $usedIds)
    ->get(['id', 'name']);

echo "Total disponibles: " . $disponibles->count() . "\n";
foreach ($disponibles as $d) {
    echo "  ✓ {$d->name}\n";
}

echo "\n=== FIN ===\n";
