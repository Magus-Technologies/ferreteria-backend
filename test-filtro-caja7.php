<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST FILTRO PARA CAJA PRINCIPAL 7 ===\n\n";

$cajaPrincipalId = 7;

// Métodos usados por OTRAS cajas principales
echo "1. Métodos usados por OTRAS cajas principales (!=7):\n";
$usadosPorOtras = DB::table('sub_cajas')
    ->where('caja_principal_id', '!=', $cajaPrincipalId)
    ->get();

echo "Sub-cajas de otras cajas: " . $usadosPorOtras->count() . "\n";
foreach ($usadosPorOtras as $sc) {
    $metodos = json_decode($sc->despliegues_pago_ids ?? '[]', true);
    echo "  - {$sc->nombre} (Caja {$sc->caja_principal_id}): " . json_encode($metodos) . "\n";
}

$metodosOtras = $usadosPorOtras
    ->pluck('despliegues_pago_ids')
    ->map(fn($ids) => json_decode($ids ?? '[]', true))
    ->flatten()
    ->filter(fn($id) => $id !== '*')
    ->unique()
    ->values()
    ->toArray();

echo "IDs de métodos usados por otras cajas: " . json_encode($metodosOtras) . "\n";

// Métodos usados por sub-cajas ACTIVAS de la MISMA caja
echo "\n2. Métodos usados por sub-cajas ACTIVAS de la caja 7:\n";
$usadosPorMisma = DB::table('sub_cajas')
    ->where('caja_principal_id', $cajaPrincipalId)
    ->where('estado', 1)
    ->get();

echo "Sub-cajas activas de caja 7: " . $usadosPorMisma->count() . "\n";
foreach ($usadosPorMisma as $sc) {
    $metodos = json_decode($sc->despliegues_pago_ids ?? '[]', true);
    echo "  - {$sc->nombre}: " . json_encode($metodos) . "\n";
}

$metodosMisma = $usadosPorMisma
    ->pluck('despliegues_pago_ids')
    ->map(fn($ids) => json_decode($ids ?? '[]', true))
    ->flatten()
    ->filter(fn($id) => $id !== '*')
    ->unique()
    ->values()
    ->toArray();

echo "IDs de métodos usados por caja 7: " . json_encode($metodosMisma) . "\n";

// Combinar
$usedIds = array_values(array_unique(array_merge($metodosOtras, $metodosMisma)));
echo "\n3. TOTAL DE IDs A EXCLUIR: " . count($usedIds) . "\n";
echo "IDs: " . json_encode($usedIds) . "\n";

// Consultar disponibles
echo "\n4. MÉTODOS DISPONIBLES:\n";
$query = DB::table('desplieguedepago')
    ->where('activo', true)
    ->where('mostrar', true);

if (!empty($usedIds)) {
    $query->whereNotIn('id', $usedIds);
}

$disponibles = $query->get(['id', 'name']);

echo "Total disponibles: " . $disponibles->count() . "\n";
foreach ($disponibles as $d) {
    echo "  ✓ {$d->name} (ID: {$d->id})\n";
}

echo "\n=== FIN ===\n";
