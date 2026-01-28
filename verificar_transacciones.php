<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICANDO TRANSACCIONES ===\n\n";

echo "INGRESOS (tipo='ingreso'):\n";
echo str_repeat("-", 100) . "\n";

$ingresos = DB::table('transacciones_caja as tc')
    ->leftJoin('users as u', 'tc.user_id', '=', 'u.id')
    ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
    ->where('tc.tipo_transaccion', 'ingreso')
    ->whereRaw("(tc.referencia_tipo IS NULL OR tc.referencia_tipo NOT IN ('venta', 'apertura', 'transferencia_vendedor'))")
    ->select([
        'tc.id',
        'tc.monto',
        'tc.descripcion',
        'tc.user_id',
        'u.name as usuario',
        'tc.referencia_tipo',
        'sc.nombre as sub_caja',
        'tc.created_at'
    ])
    ->orderBy('tc.created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($ingresos as $ingreso) {
    printf(
        "ID: %s | Monto: %s | User: %s (%s) | SubCaja: %s | Desc: %s | Ref: %s | Fecha: %s\n",
        $ingreso->id,
        number_format($ingreso->monto, 2),
        $ingreso->usuario ?? 'NULL',
        $ingreso->user_id ?? 'NULL',
        $ingreso->sub_caja ?? 'NULL',
        $ingreso->descripcion ?? 'NULL',
        $ingreso->referencia_tipo ?? 'NULL',
        $ingreso->created_at
    );
}

echo "\n\nGASTOS (tipo='egreso'):\n";
echo str_repeat("-", 100) . "\n";

$gastos = DB::table('transacciones_caja as tc')
    ->leftJoin('users as u', 'tc.user_id', '=', 'u.id')
    ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
    ->where('tc.tipo_transaccion', 'egreso')
    ->whereRaw("(tc.referencia_tipo IS NULL OR tc.referencia_tipo NOT IN ('transferencia_vendedor'))")
    ->select([
        'tc.id',
        'tc.monto',
        'tc.descripcion',
        'tc.user_id',
        'u.name as usuario',
        'tc.referencia_tipo',
        'sc.nombre as sub_caja',
        'tc.created_at'
    ])
    ->orderBy('tc.created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($gastos as $gasto) {
    printf(
        "ID: %s | Monto: %s | User: %s (%s) | SubCaja: %s | Desc: %s | Ref: %s | Fecha: %s\n",
        $gasto->id,
        number_format($gasto->monto, 2),
        $gasto->usuario ?? 'NULL',
        $gasto->user_id ?? 'NULL',
        $gasto->sub_caja ?? 'NULL',
        $gasto->descripcion ?? 'NULL',
        $gasto->referencia_tipo ?? 'NULL',
        $gasto->created_at
    );
}

echo "\n\nUSUARIO ACTUAL EN LOGS: cmj8o0pf70001uk0o4d3tbyyx\n";
echo "Verificar si las transacciones pertenecen a este usuario.\n";
