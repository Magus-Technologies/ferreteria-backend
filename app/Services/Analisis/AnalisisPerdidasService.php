<?php

namespace App\Services\Analisis;

use Illuminate\Support\Facades\DB;
use App\QueryFilters\GananciasQueryFilter;

class AnalisisPerdidasService
{
    /**
     * Obtener análisis completo de pérdidas
     */
    public function obtenerAnalisisPerdidas(array $filtros): array
    {
        $filter = new GananciasQueryFilter($filtros);

        $tipo = $filtros['tipo_perdida'] ?? 'todas';
        if ($tipo === '') $tipo = 'todas';

        // 1. Pérdidas por Ventas Bajo Costo
        $perdidasBajoCosto = ($tipo === 'todas' || $tipo === 'ventas_bajo_costo') 
            ? $this->calcularPerdidasBajoCosto($filter) 
            : ['detalles' => [], 'total' => 0];

        // 2. Pérdidas por Descuentos
        $perdidasDescuentos = ($tipo === 'todas' || $tipo === 'descuentos') 
            ? $this->calcularPerdidasDescuentos($filter) 
            : ['detalles' => [], 'total' => 0];

        // 3. Pérdidas por Comisiones
        $perdidasComisiones = ($tipo === 'todas' || $tipo === 'comisiones') 
            ? $this->calcularPerdidasComisiones($filter) 
            : ['detalles' => [], 'total' => 0];

        // 4. Pérdidas por Salidas de Almacén
        $perdidasSalidas = ($tipo === 'todas' || $tipo === 'salidas') 
            ? $this->calcularPerdidasSalidas($filter) 
            : ['detalles' => [], 'total' => 0];

        // 5. Pérdidas por Notas de Crédito
        $perdidasNotasCredito = ($tipo === 'todas' || $tipo === 'notas_credito') 
            ? $this->calcularPerdidasNotasCredito($filter) 
            : ['detalles' => [], 'total' => 0];

        // Combinar todos los detalles
        $todosDetalles = collect()
            ->concat($perdidasBajoCosto['detalles'])
            ->concat($perdidasDescuentos['detalles'])
            ->concat($perdidasComisiones['detalles'])
            ->concat($perdidasSalidas['detalles'])
            ->concat($perdidasNotasCredito['detalles'])
            ->sortByDesc('fecha')
            ->values();

        return [
            'detalles' => $todosDetalles->toArray(),
            'resumen' => [
                'ventas_bajo_costo' => round($perdidasBajoCosto['total'], 2),
                'descuentos_aplicados' => round($perdidasDescuentos['total'], 2),
                'comisiones_vendedor' => round($perdidasComisiones['total'], 2),
                'salidas_almacen' => round($perdidasSalidas['total'], 2),
                'notas_credito' => round($perdidasNotasCredito['total'], 2),
                'total_perdidas' => round(
                    $perdidasBajoCosto['total'] +
                    $perdidasDescuentos['total'] +
                    $perdidasComisiones['total'] +
                    $perdidasSalidas['total'] +
                    $perdidasNotasCredito['total'],
                    2
                ),
            ],
            'por_categoria' => [
                [
                    'categoria' => 'Ventas Bajo Costo',
                    'monto' => round($perdidasBajoCosto['total'], 2),
                    'cantidad' => count($perdidasBajoCosto['detalles']),
                ],
                [
                    'categoria' => 'Descuentos Aplicados',
                    'monto' => round($perdidasDescuentos['total'], 2),
                    'cantidad' => count($perdidasDescuentos['detalles']),
                ],
                [
                    'categoria' => 'Comisiones de Vendedor',
                    'monto' => round($perdidasComisiones['total'], 2),
                    'cantidad' => count($perdidasComisiones['detalles']),
                ],
                [
                    'categoria' => 'Salidas de Almacén',
                    'monto' => round($perdidasSalidas['total'], 2),
                    'cantidad' => count($perdidasSalidas['detalles']),
                ],
                [
                    'categoria' => 'Notas de Crédito',
                    'monto' => round($perdidasNotasCredito['total'], 2),
                    'cantidad' => count($perdidasNotasCredito['detalles']),
                ],
            ],
        ];
    }

    /**
     * Calcular pérdidas por ventas bajo costo
     */
    private function calcularPerdidasBajoCosto(GananciasQueryFilter $filter): array
    {
        $costoFallback = "COALESCE(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END, 0)";

        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            ->select([
                'v.id as venta_id',
                'pav.id as pav_id',
                'v.fecha',
                DB::raw("CASE v.tipo_documento
                    WHEN 'fa' THEN 'FACTURA'
                    WHEN '01' THEN 'FACTURA'
                    WHEN 'bv' THEN 'B.VENTA'
                    WHEN '03' THEN 'B.VENTA'
                    WHEN 'nv' THEN 'N.VENTA'
                    WHEN '00' THEN 'N.VENTA'
                    ELSE v.tipo_documento
                END as tipo_doc_name"),
                'v.numero',
                'p.name as producto',
                DB::raw("CASE
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                // Factor de conversión unidad derivada → unidad base. Los costos se
                // guardan por unidad base y el precio por unidad derivada, así que hay
                // que llevar el costo a unidad derivada (costo × factor) antes de comparar.
                'udiv.factor',
                DB::raw("$costoFallback as costo_fallback"),
                // Desglose PEPS guardado en la venta (lote anterior / actual).
                'pav.cant_costo_anterior',
                'pav.costo_anterior',
                'pav.cant_costo_actual',
                'pav.costo_actual',
                DB::raw("CASE
                    WHEN ce.serie IS NOT NULL AND ce.correlativo IS NOT NULL
                    THEN CONCAT(
                        CASE v.tipo_documento
                            WHEN 'fa' THEN '[FACTURA] '
                            WHEN '01' THEN '[FACTURA] '
                            WHEN 'bv' THEN '[BOLETA] '
                            WHEN '03' THEN '[BOLETA] '
                            ELSE '[DOC] '
                        END,
                        ce.serie, '-', LPAD(ce.correlativo, 8, '0')
                    )
                    ELSE CONCAT(
                        CASE v.tipo_documento
                            WHEN 'fa' THEN '[FACTURA] '
                            WHEN '01' THEN '[FACTURA] '
                            WHEN 'bv' THEN '[BOLETA] '
                            WHEN '03' THEN '[BOLETA] '
                            WHEN 'nv' THEN '[N.VENTA] '
                            WHEN '00' THEN '[N.VENTA] '
                            ELSE '[DOC] '
                        END,
                        LPAD(v.numero, 8, '0')
                    )
                END as comprobante"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            // No contar ventas administrativas ("no descontar stock").
            ->whereRaw('(v.descuenta_stock IS NULL OR v.descuenta_stock = 1)')
            // Traer filas donde AL MENOS un costo (lote anterior/actual o fallback) supera el precio.
            // Los costos están por unidad base → se llevan a unidad derivada (× factor) para
            // compararlos contra udiv.precio (por unidad derivada). Sin esto, productos vendidos
            // por fracción (factor < 1) salían como "bajo costo" falsamente, y ventas por caja
            // (factor > 1) realmente bajo costo se escapaban.
            ->whereRaw("udiv.precio < GREATEST(COALESCE(pav.costo_anterior,0), COALESCE(pav.costo_actual,0), $costoFallback) * COALESCE(NULLIF(udiv.factor,0),1)");

        $filter->applyPerdidas($query, 'venta');
        $rows = $query->get();

        // Construir filas: si la venta consumió dos lotes (anterior + actual), se generan
        // DOS filas (una por costo). Si no hay desglose (ventas previas), una sola fila.
        $detalles = [];
        $total = 0.0;
        // El desglose se guarda por línea de venta (pav). Si la línea tiene varias
        // unidades derivadas, el query repite la fila → procesamos el desglose una sola
        // vez por pav para no duplicar la pérdida.
        $pavDesgloseProcesado = [];

        $armarFila = function ($r, string $categoria, float $cantidad, float $costo, float $monto): array {
            return [
                'venta_id' => $r->venta_id,
                'fecha' => $r->fecha,
                'tipo_doc_name' => $r->tipo_doc_name,
                'numero' => $r->numero,
                'producto' => $r->producto,
                'cliente' => $r->cliente,
                'vendedor' => $r->vendedor,
                'categoria' => $categoria,
                'cantidad' => $cantidad,
                'precio_venta' => (float) $r->precio_venta,
                'costo_producto' => $costo,
                'monto' => $monto,
                'comprobante' => $r->comprobante,
                'referencia' => $r->referencia,
            ];
        };

        foreach ($rows as $r) {
            $precio = (float) $r->precio_venta;            // por unidad derivada
            $factor = (float) ($r->factor ?? 1);           // unidad derivada → base
            if ($factor <= 0) {
                $factor = 1;
            }

            // Cantidades del desglose (cant_costo_*) están en unidad BASE; los costos
            // (costo_*) por unidad BASE. Para comparar contra el precio (por unidad
            // derivada) se lleva el costo a unidad derivada (× factor) y la cantidad a
            // unidad derivada (÷ factor).
            $cantAntBase = (float) ($r->cant_costo_anterior ?? 0);
            $cantActBase = (float) ($r->cant_costo_actual ?? 0);
            $costoAntDeriv = (float) ($r->costo_anterior ?? 0) * $factor;
            $costoActDeriv = (float) ($r->costo_actual ?? 0) * $factor;

            $tieneDesglose = ($cantAntBase + $cantActBase) > 0;

            // Evitar duplicar el desglose si el pav aparece en varias filas (varias U.D.).
            if ($tieneDesglose && isset($pavDesgloseProcesado[$r->pav_id])) {
                continue;
            }

            if ($tieneDesglose) {
                $pavDesgloseProcesado[$r->pav_id] = true;
                // Fila del lote ANTERIOR (si hubo consumo y está bajo costo).
                if ($cantAntBase > 0 && $costoAntDeriv > $precio) {
                    $cantDeriv = $cantAntBase / $factor;
                    $monto = ($costoAntDeriv - $precio) * $cantDeriv;
                    $detalles[] = $armarFila($r, 'VENTA BAJO COSTO (lote anterior)', $cantDeriv, $costoAntDeriv, $monto);
                    $total += $monto;
                }
                // Fila del lote ACTUAL.
                if ($cantActBase > 0 && $costoActDeriv > $precio) {
                    $cantDeriv = $cantActBase / $factor;
                    $monto = ($costoActDeriv - $precio) * $cantDeriv;
                    $detalles[] = $armarFila($r, 'VENTA BAJO COSTO (lote actual)', $cantDeriv, $costoActDeriv, $monto);
                    $total += $monto;
                }
            } else {
                // Sin desglose (venta previa a esta función): una sola fila con el costo
                // guardado, llevado a unidad derivada. udiv.cantidad ya está en derivada.
                $costoDeriv = (float) $r->costo_fallback * $factor;
                if ($costoDeriv > $precio) {
                    $monto = ($costoDeriv - $precio) * (float) $r->cantidad;
                    $detalles[] = $armarFila($r, 'VENTA BAJO COSTO', (float) $r->cantidad, $costoDeriv, $monto);
                    $total += $monto;
                }
            }
        }

        return [
            'detalles' => $detalles,
            'total' => $total,
        ];
    }

    /**
     * Calcular pérdidas por descuentos aplicados
     */
    private function calcularPerdidasDescuentos(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            ->select([
                'v.id as venta_id',
                'v.fecha',
                DB::raw("CASE v.tipo_documento 
                    WHEN 'fa' THEN 'FACTURA' 
                    WHEN '01' THEN 'FACTURA' 
                    WHEN 'bv' THEN 'B.VENTA' 
                    WHEN '03' THEN 'B.VENTA' 
                    WHEN 'nv' THEN 'N.VENTA' 
                    WHEN '00' THEN 'N.VENTA'
                    ELSE v.tipo_documento 
                END as tipo_doc_name"),
                'v.numero',
                'p.name as producto',
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("CASE 
                    WHEN udiv.descuento_tipo = 'porcentaje' THEN CONCAT('DESCUENTO ', udiv.descuento, '%')
                    ELSE 'DESCUENTO FIJO'
                END as categoria"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_producto"),
                'udiv.descuento',
                'udiv.descuento_tipo',
                DB::raw("CASE 
                    WHEN udiv.descuento_tipo = 'porcentaje' THEN (udiv.precio * udiv.cantidad * udiv.descuento / 100)
                    ELSE udiv.descuento
                END as monto"),
                DB::raw("CASE 
                    WHEN ce.serie IS NOT NULL AND ce.correlativo IS NOT NULL 
                    THEN CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            ELSE '[DOC] ' 
                        END,
                        ce.serie, '-', LPAD(ce.correlativo, 8, '0')
                    )
                    ELSE CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            WHEN 'nv' THEN '[N.VENTA] ' 
                            WHEN '00' THEN '[N.VENTA] '
                            ELSE '[DOC] ' 
                        END,
                        LPAD(v.numero, 8, '0')
                    )
                END as comprobante"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('udiv.descuento', '>', 0);

        $filter->applyPerdidas($query, 'venta');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por comisiones de vendedor
     */
    private function calcularPerdidasComisiones(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableventa as udiv')
            ->join('productoalmacenventa as pav', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('venta as v', 'pav.venta_id', '=', 'v.id')
            ->leftJoin('productoalmacen as pa', 'pav.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('comprobantes_electronicos as ce', 'v.id', '=', 'ce.venta_id')
            ->select([
                'v.id as venta_id',
                'v.fecha',
                DB::raw("CASE v.tipo_documento 
                    WHEN 'fa' THEN 'FACTURA' 
                    WHEN '01' THEN 'FACTURA' 
                    WHEN 'bv' THEN 'B.VENTA' 
                    WHEN '03' THEN 'B.VENTA' 
                    WHEN 'nv' THEN 'N.VENTA' 
                    WHEN '00' THEN 'N.VENTA'
                    ELSE v.tipo_documento 
                END as tipo_doc_name"),
                'v.numero',
                'p.name as producto',
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("'COMISIÓN VENDEDOR' as categoria"),
                'udiv.cantidad',
                'udiv.precio as precio_venta',
                DB::raw("CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END as costo_producto"),
                'udiv.comision as comision_unitaria',
                DB::raw("udiv.comision * udiv.cantidad as monto"),
                DB::raw("CASE 
                    WHEN ce.serie IS NOT NULL AND ce.correlativo IS NOT NULL 
                    THEN CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            ELSE '[DOC] ' 
                        END,
                        ce.serie, '-', LPAD(ce.correlativo, 8, '0')
                    )
                    ELSE CONCAT(
                        CASE v.tipo_documento 
                            WHEN 'fa' THEN '[FACTURA] ' 
                            WHEN '01' THEN '[FACTURA] ' 
                            WHEN 'bv' THEN '[BOLETA] ' 
                            WHEN '03' THEN '[BOLETA] ' 
                            WHEN 'nv' THEN '[N.VENTA] ' 
                            WHEN '00' THEN '[N.VENTA] '
                            ELSE '[DOC] ' 
                        END,
                        LPAD(v.numero, 8, '0')
                    )
                END as comprobante"),
                DB::raw("CONCAT(v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('v.estado_de_venta', '!=', 'an')
            ->where('udiv.comision', '>', 0);

        $filter->applyPerdidas($query, 'venta');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por salidas de almacén
     */
    private function calcularPerdidasSalidas(GananciasQueryFilter $filter): array
    {
        $query = DB::table('unidadderivadainmutableingresosalida as udis')
            ->join('productoalmaceningresosalida as pais', 'udis.producto_almacen_ingreso_salida_id', '=', 'pais.id')
            ->join('ingresosalida as isa', 'pais.ingreso_id', '=', 'isa.id')
            ->leftJoin('productoalmacen as pa', 'pais.producto_almacen_id', '=', 'pa.id')
            ->leftJoin('producto as p', 'pa.producto_id', '=', 'p.id')
            ->leftJoin('tipoingresosalida as tis', 'isa.tipo_ingreso_id', '=', 'tis.id')
            ->leftJoin('user as u', 'isa.user_id', '=', 'u.id')
            ->select([
                'isa.id as salida_id',
                'isa.fecha',
                DB::raw("'SALIDA' as tipo_documento"),
                'isa.numero',
                'p.name as producto',
                DB::raw("UPPER(tis.name) as categoria"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as cliente"),
                'udis.cantidad',
                'pais.costo as costo_producto',
                DB::raw("pais.costo * udis.cantidad as monto"),
                DB::raw("CASE 
                    WHEN isa.serie IS NOT NULL THEN CONCAT(isa.serie, '-', LPAD(isa.numero, 8, '0'))
                    ELSE CONCAT('NS-', LPAD(isa.numero, 8, '0'))
                END as comprobante"),
                DB::raw("CONCAT('SALIDA #', isa.numero) as referencia"),
            ])
            ->where('isa.tipo_documento', 'sa')
            ->where('isa.estado', true)
            // Los préstamos NO son pérdida (el producto se devuelve), se excluyen
            ->whereRaw("UPPER(COALESCE(tis.name, '')) <> 'PRESTAMO'");

        $filter->applyPerdidas($query, 'salida');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }

    /**
     * Calcular pérdidas por notas de crédito
     * Nota: Usar tabla 'nota_credito' con underscore, no 'notacredito'
     */
    private function calcularPerdidasNotasCredito(GananciasQueryFilter $filter): array
    {
        $query = DB::table('nota_credito as nc')
            ->join('venta as v', 'nc.venta_id', '=', 'v.id')
            ->leftJoin('cliente as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('user as u', 'nc.usuario_id', '=', 'u.id')
            ->leftJoin('motivo_nota as mn', 'nc.motivo_id', '=', 'mn.id')
            ->leftJoin('comprobantes_electronicos as ce_nc', function($join) {
                $join->on('nc.serie', '=', 'ce_nc.serie')
                     ->on('nc.numero', '=', 'ce_nc.correlativo');
            })
            ->select([
                'nc.id as nota_credito_id',
                'nc.fecha',
                DB::raw("'NC' as tipo_documento"),
                DB::raw("CONCAT(nc.serie, '-', LPAD(nc.numero, 8, '0')) as numero"),
                DB::raw("COALESCE(mn.descripcion, 'NOTA DE CRÉDITO') as categoria"),
                DB::raw("CASE 
                    WHEN c.id IS NOT NULL THEN CONCAT(c.numero_documento, ' - ', COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)))
                    ELSE 'CLIENTES VARIOS'
                END as cliente"),
                DB::raw("COALESCE(u.name, 'SISTEMA') as vendedor"),
                DB::raw("'N/A' as producto"),
                DB::raw("1 as cantidad"),
                DB::raw("nc.monto_total as monto"),
                DB::raw("CONCAT(nc.serie, '-', LPAD(nc.numero, 8, '0')) as comprobante"),
                DB::raw("CONCAT('VENTA ', v.tipo_documento, ' ', v.numero) as referencia"),
            ])
            ->where('nc.estado', '!=', 'cancelado')
            ->where('nc.estado', '!=', 'borrador');

        $filter->applyPerdidas($query, 'nota_credito');
        $detalles = $query->get();

        return [
            'detalles' => $detalles->toArray(),
            'total' => $detalles->sum('monto'),
        ];
    }
}
