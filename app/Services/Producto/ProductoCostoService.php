<?php

namespace App\Services\Producto;

use App\Models\ProductoAlmacen;

class ProductoCostoService
{
    /**
     * Actualiza el costo y stock de un producto en almacén usando PEPS
     * Mantiene dos pares de (costo, stock) simultáneamente
     * 
     * @param ProductoAlmacen $productoAlmacen
     * @param float $costoNuevo
     * @param float $cantidadNueva
     * @return void
     */
    public function actualizarCostoConPEPS(ProductoAlmacen $productoAlmacen, float $costoNuevo, float $cantidadNueva): void
    {
        $costoActual = $productoAlmacen->costo_actual ?? $productoAlmacen->costo;
        $stockActual = $productoAlmacen->stock_costo_actual ?? 0;
        $stockAnterior = $productoAlmacen->stock_costo_anterior ?? 0;
        $costoAnterior = $productoAlmacen->costo_anterior;

        // Si es la primera recepción o el costo es igual al actual, solo sumar stock
        if ($costoActual === null || abs($costoNuevo - $costoActual) < 0.0001) {
            // Mismo costo, solo incrementar stock actual
            $productoAlmacen->costo_actual = $costoNuevo;
            $productoAlmacen->stock_costo_actual = $stockActual + $cantidadNueva;
            $productoAlmacen->costo = $costoNuevo;
        } else if ($costoAnterior !== null && abs($costoNuevo - $costoAnterior) < 0.0001) {
            // El nuevo costo es igual al anterior - sumar al stock anterior
            $productoAlmacen->stock_costo_anterior = $stockAnterior + $cantidadNueva;
            // El costo principal es el promedio ponderado
            $productoAlmacen->costo = $this->calcularCostoPromedioPonderado(
                $stockAnterior + $cantidadNueva,
                $costoAnterior,
                $stockActual,
                $costoActual,
                0,
                $costoNuevo
            );
        } else {
            // Precio diferente - hay cambio de costo
            // El costo actual se convierte en anterior
            $productoAlmacen->costo_anterior = $costoActual;
            $productoAlmacen->stock_costo_anterior = $stockActual;

            // El nuevo costo se convierte en actual
            $productoAlmacen->costo_actual = $costoNuevo;
            $productoAlmacen->stock_costo_actual = $cantidadNueva;

            // El costo principal es el promedio ponderado
            $productoAlmacen->costo = $this->calcularCostoPromedioPonderado(
                $stockAnterior,
                $costoAnterior,
                $stockActual,
                $costoActual,
                $cantidadNueva,
                $costoNuevo
            );
        }

        // Actualizar stock total
        $productoAlmacen->stock_fraccion = $productoAlmacen->stock_costo_anterior + $productoAlmacen->stock_costo_actual;
    }

    /**
     * Consume stock usando PEPS (Primero en Entrar, Primero en Salir)
     * Retorna el costo promedio ponderado del stock consumido
     * 
     * @param ProductoAlmacen $productoAlmacen
     * @param float $cantidadAConsumir
     * @return float Costo promedio del stock consumido
     */
    public function consumirStockConPEPS(ProductoAlmacen $productoAlmacen, float $cantidadAConsumir): float
    {
        $cantidadRestante = $cantidadAConsumir;
        $costoTotal = 0;
        $cantidadConsumida = 0;

        // Primero consumir stock anterior (PEPS)
        if ($productoAlmacen->stock_costo_anterior > 0) {
            $consumidaAnterior = min($cantidadRestante, $productoAlmacen->stock_costo_anterior);
            $costoTotal += $consumidaAnterior * $productoAlmacen->costo_anterior;
            $cantidadConsumida += $consumidaAnterior;
            $cantidadRestante -= $consumidaAnterior;
            $productoAlmacen->stock_costo_anterior -= $consumidaAnterior;

            // Si se agotó el stock anterior, limpiar el costo anterior
            if ($productoAlmacen->stock_costo_anterior <= 0) {
                $productoAlmacen->stock_costo_anterior = 0;
                $productoAlmacen->costo_anterior = null;
            }
        }

        // Luego consumir stock actual
        if ($cantidadRestante > 0 && $productoAlmacen->stock_costo_actual > 0) {
            $consumidaActual = min($cantidadRestante, $productoAlmacen->stock_costo_actual);
            $costoTotal += $consumidaActual * $productoAlmacen->costo_actual;
            $cantidadConsumida += $consumidaActual;
            $cantidadRestante -= $consumidaActual;
            $productoAlmacen->stock_costo_actual -= $consumidaActual;
        }

        // Actualizar stock total
        $productoAlmacen->stock_fraccion = $productoAlmacen->stock_costo_anterior + $productoAlmacen->stock_costo_actual;

        // Actualizar costo principal como promedio del stock restante
        if ($productoAlmacen->stock_fraccion > 0) {
            $productoAlmacen->costo = $costoTotal / $cantidadConsumida;
        }

        // Retornar costo promedio del stock consumido
        return $cantidadConsumida > 0 ? $costoTotal / $cantidadConsumida : 0;
    }

    /**
     * Calcula el costo promedio ponderado considerando todos los lotes
     */
    private function calcularCostoPromedioPonderado(
        float $stockAnteriorAnterior,
        ?float $costoAnteriorAnterior,
        float $stockActualAnterior,
        float $costoActualAnterior,
        float $cantidadNueva,
        float $costoNuevo
    ): float {
        $costoAnteriorAnterior = $costoAnteriorAnterior ?? 0;
        
        $totalStock = $stockAnteriorAnterior + $stockActualAnterior + $cantidadNueva;
        
        if ($totalStock <= 0) {
            return $costoNuevo;
        }

        $costoTotal = 
            ($stockAnteriorAnterior * $costoAnteriorAnterior) +
            ($stockActualAnterior * $costoActualAnterior) +
            ($cantidadNueva * $costoNuevo);

        return $costoTotal / $totalStock;
    }

    /**
     * Obtiene información de costos para visualización
     */
    public function obtenerInfoCostos(ProductoAlmacen $productoAlmacen): array
    {
        return [
            'costo_anterior' => $productoAlmacen->costo_anterior,
            'stock_costo_anterior' => $productoAlmacen->stock_costo_anterior,
            'costo_actual' => $productoAlmacen->costo_actual,
            'stock_costo_actual' => $productoAlmacen->stock_costo_actual,
            'costo_promedio' => $productoAlmacen->costo,
            'stock_total' => $productoAlmacen->stock_fraccion,
        ];
    }
}
