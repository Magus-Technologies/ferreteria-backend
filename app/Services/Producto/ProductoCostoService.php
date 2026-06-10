<?php

namespace App\Services\Producto;

use App\Models\ProductoAlmacen;

class ProductoCostoService
{
    /**
     * Repara en memoria productos legacy que tienen stock_fraccion pero sus
     * buckets PEPS están vacíos o desincronizados.
     */
    private function inicializarPepsLegacySiCorresponde(ProductoAlmacen $productoAlmacen): void
    {
        $stockFraccion = (float) ($productoAlmacen->stock_fraccion ?? 0);
        $stockAnterior = (float) ($productoAlmacen->stock_costo_anterior ?? 0);
        $stockActual = (float) ($productoAlmacen->stock_costo_actual ?? 0);
        $stockPepsTotal = $stockAnterior + $stockActual;
        $costoActual = $productoAlmacen->costo_actual;

        $bucketsVacios = abs($stockAnterior) < 0.0001 && abs($stockActual) < 0.0001;
        $stockDesincronizado = abs($stockPepsTotal - $stockFraccion) > 0.0001;
        $sinCostoActual = $costoActual === null || (float) $costoActual === 0.0;

        if (! $stockDesincronizado && ! ($stockFraccion !== 0.0 && $bucketsVacios && $sinCostoActual)) {
            return;
        }

        $productoAlmacen->costo_anterior = null;
        $productoAlmacen->stock_costo_anterior = 0;
        $productoAlmacen->costo_actual = $productoAlmacen->costo ?? 0;
        $productoAlmacen->stock_costo_actual = $stockFraccion;
    }
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
     * Calcula (SIN MUTAR) cómo se repartiría un consumo PEPS entre el lote anterior
     * y el actual, según los buckets actuales. Solo para registrar el desglose de costo
     * en la venta (reporte de pérdidas). NO modifica stock ni costo.
     *
     * @return array{cant_anterior: float, costo_anterior: float, cant_actual: float, costo_actual: float}
     */
    public function calcularDesglosePEPS(ProductoAlmacen $productoAlmacen, float $cantidad): array
    {
        $stockAnterior = max((float) ($productoAlmacen->stock_costo_anterior ?? 0), 0);
        $cantidad = max($cantidad, 0);
        $deAnterior = min($cantidad, $stockAnterior); // primero el lote anterior (PEPS)
        $deActual = $cantidad - $deAnterior;          // el resto, del lote actual

        return [
            'cant_anterior' => $deAnterior,
            'costo_anterior' => (float) ($productoAlmacen->costo_anterior ?? 0),
            'cant_actual' => $deActual,
            'costo_actual' => (float) ($productoAlmacen->costo_actual ?? $productoAlmacen->costo ?? 0),
        ];
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
        $this->inicializarPepsLegacySiCorresponde($productoAlmacen);

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

        // Luego consumir stock actual (permite stock negativo)
        if ($cantidadRestante > 0) {
            $costoActualUsar = $productoAlmacen->costo_actual ?? $productoAlmacen->costo ?? 0;
            $costoTotal += $cantidadRestante * $costoActualUsar;
            $cantidadConsumida += $cantidadRestante;
            $productoAlmacen->stock_costo_actual = ($productoAlmacen->stock_costo_actual ?? 0) - $cantidadRestante;
        }

        // Actualizar stock total (puede ser negativo)
        $productoAlmacen->stock_fraccion = $productoAlmacen->stock_costo_anterior + $productoAlmacen->stock_costo_actual;

        // Actualizar costo principal
        if ($cantidadConsumida > 0) {
            $productoAlmacen->costo = $costoTotal / $cantidadConsumida;
        }

        // Retornar costo promedio del stock consumido
        return $cantidadConsumida > 0 ? $costoTotal / $cantidadConsumida : 0;
    }

    /**
     * Revierte el ingreso de stock de una recepción anulada.
     *
     * El ingreso (actualizarCostoConPEPS) sumó la cantidad al bucket "actual";
     * al anular la quitamos de ese bucket y recalculamos el stock total y el
     * costo promedio con los buckets restantes. Así stock_costo_actual y
     * stock_fraccion no se desincronizan (evita que la siguiente recepción
     * infle el stock vía actualizarCostoConPEPS).
     *
     * NOTA: solo afecta el inventario del almacén; el registro de la recepción
     * permanece intacto como histórico.
     */
    public function revertirRecepcionConPEPS(ProductoAlmacen $productoAlmacen, float $cantidad): void
    {
        $anterior = (float) ($productoAlmacen->stock_costo_anterior ?? 0);
        $actual = (float) ($productoAlmacen->stock_costo_actual ?? 0);

        // Quitar la cantidad recibida del bucket actual (lo último que entró).
        // Se permite que quede negativo, igual que la anulación permitía stock negativo.
        $actual -= $cantidad;

        $productoAlmacen->stock_costo_actual = $actual;
        $productoAlmacen->stock_costo_anterior = $anterior;
        $productoAlmacen->stock_fraccion = $anterior + $actual;

        // Recalcular costo promedio con los buckets restantes (si hay stock positivo)
        $totalStock = $anterior + $actual;
        if ($totalStock > 0) {
            $costoActual = (float) ($productoAlmacen->costo_actual ?? $productoAlmacen->costo ?? 0);
            $costoAnterior = (float) ($productoAlmacen->costo_anterior ?? 0);
            $productoAlmacen->costo = (($anterior * $costoAnterior) + ($actual * $costoActual)) / $totalStock;
        }
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
