<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntregaStockService
{
    /**
     * Aplica el descuento de stock e inventario.
     * Idempotente: si stock_aplicado = true, no hace nada.
     */
    public function aplicar(Entrega $entrega): void
    {
        if ($entrega->stock_aplicado) {
            return;
        }

        DB::transaction(function () use ($entrega) {
            $venta = $entrega->venta;
            $entrega->loadMissing('detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen');

            foreach ($entrega->detalles as $detalle) {
                $udv      = $detalle->unidadDerivadaVenta;
                $cantidad = (float) $detalle->cantidad;

                // Reducir cantidad pendiente de la UDV
                $udv->decrement('cantidad_pendiente', $cantidad);

                // Reducir stock físico solo si la venta no lo aplicó aún.
                // descuenta_stock=false (venta administrativa, "Descontar stock: NO"):
                // el cliente ya tiene el producto, NUNCA se toca stock físico.
                if (! $venta->stock_aplicado && $venta->descuenta_stock) {
                    $pa = $udv->productoAlmacenVenta?->productoAlmacen;
                    if ($pa) {
                        $fraccion = $cantidad * (float) $udv->factor;
                        $pa->decrement('stock_fraccion', $fraccion);

                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $pa->id,
                            (float) $udv->factor,
                            $cantidad,
                            $entrega->almacen_salida_id,
                            false // salida
                        );

                        $this->registrarKardex($entrega, $venta, $udv, $pa, $fraccion, false);
                    }
                }
            }

            $entrega->update(['stock_aplicado' => true]);
        });
    }

    /**
     * Revierte el descuento de stock e inventario.
     * Idempotente: si stock_aplicado = false, no hace nada.
     */
    public function revertir(Entrega $entrega): void
    {
        if (! $entrega->stock_aplicado) {
            return;
        }

        DB::transaction(function () use ($entrega) {
            $venta = $entrega->venta;
            $entrega->loadMissing('detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen');

            foreach ($entrega->detalles as $detalle) {
                $udv      = $detalle->unidadDerivadaVenta;
                $cantidad = (float) $detalle->cantidad;

                // Restaurar cantidad pendiente
                $udv->increment('cantidad_pendiente', $cantidad);

                // Restaurar stock físico solo si la venta no lo administra.
                // descuenta_stock=false: la venta nunca descontó stock (ni la venta
                // ni la entrega), así que anular NO debe devolver stock fantasma.
                if (! $venta->stock_aplicado && $venta->descuenta_stock) {
                    $pa = $udv->productoAlmacenVenta?->productoAlmacen;
                    if ($pa) {
                        $fraccion = $cantidad * (float) $udv->factor;
                        $pa->increment('stock_fraccion', $fraccion);

                        ComplementarioStockService::procesarComplementarioPorFactor(
                            $pa->id,
                            (float) $udv->factor,
                            $cantidad,
                            $entrega->almacen_salida_id,
                            true // ingreso (reversa)
                        );

                        $this->registrarKardex($entrega, $venta, $udv, $pa, $fraccion, true);
                    }
                }
            }

            $entrega->update(['stock_aplicado' => false]);
        });
    }

    /**
     * Deja en kardex de facturación el movimiento de stock que hace la ENTREGA
     * (modelo "la entrega descuenta": venta.stock_aplicado = false). Sin esto el
     * stock saltaba sin ninguna fila que lo explique — el 24/08/2026 la varilla
     * 1/2" pasó de 848 a 888 (+40 de una entrega) y ningún kardex lo registró.
     *
     * tipo = 'entrega_stock' a propósito: NO es 'venta' (no debe entrar al saldo
     * de movimientos que reparte las filas ENTREGA en getPaginated()) ni
     * 'entrega' (esas son filas sintéticas que heredan el stock de su venta).
     */
    private function registrarKardex(Entrega $entrega, $venta, $udv, $pa, float $fraccion, bool $esIngreso): void
    {
        try {
            $stockActual = (float) DB::table('productoalmacen')->where('id', $pa->id)->value('stock_fraccion');
            $tipoDocumento = match ($venta->tipo_documento->value ?? (string) $venta->tipo_documento) {
                '01' => 'Factura',
                '03' => 'Boleta',
                'nv' => 'Nota de Venta',
                default => (string) $venta->tipo_documento,
            };
            $factor = (float) ($udv->factor ?: 1);

            app(\App\Services\Kardex\KardexFacturacionService::class)->registrar([
                'tipo' => 'entrega_stock',
                'movimiento' => $esIngreso ? 'DEVOLUCIÓN POR ENTREGA ANULADA' : 'SALIDA POR ENTREGA',
                'fecha' => now(),
                'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
                'unidad' => $udv->unidadDerivadaInmutable?->name,
                'cantidad' => $factor > 0 ? $fraccion / $factor : $fraccion,
                'cantidad_fraccion' => $fraccion,
                'factor' => $factor,
                'precio' => (float) $udv->precio,
                'costo' => (float) $pa->costo,
                'entrada' => $esIngreso ? $fraccion : 0,
                'salida' => $esIngreso ? 0 : $fraccion,
                'referencia_id' => $venta->id,
                'venta_id' => $venta->id,
                'producto_id' => $pa->producto_id,
                'producto_nombre' => $pa->producto?->name,
                'producto_codigo' => $pa->producto?->cod_producto,
                'cliente_id' => $venta->cliente_id,
                'almacen_id' => $entrega->almacen_salida_id,
                'orden' => 1,
                // El stock físico YA se movió arriba: fijar el saldo real y
                // reconstruir el anterior deshaciendo este mismo movimiento.
                'stock_actual_override' => $stockActual,
                'stock_anterior_override' => $esIngreso ? $stockActual - $fraccion : $stockActual + $fraccion,
            ]);
        } catch (\Throwable $e) {
            // El kardex es rastro, no requisito: un fallo acá no debe tumbar la
            // transacción de la entrega.
            Log::warning('No se pudo registrar kardex del movimiento de stock de la entrega', [
                'entrega_id' => $entrega->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
