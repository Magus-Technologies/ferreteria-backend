<?php

namespace App\Services\CierreCaja;

use App\DTOs\CierreCaja\ResumenCajaDTO;
use App\Models\AperturaCierreCaja;
use App\Queries\CierreCaja\MovimientosCajaQuery;
use App\Repositories\Interfaces\VentaRepositoryInterface;

class CalculadorResumenCaja
{
    public function __construct(
        private MovimientosCajaQuery $movimientosQuery,
        private VentaRepositoryInterface $ventaRepository,
        private ClasificadorMovimientos $clasificador
    ) {}

    public function calcular(AperturaCierreCaja $apertura, bool $soloDiaActual = false, ?\Carbon\Carbon $fechaFiltro = null): ResumenCajaDTO
    {

        // Obtener ventas usando el repositorio existente
        $ventas = $this->ventaRepository->obtenerPorApertura($apertura->id);

        // Si es arqueo diario, filtrar ventas solo del día especificado (o hoy por defecto)
        if ($soloDiaActual) {
            $fechaDia = $fechaFiltro ?? \Carbon\Carbon::today();
            $ventas = $ventas->filter(function ($venta) use ($fechaDia) {
                // Usar la FECHA DE EMISIÓN (fecha), no created_at: una venta "En Espera"
                // registrada un día antes y confirmada hoy pertenece al arqueo de HOY
                // (el correlativo y el cobro se generan al confirmarla).
                return \Carbon\Carbon::parse($venta->fecha ?? $venta->created_at)->isSameDay($fechaDia);
            });
        }


        // Consolidar información de todas las subcajas
        $clasificacion = $this->clasificador->clasificarPorTodasLasSubCajas($apertura->id, $ventas, $soloDiaActual, $fechaFiltro);


        // FÓRMULA DEL CIERRE (SOLO EFECTIVO):
        // Total en Caja = Efectivo Inicial + Cobros en Efectivo + Otros Ingresos en Efectivo + Préstamos Recibidos - Gastos en Efectivo - Préstamos Dados
        // (Movimientos internos NO afectan el total)
        // (Pagos digitales NO afectan el efectivo en caja)

        // Filtrar solo cobros en EFECTIVO (método de pago "Efectivo")
        $cobrosEfectivo = $clasificacion['cobros_por_metodo']
            ->filter(function ($metodo) {
                return stripos($metodo['label'], 'Efectivo') !== false;
            })
            ->sum('total');

        // ¿El movimiento es en EFECTIVO? Se detecta por el MÉTODO (sin cuenta bancaria y
        // nombre "efectivo"), no por el nombre de la sub-caja. Así un ingreso/gasto en
        // cualquier caja de efectivo (ej. "caja negra") sí afecta el total en caja.
        // Fallback: si no hay método (movimiento antiguo), se usa el nombre "Chica".
        $esEfectivo = function ($item): bool {
            $cuenta = $item->metodo_cuenta ?? null;
            $sinCuenta = empty($cuenta) || $cuenta === 'SIN-CUENTA' || $cuenta === '-';
            $nombre = strtolower(($item->metodo_nombre ?? '') . ' ' . ($item->despliegue_nombre ?? ''));
            if (!empty($item->metodo_nombre) || !empty($item->despliegue_nombre)) {
                return $sinCuenta && stripos($nombre, 'efectivo') !== false;
            }
            return stripos($item->sub_caja ?? '', 'Chica') !== false;
        };

        // Filtrar solo otros ingresos en EFECTIVO (unir normales + extras para el cálculo de efectivo)
        $todosLosIngresosManuales = $clasificacion['otros_ingresos']->concat($clasificacion['ingresos_extras']);
        $otrosIngresosEfectivo = $todosLosIngresosManuales
            ->filter($esEfectivo)
            ->sum('monto');

        // Filtrar solo gastos en EFECTIVO (unir normales + extras para el cálculo de efectivo)
        $todosLosEgresosManuales = $clasificacion['gastos_y_pagos']->concat($clasificacion['gastos_extras']);
        $gastosEfectivo = $todosLosEgresosManuales
            ->filter($esEfectivo)
            ->sum('monto');

        $montoEsperado = $clasificacion['efectivo_inicial']
            + $cobrosEfectivo
            + $otrosIngresosEfectivo
            + $clasificacion['total_prestamos_recibidos']
            - $gastosEfectivo
            - $clasificacion['total_prestamos_dados'];

        // Redondeo a la décima SIEMPRE HACIA ABAJO (múltiplo de S/ 0.10). En Perú el
        // efectivo físico se cuadra en décimas (no circulan monedas de 0.01/0.05), así que
        // no se deben esperar céntimos sueltos (ej. 100.11 -> 100.10, 100.19 -> 100.10).
        // La épsilon 1e-6 evita que el ruido de punto flotante baje de más un valor exacto.
        $redondearDecimaAbajo = fn(float $monto): float => floor(round($monto, 2) * 10 + 1e-6) / 10;

        // Efectivo esperado (Total en Caja) redondeado. La diferencia se calcula contra este
        // valor ya redondeado.
        $montoEsperado = $redondearDecimaAbajo($montoEsperado);

        // Resúmenes de ingresos/egresos SOLO EFECTIVO, también redondeados a 0.10, para que
        // el bloque de efectivo del cierre cuadre exactamente:
        //   Ingreso Efectivo − Egreso Efectivo = Total en Caja (monto esperado).
        // El INGRESO EFECTIVO INCLUYE la apertura (efectivo inicial), porque ese efectivo ya
        // está físicamente en la caja y forma parte del total. Composición:
        //   Ingresos efectivo  = efectivo inicial + cobros efectivo + otros ingresos efectivo (incl. extras) + préstamos recibidos
        //   Egresos  efectivo  = gastos efectivo (incl. extras) + préstamos dados
        // (otrosIngresosEfectivo y gastosEfectivo ya unen los movimientos normales + extras).
        $resumenIngresosEfectivo = $redondearDecimaAbajo(
            $clasificacion['efectivo_inicial']
            + $cobrosEfectivo
            + $otrosIngresosEfectivo
            + $clasificacion['total_prestamos_recibidos']
        );
        $resumenEgresosEfectivo = $redondearDecimaAbajo(
            $gastosEfectivo + $clasificacion['total_prestamos_dados']
        );

        $montoCierre = $apertura->monto_cierre;
        $diferencia = $montoCierre !== null ? ($montoCierre - $montoEsperado) : null;

        // Formatear detalles de ventas con información de pagos
        $detalleVentas = $this->formatearDetalleVentas($clasificacion['ventas']);

        // Función auxiliar para formatear movimientos manuales
        $formatearManual = function ($item, $tipoDefault = 'ingreso_manual') use ($esEfectivo) {
            return [
                'id' => $item->id,
                'tipo' => $item->tipo ?? $tipoDefault,
                'monto' => number_format($item->monto, 2, '.', ''),
                'concepto' => $item->descripcion,
                'sub_caja' => $item->sub_caja,
                // Despliegue/método del movimiento, para mostrarlo en el detalle.
                'metodo' => $item->metodo_nombre ?? null,
                'despliegue' => $item->despliegue_nombre ?? null,
                'es_efectivo' => $esEfectivo($item),
                'created_at' => $item->created_at,
            ];
        };

        // Formatear detalles
        $detalleIngresos = $clasificacion['otros_ingresos']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item)]);
        $detalleEgresos = $clasificacion['gastos_y_pagos']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'gasto_manual')]);
        
        $detalleIngresosExtras = $clasificacion['ingresos_extras']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'ingreso_extra')]);
        $detalleGastosExtras = $clasificacion['gastos_extras']->mapWithKeys(fn($item) => [$item->id => $formatearManual($item, 'gasto_extra')]);

        $resultado = new ResumenCajaDTO(
            efectivoInicial: $clasificacion['efectivo_inicial'],
            montoApertura: $apertura->monto_apertura,
            totalIngresos: $clasificacion['resumen_ingresos'],
            totalEgresos: $clasificacion['resumen_egresos'],
            totalVentas: $clasificacion['resumen_ventas'],
            montoEsperado: $montoEsperado,
            montoCierre: $montoCierre,
            diferencia: $diferencia,
            detalleIngresos: $detalleIngresos,
            detalleEgresos: $detalleEgresos,
            detalleVentas: $detalleVentas,
            detalleMetodosPago: $clasificacion['cobros_por_metodo'],
            prestamosRecibidos: $clasificacion['prestamos_recibidos'],
            totalPrestamosRecibidos: $clasificacion['total_prestamos_recibidos'],
            prestamosDados: $clasificacion['prestamos_dados'],
            totalPrestamosDados: $clasificacion['total_prestamos_dados'],
            movimientosInternos: $clasificacion['movimientos_internos'],
            prestamos: $clasificacion['prestamos'],
            prestamosVendedores: $clasificacion['prestamos_vendedores'],
            resumenBancos: $clasificacion['resumen_bancos'],
            totalIngresosExtras: $clasificacion['total_ingresos_extras'],
            totalGastosExtras: $clasificacion['total_gastos_extras'],
            detalleIngresosExtras: $detalleIngresosExtras,
            detalleGastosExtras: $detalleGastosExtras,
            trasladosBoveda: $clasificacion['traslados_boveda'] ?? collect([]),
            totalTrasladosBoveda: $clasificacion['total_traslados_boveda'] ?? 0,
            resumenIngresosEfectivo: $resumenIngresosEfectivo,
            resumenEgresosEfectivo: $resumenEgresosEfectivo
        );


        return $resultado;
    }

    private function formatearDetalleVentas($ventas)
    {
        return $ventas->map(function ($venta) {
            // Sub-caja por método de pago de ESTA venta, colapsada a UNA fila
            // por despliegue_pago_id. Necesario porque el modelo de cobro
            // diferencial puede dejar VARIAS transacciones_caja para el mismo
            // (venta, método) — cobro inicial + cobro de diferencia (+
            // devolución) — y un JOIN directo contra transacciones_caja
            // multiplicaría en cruz cada fila de desplieguedepagoventa por
            // cada transacción coincidente, inflando el total mostrado.
            $subCajaPorMetodo = \Illuminate\Support\Facades\DB::table('transacciones_caja')
                ->select('despliegue_pago_id', \Illuminate\Support\Facades\DB::raw('MIN(sub_caja_id) as sub_caja_id'))
                ->where('referencia_id', $venta->id)
                ->where('referencia_tipo', 'venta')
                ->groupBy('despliegue_pago_id');

            // Obtener los pagos de esta venta con información de sub-caja
            // La sub-caja se obtiene desde transacciones_caja, no desde metododepago
            $pagos = \Illuminate\Support\Facades\DB::table('desplieguedepagoventa as dpv')
                ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
                ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
                ->leftJoin('numeros_operacion_pago as nop', 'dpv.numero_operacion_id', '=', 'nop.id')
                // Obtener la sub-caja desde transacciones_caja (ya colapsada arriba)
                ->leftJoinSub($subCajaPorMetodo, 'tc', function ($join) {
                    $join->on('tc.despliegue_pago_id', '=', 'dpv.despliegue_de_pago_id');
                })
                ->leftJoin('sub_cajas as sc', 'tc.sub_caja_id', '=', 'sc.id')
                ->where('dpv.venta_id', $venta->id)
                ->select([
                    'sc.nombre as sub_caja',
                    'mp.name as banco',
                    'dp.name as metodo_pago',
                    'dpv.monto',
                    'nop.numero_operacion'
                ])
                ->get();

            // Calcular el total desde los pagos
            $totalVenta = $pagos->sum('monto');

            // Construir array de la venta con información adicional
            return [
                'id' => $venta->id,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'cliente_nombre' => $venta->cliente->razon_social ?? ($venta->cliente->nombres . ' ' . $venta->cliente->apellidos) ?? 'Sin cliente',
                'total' => (float) $totalVenta,
                // FECHA DE EMISIÓN de la venta (no created_at): una venta "En Espera"
                // registrada un día antes y confirmada hoy debe mostrarse con la fecha
                // en que se emitió la boleta. Se mantiene la clave 'created_at' por
                // compatibilidad con el front y los snapshots ya guardados.
                'created_at' => $venta->fecha ?? $venta->created_at,
                'pagos' => $pagos->map(function ($pago) {
                    $banco = $pago->banco ?? 'Sin Banco';
                    $metodoPagoFormateado = "{$banco}/{$pago->metodo_pago}";
                    return [
                        'sub_caja' => $pago->sub_caja ?? 'N/A',
                        'metodo_pago' => $metodoPagoFormateado,
                        'monto' => (float) $pago->monto,
                        'numero_operacion' => $pago->numero_operacion,
                    ];
                })->toArray(),
            ];
        });
    }
}
