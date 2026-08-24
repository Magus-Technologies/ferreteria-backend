<?php

namespace Tests\Unit;

use App\Services\Kardex\KardexFacturacionService;
use PHPUnit\Framework\TestCase;

/**
 * Reparto de las filas ENTREGA del kardex de facturación
 * (KardexFacturacionService::expandirEntregasPorMovimiento).
 *
 * Sin base de datos: los movimientos de venta se inyectan por una subclase.
 */
class KardexFacturacionEntregasTest extends TestCase
{
    private function servicio(array $movimientos): KardexFacturacionService
    {
        return new class($movimientos) extends KardexFacturacionService {
            public function __construct(private array $movimientos) {}

            protected function saldosPorMovimientoDeVenta(array $ventaIds): array
            {
                return $this->armarSaldos($this->movimientos);
            }

            public function expandir(array $entregaRows, ?string $hasta = null): array
            {
                return $this->expandirEntregasPorMovimiento($entregaRows, $hasta);
            }
        };
    }

    private function mov(array $o): object
    {
        return (object) array_merge([
            'tipo' => 'venta', 'movimiento' => 'VENTA CONTADO', 'referencia_id' => 'V1', 'producto_id' => 10,
            'fecha' => '2026-08-22 08:20:31', 'orden' => 1, 'factor' => 1,
            'cantidad' => 0, 'cantidad_fraccion' => 0, 'cantidad_reservada' => 0, 'entrada' => 0, 'salida' => 0,
        ], $o);
    }

    private function entrega(array $o): object
    {
        return (object) array_merge([
            'id' => 1, 'tipo' => 'entrega', 'movimiento' => 'ENTREGA', 'referencia_id' => 'V1', 'producto_id' => 10,
            'fecha' => '2026-08-22 08:20:32', 'factor' => 1, 'cantidad' => 0, 'cantidad_fraccion' => 0, 'orden' => 0,
        ], $o);
    }

    /** [id, fecha, cantidad, cantidad_fraccion] ordenado por fecha e id. */
    private function resumen(array $rows): array
    {
        $r = array_map(fn ($x) => [$x->id, $x->fecha, round((float) $x->cantidad, 4), round((float) $x->cantidad_fraccion, 4)], $rows);
        usort($r, fn ($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
        return $r;
    }

    /** NT01-289: 3 tubos (factor 3) vendidos; entrega partida en 1 + 2 tras anular la original. */
    public function test_entregas_parciales_conservan_su_cantidad_y_su_hora(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 3, 'cantidad_fraccion' => 9, 'factor' => 3, 'salida' => 9]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 1036, 'fecha' => '2026-08-22 08:23:02', 'factor' => 3, 'cantidad' => 2, 'cantidad_fraccion' => 6]),
            $this->entrega(['id' => 1035, 'fecha' => '2026-08-22 08:22:30', 'factor' => 3, 'cantidad' => 1, 'cantidad_fraccion' => 3]),
        ], '2026-08-22');

        $this->assertSame([
            [1035, '2026-08-22 08:22:30', 1.0, 3.0],
            [1036, '2026-08-22 08:23:02', 2.0, 6.0],
        ], $this->resumen($rows));
    }

    /** Venta editada 50 → 55: la entrega (reescrita a 55) se parte en 50 (venta) + 5 (ajuste). */
    public function test_venta_editada_parte_la_entrega_por_movimiento(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 50, 'cantidad_fraccion' => 50, 'salida' => 50]),
            $this->mov(['movimiento' => 'AJUSTE POR EDICIÓN (CONTADO)', 'fecha' => '2026-08-22 10:00:00', 'orden' => 2, 'cantidad' => 5, 'cantidad_fraccion' => 5, 'salida' => 5]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 7, 'fecha' => '2026-08-22 08:20:31', 'cantidad' => 55, 'cantidad_fraccion' => 55]),
        ], '2026-08-22');

        $this->assertSame([
            [7, '2026-08-22 08:20:31', 50.0, 50.0],
            [7, '2026-08-22 10:00:00', 5.0, 5.0],
        ], $this->resumen($rows));
        // Debajo de cualquier orden real, conservando el orden relativo de sus movimientos.
        $this->assertSame([-99.0, -98.0], array_map(fn ($r) => (float) $r->orden, $rows));
    }

    /** Venta editada 4 → 8 → 4 (BT01-576): una sola fila de 4, no 4 + 4 + 4. */
    public function test_ajuste_de_entrada_descuenta_el_saldo_mas_reciente(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 4, 'cantidad_fraccion' => 4, 'salida' => 4]),
            $this->mov(['movimiento' => 'AJUSTE POR EDICIÓN (CONTADO)', 'fecha' => '2026-08-22 09:00:00', 'orden' => 2, 'cantidad' => 4, 'cantidad_fraccion' => 4, 'salida' => 4]),
            $this->mov(['movimiento' => 'AJUSTE POR EDICIÓN (CONTADO)', 'fecha' => '2026-08-22 09:30:00', 'orden' => 3, 'cantidad' => 4, 'cantidad_fraccion' => 4, 'entrada' => 4]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 968, 'fecha' => '2026-08-22 08:20:32', 'cantidad' => 4, 'cantidad_fraccion' => 4]),
        ], '2026-08-22');

        $this->assertSame([[968, '2026-08-22 08:20:32', 4.0, 4.0]], $this->resumen($rows));
    }

    /** Varias entregas activas reescritas con el total (tras editar): nada se pierde. */
    public function test_cantidad_no_cubierta_por_movimientos_se_muestra_a_la_fecha_de_la_entrega(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 5, 'cantidad_fraccion' => 5, 'salida' => 5]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 1, 'fecha' => '2026-08-22 08:20:32', 'cantidad' => 5, 'cantidad_fraccion' => 5]),
            $this->entrega(['id' => 2, 'fecha' => '2026-08-22 09:00:00', 'cantidad' => 5, 'cantidad_fraccion' => 5]),
        ], '2026-08-22');

        $this->assertSame([
            [1, '2026-08-22 08:20:32', 5.0, 5.0],
            [2, '2026-08-22 09:00:00', 5.0, 5.0],
        ], $this->resumen($rows));
    }

    /** La parte que cae en un ajuste posterior al rango consultado no se muestra. */
    public function test_parte_posterior_al_rango_se_omite(): void
    {
        $svc = $this->servicio([
            $this->mov(['fecha' => '2026-08-21 08:00:00', 'cantidad' => 50, 'cantidad_fraccion' => 50, 'salida' => 50]),
            $this->mov(['movimiento' => 'AJUSTE POR EDICIÓN (CONTADO)', 'fecha' => '2026-08-22 10:00:00', 'orden' => 2, 'cantidad' => 5, 'cantidad_fraccion' => 5, 'salida' => 5]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 7, 'fecha' => '2026-08-21 08:00:01', 'cantidad' => 55, 'cantidad_fraccion' => 55]),
        ], '2026-08-21');

        $this->assertSame([[7, '2026-08-21 08:00:01', 50.0, 50.0]], $this->resumen($rows));
    }

    /** Venta desde cotización con reserva: cantidad es solo el excedente, lo reservado también cuenta. */
    public function test_reserva_de_cotizacion_cuenta_como_saldo(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 2, 'cantidad_fraccion' => 2, 'cantidad_reservada' => 8, 'salida' => 2]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 3, 'cantidad' => 10, 'cantidad_fraccion' => 10]),
        ], '2026-08-22');

        $this->assertSame([[3, '2026-08-22 08:20:32', 10.0, 10.0]], $this->resumen($rows));
    }

    /** Línea de entrega con cantidad 0 (producto no incluido en la entrega parcial): no se muestra. */
    public function test_linea_de_entrega_en_cero_se_omite(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 6, 'cantidad_fraccion' => 6, 'salida' => 6]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 1441, 'cantidad' => 0, 'cantidad_fraccion' => 0]),
        ], '2026-08-22');

        $this->assertSame([], $rows);
    }

    /** Entrega de una venta fuera del rango consultado: hereda el stock guardado del movimiento. */
    public function test_entrega_hereda_stock_guardado_del_movimiento(): void
    {
        $svc = $this->servicio([
            $this->mov(['fecha' => '2026-08-20 09:00:00', 'cantidad' => 40, 'cantidad_fraccion' => 40, 'salida' => 40, 'stock_anterior' => 500, 'stock_actual' => 460]),
        ]);

        $rows = $svc->expandir([
            $this->entrega(['id' => 9, 'fecha' => '2026-08-22 08:29:51', 'cantidad' => 40, 'cantidad_fraccion' => 40]),
        ], '2026-08-22');

        $this->assertCount(1, $rows);
        $this->assertSame('2026-08-22 08:29:51', $rows[0]->fecha);
        $this->assertEquals(500, $rows[0]->stock_anterior);
        $this->assertEquals(460, $rows[0]->stock_actual);
    }

    /** Las anuladas y las entregas sin movimiento de venta (legacy) pasan intactas. */
    public function test_anuladas_y_legacy_pasan_intactas(): void
    {
        $svc = $this->servicio([
            $this->mov(['cantidad' => 3, 'cantidad_fraccion' => 3, 'salida' => 3]),
        ]);

        $anulada = $this->entrega(['id' => 1033, 'movimiento' => 'ENTREGA ANULADA', 'fecha' => '2026-08-22 08:21:07', 'cantidad' => 3, 'cantidad_fraccion' => 3]);
        $legacy = $this->entrega(['id' => 5, 'referencia_id' => 'V9', 'cantidad' => 7, 'cantidad_fraccion' => 7]);

        $rows = $svc->expandir([$anulada, $legacy], '2026-08-22');

        $this->assertSame([$anulada, $legacy], $rows);
    }
}
