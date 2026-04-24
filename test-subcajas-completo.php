<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST COMPLETO DE SUB-CAJAS Y MÉTODOS DE PAGO ===\n\n";

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

// 3. Probar el endpoint GET /despliegues-de-pago con filtro
echo "\n3. TEST DEL ENDPOINT GET /despliegues-de-pago:\n";

// Simular request para caja principal 1
$cajaPrincipalId = 1;
echo "Probando con caja_principal_id = {$cajaPrincipalId}\n";

// Lógica del controlador
$query = DB::table('desplieguedepago')
    ->where('activo', true)
    ->where('mostrar', true);

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

$usedIds = array_values(array_unique(array_merge($usadosPorOtras, $usadosPorMisma)));

echo "  Métodos excluidos (usados por otras cajas): " . count($usadosPorOtras) . "\n";
echo "  Métodos excluidos (usados por sub-cajas activas de esta caja): " . count($usadosPorMisma) . "\n";
echo "  Total de métodos excluidos: " . count($usedIds) . "\n";

if (!empty($usedIds)) {
    $query->whereNotIn('id', $usedIds);
}

$disponibles = $query->get(['id', 'name']);

echo "\n  Métodos DISPONIBLES: " . $disponibles->count() . "\n";
foreach ($disponibles as $d) {
    echo "    ✓ {$d->name} (ID: {$d->id})\n";
}

// 4. Probar creación de nuevo método de pago
echo "\n4. TEST DE CREACIÓN DE NUEVO MÉTODO DE PAGO:\n";
$nuevoNombre = "Efectivo-Test-" . time();
echo "Intentando crear método: {$nuevoNombre}\n";

try {
    // Verificar si ya existe
    $existe = DB::table('desplieguedepago')
        ->where('name', $nuevoNombre)
        ->exists();
    
    if ($existe) {
        echo "  ⚠️  Ya existe un método con este nombre\n";
    } else {
        // Crear método de pago base
        $metodoPagoId = (string) \Illuminate\Support\Str::ulid();
        DB::table('metododepago')->insert([
            'id' => $metodoPagoId,
            'name' => $nuevoNombre,
            'monto' => 0,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Crear despliegue de pago
        $desplieguePagoId = (string) \Illuminate\Support\Str::ulid();
        DB::table('desplieguedepago')->insert([
            'id' => $desplieguePagoId,
            'name' => $nuevoNombre,
            'metodo_de_pago_id' => $metodoPagoId,
            'activo' => true,
            'mostrar' => true,
            'requiere_numero_serie' => false,
            'sobrecargo_porcentaje' => 0,
            'tipo_sobrecargo' => 'ninguno',
            'adicional' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  ✓ Método creado exitosamente\n";
        echo "    ID: {$desplieguePagoId}\n";
        
        // Verificar que aparece en la lista
        $verificar = DB::table('desplieguedepago')
            ->where('id', $desplieguePagoId)
            ->where('activo', true)
            ->where('mostrar', true)
            ->first();
        
        if ($verificar) {
            echo "  ✓ Método verificado en la base de datos\n";
        } else {
            echo "  ❌ ERROR: Método no encontrado después de crear\n";
        }
    }
} catch (\Exception $e) {
    echo "  ❌ ERROR al crear método: " . $e->getMessage() . "\n";
}

// 5. Verificar reglas de exclusividad
echo "\n5. TEST DE REGLAS DE EXCLUSIVIDAD:\n";
echo "Regla 1: Un método solo puede estar en UNA sub-caja ACTIVA de la misma caja principal\n";
echo "Regla 2: Múltiples métodos pueden estar en la MISMA sub-caja\n";
echo "Regla 3: Si una sub-caja está INACTIVA, sus métodos quedan disponibles\n\n";

// Verificar cada sub-caja
foreach ($subcajas as $sc) {
    $metodos_ids = json_decode($sc->despliegues_pago_ids ?? '[]', true);
    $estado_text = $sc->estado == 1 ? 'ACTIVA' : 'INACTIVA';
    
    echo "Sub-caja: {$sc->nombre} [{$estado_text}]\n";
    echo "  Caja Principal: {$sc->caja_principal_id}\n";
    echo "  Métodos asignados: " . count($metodos_ids) . "\n";
    
    if (in_array('*', $metodos_ids)) {
        echo "  ⚠️  Acepta TODOS los métodos de pago\n";
    } else {
        foreach ($metodos_ids as $mid) {
            $metodo = DB::table('desplieguedepago')->where('id', $mid)->first();
            if ($metodo) {
                echo "    - {$metodo->name}\n";
            }
        }
    }
    
    // Verificar si hay conflictos
    if ($sc->estado == 1 && !in_array('*', $metodos_ids)) {
        $conflictos = DB::table('sub_cajas')
            ->where('id', '!=', $sc->id)
            ->where('caja_principal_id', $sc->caja_principal_id)
            ->where('estado', 1)
            ->get();
        
        foreach ($conflictos as $otra) {
            $otros_metodos = json_decode($otra->despliegues_pago_ids ?? '[]', true);
            $comunes = array_intersect($metodos_ids, $otros_metodos);
            
            if (!empty($comunes)) {
                echo "  ⚠️  CONFLICTO con sub-caja '{$otra->nombre}': métodos compartidos: " . json_encode($comunes) . "\n";
            }
        }
    }
    echo "\n";
}

echo "=== FIN DEL TEST COMPLETO ===\n";
