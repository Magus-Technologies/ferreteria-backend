<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Migraciones a verificar
$migracionesAVerificar = [
    '2026_02_09_200000_fix_cdr_xml_to_longblob',
    '2026_02_10_000000_create_comprobante_electronico_detalles_table',
    '2026_02_10_010000_fix_vendedor_ids_in_solicitudes_efectivo',
    '2026_02_10_020000_add_sub_caja_columns_to_solicitudes_efectivo',
];

echo "\n=== VERIFICACIÓN DE MIGRACIONES ===\n\n";

foreach ($migracionesAVerificar as $migracion) {
    $existe = DB::table('migrations')
        ->where('migration', $migracion)
        ->exists();
    
    $status = $existe ? '✅ EJECUTADA' : '❌ PENDIENTE';
    
    if ($existe) {
        $batch = DB::table('migrations')
            ->where('migration', $migracion)
            ->value('batch');
        echo "$status - $migracion (Batch: $batch)\n";
    } else {
        echo "$status - $migracion\n";
    }
}

echo "\n=== FIN VERIFICACIÓN ===\n";
