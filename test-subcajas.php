<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST DE SUB-CAJAS Y MÉTODOS DE PAGO ===\n\n";

// 1. Ver métodos de pago disponibles
echo "1. MÉTODOS DE PAGO ACTIVOS (mostrar=true):\n";
$metodos = DB::table('desplieguedepago')
    ->where('activo', true)
    ->where('mostrar', true)
    ->get(['id', 'name']);
echo "Total: " . $metodos->count() . " métodos\n";
foreach ($metodos as $m) {
    echo "  - {$m->name} (ID: {$m->id})\n";
}

// 2. Ver sub-cajas existentes
echo "\n2. SUB-CAJAS EXISTENTES:\n";
$subcajas = DB::table('sub_cajas')->get();
echo "Total: " . $subcajas->count() . " sub-cajas\n";
foreach ($subcajas as $sc) {
    $metodos_ids = json_decode($sc->despliegues_pago_ids ?? '[]', true);
    $estado_text = $sc->estado == 1 ? 'ACTIVA' : 'INACTIVA';
    echo "  - {$sc->nombre} (Caja Principal: {$sc->caja_principal_id}) [{$estado_text}]\n";
    echo "    Métodos asignados: " . json_encode($metodos_ids) . "\n";
}

// 3. Simular filtro de exclusión para caja principal ID 1
echo "\n3. SIMULACIÓN DE FILTRO (exclude_used_by_caja_principal_id=1):\n";
$cajaPrincipalId = 1;

// Métodos usados por otras cajas
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

echo "Métodos usados por OTRAS cajas principales: " . json_encode($usadosPorOtras) . "\n";

// Métodos usados por sub-cajas ACTIVAS de la misma caja
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

echo "Métodos usados por sub-cajas ACTIVAS de la misma caja: " . json_encode($usadosPorMisma) . "\n";

$usedIds = array_values(array_unique(array_merge($usadosPorOtras, $usadosPorMisma)));
echo "Total de métodos a EXCLUIR: " . count($usedIds) . "\n";
echo "IDs excluidos: " . json_encode($usedIds) . "\n";

// Métodos disponibles
$disponibles = DB::table('desplieguedepago')
    ->where('activo', true)
    ->where('mostrar', true)
    ->whereNotIn('id', $usedIds)
    ->get(['id', 'name']);

echo "\nMétodos DISPONIBLES para nueva sub-caja: " . $disponibles->count() . "\n";
foreach ($disponibles as $d) {
    echo "  ✓ {$d->name} (ID: {$d->id})\n";
}

echo "\n=== FIN DEL TEST ===\n";
