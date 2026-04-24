<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN DE DESPLIEGUES DE PAGO ===\n\n";

// Ver todos los despliegues
echo "1. TODOS LOS DESPLIEGUES:\n";
$todos = DB::table('desplieguedepago')
    ->select('id', 'name', 'mostrar', 'activo')
    ->get();

echo "Total: " . $todos->count() . "\n\n";
foreach ($todos as $d) {
    $mostrar = $d->mostrar ? '✓ mostrar' : '✗ NO mostrar';
    $activo = $d->activo ? '✓ activo' : '✗ inactivo';
    echo "  {$d->name}\n";
    echo "    ID: {$d->id}\n";
    echo "    {$mostrar} | {$activo}\n\n";
}

// Ver los que cumplen el filtro
echo "\n2. DESPLIEGUES CON mostrar=1 Y activo=1:\n";
$filtrados = DB::table('desplieguedepago')
    ->where('mostrar', true)
    ->where('activo', true)
    ->get(['id', 'name']);

echo "Total: " . $filtrados->count() . "\n";
foreach ($filtrados as $f) {
    echo "  ✓ {$f->name} (ID: {$f->id})\n";
}

// Ver sub-cajas de caja principal 7
echo "\n3. SUB-CAJAS DE CAJA PRINCIPAL 7:\n";
$subcajas7 = DB::table('sub_cajas')
    ->where('caja_principal_id', 7)
    ->get();

if ($subcajas7->count() > 0) {
    foreach ($subcajas7 as $sc) {
        $metodos = json_decode($sc->despliegues_pago_ids ?? '[]', true);
        $estado = $sc->estado == 1 ? 'ACTIVA' : 'INACTIVA';
        echo "  {$sc->nombre} [{$estado}]\n";
        echo "    Métodos: " . json_encode($metodos) . "\n";
    }
} else {
    echo "  No hay sub-cajas para esta caja principal\n";
}

echo "\n=== FIN ===\n";
