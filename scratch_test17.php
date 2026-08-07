$almacenId = DB::table('almacen')->first()->id ?? 1;

$usadosEnCompra = DB::table('gastos_extras as ge')
    ->join('compra as c', 'c.gasto_extra_id', '=', 'ge.id')
    ->select('ge.id', 'ge.estado', 'ge.monto', 'ge.concepto', 'c.id as compra_id', 'c.serie', 'c.numero')
    ->get();

echo "Gastos extras usados en alguna compra (via c.gasto_extra_id): " . $usadosEnCompra->count() . "\n";
foreach ($usadosEnCompra as $u) {
    echo "  ge.id={$u->id} estado={$u->estado} monto={$u->monto} compra={$u->serie}-{$u->numero}\n";
}

$service = app(\App\Services\Interfaces\GananciasServiceInterface::class);
$result = $service->obtenerPagosCompras([
    'almacen_id' => $almacenId,
    'desde' => '2000-01-01',
    'hasta' => '2100-01-01',
]);
$gastos = collect($result['gastos']);

echo "\nTotal filas en 'gastos' devueltas: " . $gastos->count() . "\n";

foreach ($usadosEnCompra as $u) {
    $comoOperativo = $gastos->first(fn($g) => $g->id === $u->id && $g->tipo === 'gasto_operativo');
    $comoCompra = $gastos->first(fn($g) => $g->id === $u->id && $g->tipo === 'gasto_compra');
    echo "ge.id={$u->id}: aparece como gasto_operativo=" . ($comoOperativo ? 'SI (BUG - duplicado)' : 'no') . ", aparece como gasto_compra=" . ($comoCompra ? 'si (correcto)' : 'NO') . "\n";
}
