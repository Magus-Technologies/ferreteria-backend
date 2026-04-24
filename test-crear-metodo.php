<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

echo "=== TEST CREAR MÉTODO DE PAGO ===\n\n";

// Simular request POST
$nombreTest = "Test-Metodo-" . time();
echo "Creando método: {$nombreTest}\n\n";

$request = \Illuminate\Http\Request::create(
    '/api/despliegues-de-pago',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode([
        'name' => $nombreTest,
        'mostrar' => true,
        'requiere_numero_serie' => false,
        'sobrecargo_porcentaje' => 0,
        'tipo_sobrecargo' => 'ninguno',
        'adicional' => 0
    ])
);

$response = $kernel->handle($request);
$data = json_decode($response->getContent(), true);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

if (isset($data['data']['id'])) {
    $id = $data['data']['id'];
    echo "✓ Método creado con ID: {$id}\n";
    
    // Verificar que aparece en la lista
    echo "\nVerificando que aparece en GET /despliegues-de-pago?mostrar=1:\n";
    $request2 = \Illuminate\Http\Request::create('/api/despliegues-de-pago?mostrar=1', 'GET');
    $response2 = $kernel->handle($request2);
    $data2 = json_decode($response2->getContent(), true);
    
    $encontrado = false;
    foreach ($data2['data'] ?? [] as $metodo) {
        if ($metodo['id'] === $id) {
            $encontrado = true;
            echo "✓ Método encontrado en la lista:\n";
            echo "  - ID: {$metodo['id']}\n";
            echo "  - Name: {$metodo['name']}\n";
            echo "  - Mostrar: " . ($metodo['mostrar'] ? 'true' : 'false') . "\n";
            echo "  - Activo: " . ($metodo['activo'] ? 'true' : 'false') . "\n";
            break;
        }
    }
    
    if (!$encontrado) {
        echo "❌ ERROR: Método NO encontrado en la lista\n";
    }
} else {
    echo "❌ ERROR: No se obtuvo ID en la respuesta\n";
}

echo "\n=== FIN ===\n";
