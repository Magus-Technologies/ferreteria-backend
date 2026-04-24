<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST CREAR MÉTODO DE PAGO (Simulando UI) ===\n\n";

$nombreTest = "Test-UI-" . time();
echo "Creando método de pago: {$nombreTest}\n\n";

try {
    // Simular lo que hace el controlador MetodoDePagoController::store
    $validated = [
        'name' => $nombreTest,
        'cuenta_bancaria' => 'SIN-CUENTA',
        'nombre_titular' => null,
        'monto_inicial' => 0,
    ];
    
    $validated['id'] = (string) \Illuminate\Support\Str::ulid();
    $validated['monto'] = 0;
    $validated['monto_inicial'] = 0;
    $validated['activo'] = true;
    
    // Crear método de pago
    $metodoPago = \App\Models\MetodoDePago::create($validated);
    echo "✓ MetodoDePago creado: {$metodoPago->id}\n";
    
    // Crear despliegue automáticamente
    $desplieguePagoId = (string) \Illuminate\Support\Str::ulid();
    $despliegue = \App\Models\DespliegueDePago::create([
        'id' => $desplieguePagoId,
        'name' => $validated['name'],
        'metodo_de_pago_id' => $metodoPago->id,
        'activo' => true,
        'mostrar' => true,
        'requiere_numero_serie' => false,
        'sobrecargo_porcentaje' => 0,
        'tipo_sobrecargo' => 'ninguno',
        'adicional' => 0,
    ]);
    echo "✓ DespliegueDePago creado: {$despliegue->id}\n\n";
    
    // Verificar que aparece en la consulta de sub-cajas
    echo "Verificando disponibilidad para Caja Principal 7:\n";
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
    
    $disponible = DB::table('desplieguedepago')
        ->where('id', $despliegue->id)
        ->where('activo', true)
        ->where('mostrar', true)
        ->whereNotIn('id', $usedIds)
        ->exists();
    
    if ($disponible) {
        echo "✓ ÉXITO: El método '{$nombreTest}' está disponible para crear sub-cajas\n";
    } else {
        echo "❌ ERROR: El método NO está disponible\n";
    }
    
    // Contar total disponibles
    $totalDisponibles = DB::table('desplieguedepago')
        ->where('activo', true)
        ->where('mostrar', true)
        ->whereNotIn('id', $usedIds)
        ->count();
    
    echo "\nTotal de métodos disponibles para Caja Principal 7: {$totalDisponibles}\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIN ===\n";
