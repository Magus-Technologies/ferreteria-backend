<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Support\Facades\DB;

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

                // Reducir stock físico solo si la venta no lo aplicó aún
                if (! $venta->stock_aplicado) {
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

                // Restaurar stock físico solo si la venta no lo administra
                if (! $venta->stock_aplicado) {
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
                    }
                }
            }

            $entrega->update(['stock_aplicado' => false]);
        });
    }
}
