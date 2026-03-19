<?php

namespace App\Services\Producto;

use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenUnidadDerivada;
use Illuminate\Support\Facades\Log;

class ComplementarioStockService
{
    /**
     * Procesar el descuento/incremento de stock del producto complementario
     * asociado a una unidad derivada.
     *
     * @param int $productoAlmacenId ID del ProductoAlmacen principal
     * @param int $unidadDerivadaId ID de la UnidadDerivada usada
     * @param float $cantidadVendida Cantidad vendida en la unidad derivada
     * @param int $almacenId ID del almacén donde se descuenta
     * @param bool $esIngreso true para incrementar (revertir), false para decrementar
     */
    public static function procesarComplementario(
        int $productoAlmacenId,
        int $unidadDerivadaId,
        float $cantidadVendida,
        int $almacenId,
        bool $esIngreso = false
    ): void {
        $paud = ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->where('unidad_derivada_id', $unidadDerivadaId)
            ->first();

        if (! $paud || ! $paud->producto_complementario_id) {
            return;
        }

        $cantidadComplementaria = $cantidadVendida * (float) $paud->producto_complementario_cantidad;

        $productoAlmacenComplementario = ProductoAlmacen::where('producto_id', $paud->producto_complementario_id)
            ->where('almacen_id', $almacenId)
            ->first();

        if (! $productoAlmacenComplementario) {
            Log::warning('Producto complementario no encontrado en almacén', [
                'producto_complementario_id' => $paud->producto_complementario_id,
                'almacen_id' => $almacenId,
            ]);
            return;
        }

        if ($esIngreso) {
            $productoAlmacenComplementario->increment('stock_fraccion', $cantidadComplementaria);
        } else {
            $productoAlmacenComplementario->decrement('stock_fraccion', $cantidadComplementaria);
        }

        Log::info('Stock complementario procesado', [
            'producto_complementario_id' => $paud->producto_complementario_id,
            'cantidad' => $cantidadComplementaria,
            'tipo' => $esIngreso ? 'ingreso' : 'salida',
            'almacen_id' => $almacenId,
        ]);
    }

    /**
     * Procesar complementario buscando por factor en vez de unidad_derivada_id.
     * Útil cuando solo se tiene el factor (ej: desde cotizaciones).
     */
    public static function procesarComplementarioPorFactor(
        int $productoAlmacenId,
        float $factor,
        float $cantidadVendida,
        int $almacenId,
        bool $esIngreso = false
    ): void {
        $paud = ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacenId)
            ->where('factor', $factor)
            ->first();

        if (! $paud || ! $paud->producto_complementario_id) {
            return;
        }

        $cantidadComplementaria = $cantidadVendida * (float) $paud->producto_complementario_cantidad;

        $productoAlmacenComplementario = ProductoAlmacen::where('producto_id', $paud->producto_complementario_id)
            ->where('almacen_id', $almacenId)
            ->first();

        if (! $productoAlmacenComplementario) {
            return;
        }

        if ($esIngreso) {
            $productoAlmacenComplementario->increment('stock_fraccion', $cantidadComplementaria);
        } else {
            $productoAlmacenComplementario->decrement('stock_fraccion', $cantidadComplementaria);
        }
    }
}
