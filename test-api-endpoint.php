<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

echo "=== TEST DEL ENDPOINT API /despliegues-de-pago ===\n\n";

// Test 1: Sin filtros
echo "1. GET /despliegues-de-pago?mostrar=1\n";
$request1 = \Illuminate\Http\Request::create('/despliegues-de-pago?mostrar=1', 'GET');
$response1 = $kernel->handle($request1);
$data1 = json_decode($response1->getContent(), true);
echo "   Status: " . $response1->getStatusCode() . "\n";
echo "   Total métodos: " . count($data1['data'] ?? []) . "\n";
foreach (($data1['data'] ?? []) as $m) {
    echo "     - {$m['name']} (ID: {$m['id']})\n";
}

// Test 2: Con filtro de caja principal 1
echo "\n2. GET /despliegues-de-pago?mostrar=1&exclude_used_by_caja_principal_id=1\n";
$request2 = \Illuminate\Http\Request::create('/despliegues-de-pago?mostrar=1&exclude_used_by_caja_principal_id=1', 'GET');
$response2 = $kernel->handle($request2);
$data2 = json_decode($response2->getContent(), true);
echo "   Status: " . $response2->getStatusCode() . "\n";
echo "   Total métodos disponibles: " . count($data2['data'] ?? []) . "\n";
foreach (($data2['data'] ?? []) as $m) {
    echo "     ✓ {$m['name']} (ID: {$m['id']})\n";
}

// Test 3: Con filtro de caja principal 5 (la que tiene la sub-caja)
echo "\n3. GET /despliegues-de-pago?mostrar=1&exclude_used_by_caja_principal_id=5\n";
$request3 = \Illuminate\Http\Request::create('/despliegues-de-pago?mostrar=1&exclude_used_by_caja_principal_id=5', 'GET');
$response3 = $kernel->handle($request3);
$data3 = json_decode($response3->getContent(), true);
echo "   Status: " . $response3->getStatusCode() . "\n";
echo "   Total métodos disponibles: " . count($data3['data'] ?? []) . "\n";
echo "   (Debería excluir 'efectivo-caja-p2' porque está en una sub-caja activa de esta caja)\n";
foreach (($data3['data'] ?? []) as $m) {
    echo "     ✓ {$m['name']} (ID: {$m['id']})\n";
}

// Verificar que efectivo-caja-p2 NO está en la lista
$ids3 = array_column($data3['data'] ?? [], 'id');
if (!in_array('01KQ0881SMF8BEDZY0XXW539PW', $ids3)) {
    echo "   ✓ CORRECTO: 'efectivo-caja-p2' está excluido\n";
} else {
    echo "   ❌ ERROR: 'efectivo-caja-p2' NO debería estar disponible\n";
}

echo "\n=== FIN DEL TEST ===\n";
