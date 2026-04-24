<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST CREAR MÉTODO DIRECTO ===\n\n";

$nombreTest = "Test-Directo-" . time();
echo "Creando método: {$nombreTest}\n\n";

try {
    // Crear método de pago base
    $metodoPagoId = (string) \Illuminate\Support\Str::ulid();
    DB::table('metododepago')->insert([
        'id' => $metodoPagoId,
        'name' => $nombreTest,
        'monto' => 0,
        'activo' => true,
    ]);
    
    echo "✓ MetodoDePago creado: {$metodoPagoId}\n";
    
    // Crear despliegue de pago
    $desplieguePagoId = (string) \Illuminate\Support\Str::ulid();
    DB::table('desplieguedepago')->insert([
        'id' => $desplieguePagoId,
        'name' => $nombreTest,
        'metodo_de_pago_id' => $metodoPagoId,
        'activo' => true,
        'mostrar' => true,
        'requiere_numero_serie' => false,
        'sobrecargo_porcentaje' => 0,
        'tipo_sobrecargo' => 'ninguno',
        'adicional' => 0,
    ]);
    
    echo "✓ DespliegueDePago creado: {$desplieguePagoId}\n\n";
    
    // Verificar estructura de respuesta que debería retornar el controlador
    echo "Estructura que debería retornar el controlador:\n";
    $respuesta = [
        'data' => [
            'id' => $desplieguePagoId,
            'name' => $nombreTest,
            'label' => $nombreTest,
            'mostrar' => true,
            'activo' => true,
        ],
        'message' => 'Método de pago creado exitosamente'
    ];
    
    echo json_encode($respuesta, JSON_PRETTY_PRINT) . "\n\n";
    
    // Verificar que aparece en consulta
    echo "Verificando en consulta GET:\n";
    $metodo = DB::table('desplieguedepago')
        ->where('id', $desplieguePagoId)
        ->where('mostrar', true)
        ->where('activo', true)
        ->first();
    
    if ($metodo) {
        echo "✓ Encontrado:\n";
        echo "  - ID: {$metodo->id}\n";
        echo "  - Name: {$metodo->name}\n";
        echo "  - Mostrar: " . ($metodo->mostrar ? 'true' : 'false') . "\n";
        echo "  - Activo: " . ($metodo->activo ? 'true' : 'false') . "\n";
    } else {
        echo "❌ NO encontrado\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";
