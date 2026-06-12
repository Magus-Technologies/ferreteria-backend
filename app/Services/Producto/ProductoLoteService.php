<?php

namespace App\Services\Producto;

use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenLote;
use App\Models\ProductoAlmacenLoteConsumo;

/**
 * Ledger PEPS por capas (lotes).
 *
 * Reemplaza el modelo de 2 buckets fijos (costo_anterior/costo_actual) por una
 * fila por cada entrada de costo. Permite recordar N costos distintos, saber el
 * costo real de cada venta (de qué lote salió) y, por tanto, el margen exacto.
 *
 * Los campos derivados de productoalmacen (costo, stock_fraccion,
 * costo_anterior/actual, stock_costo_anterior/actual, costo_con_flete) se
 * recalculan desde los lotes en {@see resyncDerivados()} para no romper nada de
 * lo que ya los lee.
 */
class ProductoLoteService
{
    /**
     * Si el producto ya tenía stock pero aún no tiene lotes (datos previos al
     * ledger), crea lotes iniciales a partir de los buckets actuales para no
     * perder el stock/costo existente. Idempotente.
     */
    public function inicializarDesdeBucketsSiVacio(ProductoAlmacen $pa): void
    {
        $tieneLotes = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)->exists();
        if ($tieneLotes) {
            return;
        }

        $stockTotal = (float) ($pa->stock_fraccion ?? 0);
        if (abs($stockTotal) < 0.0001) {
            return; // sin stock: no hay nada que respaldar
        }

        $stockAnterior = (float) ($pa->stock_costo_anterior ?? 0);
        $stockActual = (float) ($pa->stock_costo_actual ?? 0);
        $costoAnterior = $pa->costo_anterior !== null ? (float) $pa->costo_anterior : null;
        $costoActual = $pa->costo_actual !== null ? (float) $pa->costo_actual : (float) ($pa->costo ?? 0);

        $secuencia = 0;

        // Bucket anterior (más viejo) → primer lote
        if ($stockAnterior > 0 && $costoAnterior !== null) {
            ProductoAlmacenLote::create([
                'producto_almacen_id' => $pa->id,
                'costo' => $costoAnterior,
                'cantidad_inicial' => $stockAnterior,
                'cantidad_restante' => $stockAnterior,
                'secuencia' => ++$secuencia,
            ]);
        }

        // Bucket actual (o todo el stock si no había desglose) → segundo lote.
        // Si los buckets no cuadran con el total, el remanente va aquí.
        $stockActualLote = $stockActual > 0 ? $stockActual : ($stockTotal - max($stockAnterior, 0));
        if (abs($stockActualLote) >= 0.0001) {
            ProductoAlmacenLote::create([
                'producto_almacen_id' => $pa->id,
                'costo' => $costoActual,
                'cantidad_inicial' => $stockActualLote,
                'cantidad_restante' => $stockActualLote,
                'secuencia' => ++$secuencia,
            ]);
        }
    }

    /**
     * Registra un nuevo lote de costo (entrada por recepción o ingreso) y
     * recalcula los derivados.
     *
     * @param array{recepcion_id?: int, compra_id?: string|null, ingreso_salida_id?: int} $origen
     * @param int|null $secuenciaOverride Posición FIFO explícita. Un ingreso que
     *        "hereda" el costo del lote anterior pasa la secuencia de ese lote para
     *        quedar adyacente a él (se consume junto); null = al final (más nuevo).
     */
    public function registrarLote(ProductoAlmacen $pa, float $costo, float $cantidad, array $origen = [], ?int $secuenciaOverride = null): ProductoAlmacenLote
    {
        $this->inicializarDesdeBucketsSiVacio($pa);

        // Recepciones PARCIALES de la MISMA compra: sumar al lote ya existente de
        // esa compra (misma fila, misma posición FIFO de la 1.ª recepción), no
        // crear otra fila. Solo si el costo coincide (debería, con el flete
        // prorrateado sobre el total de la línea); si difiere, fila separada.
        $compraId = $origen['compra_id'] ?? null;
        if ($compraId !== null) {
            $existente = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
                ->where('compra_id', $compraId)
                ->get()
                ->first(fn($l) => abs((float) $l->costo - $costo) < 0.0001);

            if ($existente) {
                $existente->cantidad_inicial = (float) $existente->cantidad_inicial + $cantidad;
                $existente->cantidad_restante = (float) $existente->cantidad_restante + $cantidad;
                $existente->save();

                $this->resyncDerivados($pa);

                return $existente;
            }
        }

        $secuencia = $secuenciaOverride
            ?? ((int) (ProductoAlmacenLote::where('producto_almacen_id', $pa->id)->max('secuencia') ?? 0) + 1);

        $lote = ProductoAlmacenLote::create([
            'producto_almacen_id' => $pa->id,
            'recepcion_id' => $origen['recepcion_id'] ?? null,
            'compra_id' => $compraId,
            'ingreso_salida_id' => $origen['ingreso_salida_id'] ?? null,
            'costo' => $costo,
            'cantidad_inicial' => $cantidad,
            'cantidad_restante' => $cantidad,
            'secuencia' => $secuencia,
        ]);

        $this->resyncDerivados($pa);

        return $lote;
    }

    /**
     * Consume stock por PEPS (lote más viejo primero), registra el detalle de
     * consumo (para anular y para el reporte) y recalcula derivados.
     *
     * Si el stock no alcanza, el faltante se descuenta del lote más nuevo, que
     * puede quedar negativo (igual que el modelo anterior permitía).
     *
     * @param array{tipo: string, id: int}|null $origen  Para persistir el consumo (anulable).
     * @return array{costo_promedio: float, consumos: array<int, array{lote_id:int, cantidad:float, costo:float}>}
     */
    public function consumirLotes(ProductoAlmacen $pa, float $cantidad, ?array $origen = null): array
    {
        $this->inicializarDesdeBucketsSiVacio($pa);

        $restante = max($cantidad, 0);
        $consumos = [];
        $costoTotal = 0.0;
        $cantConsumida = 0.0;

        $lotes = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->where('cantidad_restante', '>', 0)
            ->orderBy('secuencia')->orderBy('id')
            ->get();

        foreach ($lotes as $lote) {
            if ($restante <= 0.0001) {
                break;
            }
            $tomar = min($restante, (float) $lote->cantidad_restante);
            $lote->cantidad_restante = (float) $lote->cantidad_restante - $tomar;
            $lote->save();

            $consumos[] = ['lote_id' => $lote->id, 'cantidad' => $tomar, 'costo' => (float) $lote->costo];
            $costoTotal += $tomar * (float) $lote->costo;
            $cantConsumida += $tomar;
            $restante -= $tomar;
        }

        // Sobreventa: el faltante se carga al lote más nuevo (puede quedar negativo).
        if ($restante > 0.0001) {
            $ultimo = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
                ->orderBy('secuencia', 'desc')->orderBy('id', 'desc')
                ->first();
            $costoUlt = $ultimo ? (float) $ultimo->costo : (float) ($pa->costo ?? 0);
            if ($ultimo) {
                $ultimo->cantidad_restante = (float) $ultimo->cantidad_restante - $restante;
                $ultimo->save();
                $consumos[] = ['lote_id' => $ultimo->id, 'cantidad' => $restante, 'costo' => $costoUlt];
            }
            $costoTotal += $restante * $costoUlt;
            $cantConsumida += $restante;
            $restante = 0;
        }

        if ($origen && ! empty($consumos)) {
            foreach ($consumos as $c) {
                ProductoAlmacenLoteConsumo::create([
                    'lote_id' => $c['lote_id'],
                    'producto_almacen_id' => $pa->id,
                    'cantidad' => $c['cantidad'],
                    'costo' => $c['costo'],
                    'origen_tipo' => $origen['tipo'],
                    'origen_id' => $origen['id'],
                ]);
            }
        }

        $this->resyncDerivados($pa);

        return [
            'costo_promedio' => $cantConsumida > 0 ? $costoTotal / $cantConsumida : 0.0,
            'consumos' => $consumos,
        ];
    }

    /**
     * Simula (SIN MUTAR) el costo promedio PEPS de consumir $cantidad, según los
     * lotes actuales. Para mostrar costo antes de confirmar / cotizaciones.
     */
    public function simularCostoConsumo(ProductoAlmacen $pa, float $cantidad): float
    {
        $restante = max($cantidad, 0);
        $costoTotal = 0.0;
        $cantConsumida = 0.0;

        $lotes = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->where('cantidad_restante', '>', 0)
            ->orderBy('secuencia')->orderBy('id')
            ->get();

        foreach ($lotes as $lote) {
            if ($restante <= 0.0001) {
                break;
            }
            $tomar = min($restante, (float) $lote->cantidad_restante);
            $costoTotal += $tomar * (float) $lote->costo;
            $cantConsumida += $tomar;
            $restante -= $tomar;
        }

        if ($restante > 0.0001) {
            $ultimo = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
                ->orderBy('secuencia', 'desc')->orderBy('id', 'desc')->first();
            $costoUlt = $ultimo ? (float) $ultimo->costo : (float) ($pa->costo ?? 0);
            $costoTotal += $restante * $costoUlt;
            $cantConsumida += $restante;
        }

        return $cantConsumida > 0 ? $costoTotal / $cantConsumida : (float) ($pa->costo ?? 0);
    }

    /**
     * Revierte los consumos de un origen (venta/salida anulada): devuelve el
     * stock a los lotes exactos y borra los registros de consumo.
     */
    public function revertirConsumoPorOrigen(ProductoAlmacen $pa, string $tipo, int|string $id): void
    {
        $consumos = ProductoAlmacenLoteConsumo::where('producto_almacen_id', $pa->id)
            ->where('origen_tipo', $tipo)
            ->where('origen_id', $id)
            ->get();

        foreach ($consumos as $consumo) {
            $lote = ProductoAlmacenLote::find($consumo->lote_id);
            if ($lote) {
                $lote->cantidad_restante = (float) $lote->cantidad_restante + (float) $consumo->cantidad;
                $lote->save();
            }
            $consumo->delete();
        }

        $this->resyncDerivados($pa);
    }

    /**
     * Revierte un consumo si hay registros (venta/salida nueva), o, si no existen
     * (venta creada ANTES del ledger), reingresa $cantidadFallback al lote más
     * nuevo para mantener la invariante sum(lotes) == stock_fraccion.
     *
     * Sirve para la edición de ventas (update), donde una venta vieja no tiene
     * registros de consumo por lote.
     */
    public function revertirConsumoOReingresar(ProductoAlmacen $pa, string $tipo, int|string $id, float $cantidadFallback): void
    {
        $consumos = ProductoAlmacenLoteConsumo::where('producto_almacen_id', $pa->id)
            ->where('origen_tipo', $tipo)
            ->where('origen_id', $id)
            ->get();

        if ($consumos->isNotEmpty()) {
            foreach ($consumos as $consumo) {
                $lote = ProductoAlmacenLote::find($consumo->lote_id);
                if ($lote) {
                    $lote->cantidad_restante = (float) $lote->cantidad_restante + (float) $consumo->cantidad;
                    $lote->save();
                }
                $consumo->delete();
            }
            $this->resyncDerivados($pa);
            return;
        }

        // Venta legacy sin registros: reingresar al lote más nuevo (aprox.) para
        // no descuadrar el stock total. No es FIFO exacto, pero conserva el stock.
        $this->reingresarStock($pa, $cantidadFallback);
    }

    /**
     * Devuelve $cantidad de stock al lote más nuevo (o crea uno si no hay), sin
     * registrar consumo. Uso interno para reversiones legacy.
     */
    public function reingresarStock(ProductoAlmacen $pa, float $cantidad): void
    {
        if (abs($cantidad) < 0.0001) {
            $this->resyncDerivados($pa);
            return;
        }

        $ultimo = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->orderBy('secuencia', 'desc')->orderBy('id', 'desc')->first();

        if ($ultimo) {
            $ultimo->cantidad_restante = (float) $ultimo->cantidad_restante + $cantidad;
            $ultimo->save();
            $this->resyncDerivados($pa);
        } else {
            $this->registrarLote($pa, (float) ($pa->costo ?? 0), $cantidad);
        }
    }

    /**
     * Revierte la entrada de una recepción anulada (puede quedar negativo si ya
     * se vendió, igual que antes). Como las recepciones parciales de una misma
     * compra se FUSIONAN en un solo lote, se resta exactamente la $cantidad de la
     * recepción anulada (no la cantidad inicial del lote, que puede incluir otras
     * recepciones). El lote se busca por recepción y, si no, por la compra.
     */
    public function revertirLotesPorRecepcion(ProductoAlmacen $pa, int $recepcionId, ?string $compraId = null, ?float $cantidad = null): void
    {
        $lote = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->where('recepcion_id', $recepcionId)
            ->first();

        // La recepción anulada se fusionó en el lote creado por OTRA recepción
        // de la misma compra → buscar por compra.
        if (! $lote && $compraId !== null) {
            $lote = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
                ->where('compra_id', $compraId)
                ->orderBy('secuencia')->orderBy('id')
                ->first();
        }

        if ($lote) {
            $resta = $cantidad ?? (float) $lote->cantidad_inicial;
            $lote->cantidad_restante = (float) $lote->cantidad_restante - $resta;
            $lote->cantidad_inicial = max((float) $lote->cantidad_inicial - $resta, 0);
            $lote->save();
        }

        $this->resyncDerivados($pa);
    }

    /**
     * Revierte la entrada de un ingreso manual anulado (mismo criterio que
     * recepción). Para salidas usar {@see revertirConsumoPorOrigen()}.
     */
    public function revertirLotesPorIngresoSalida(ProductoAlmacen $pa, int $ingresoSalidaId): void
    {
        $lotes = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->where('ingreso_salida_id', $ingresoSalidaId)
            ->get();

        foreach ($lotes as $lote) {
            $lote->cantidad_restante = (float) $lote->cantidad_restante - (float) $lote->cantidad_inicial;
            $lote->save();
        }

        $this->resyncDerivados($pa);
    }

    /**
     * Recalcula y guarda los campos derivados de productoalmacen a partir de los
     * lotes. Mantiene la compatibilidad con todo lo que lee costo/stock_fraccion
     * y los buckets legacy.
     */
    public function resyncDerivados(ProductoAlmacen $pa): void
    {
        $lotes = ProductoAlmacenLote::where('producto_almacen_id', $pa->id)
            ->orderBy('secuencia')->orderBy('id')
            ->get();

        // Stock total = suma de TODOS los restantes (incluye negativos por sobreventa).
        $stockTotal = (float) $lotes->sum(fn($l) => (float) $l->cantidad_restante);

        // Para costo/buckets solo cuentan lotes con stock positivo.
        $positivos = $lotes->filter(fn($l) => (float) $l->cantidad_restante > 0.0001)->values();

        $sumStockPos = (float) $positivos->sum(fn($l) => (float) $l->cantidad_restante);
        $sumCostoPos = (float) $positivos->sum(fn($l) => (float) $l->cantidad_restante * (float) $l->costo);

        $costoPonderado = $sumStockPos > 0
            ? $sumCostoPos / $sumStockPos
            : (float) ($pa->costo ?? 0); // sin stock positivo: conservar último costo

        // Buckets legacy:
        //  - "anterior" = tramo de lotes MÁS VIEJOS que comparten el mismo costo,
        //    PERO solo si después hay lotes con costo más nuevo (distinto).
        //  - "actual" = el resto (ponderado).
        //  - Si solo hay UN costo vivo (un lote, o varios al mismo costo, p.ej.
        //    recepciones parciales de la misma compra) → todo va a "actual" y
        //    anterior queda vacío, igual que el modelo original de 2 buckets.
        $primero = $positivos->first();
        if ($primero) {
            $costoRef = (float) $primero->costo;
            $grupoViejo = $positivos->takeWhile(fn($l) => abs((float) $l->costo - $costoRef) < 0.0001);
            $resto = $positivos->slice($grupoViejo->count());

            if ($resto->isEmpty()) {
                // Un solo costo vivo → es el ACTUAL (no hay lote más nuevo).
                $stockAnterior = 0.0;
                $costoAnterior = null;
                $stockActual = $stockTotal;
                $costoActual = $costoRef;
            } else {
                $stockAnterior = (float) $grupoViejo->sum(fn($l) => (float) $l->cantidad_restante);
                $costoAnterior = $costoRef;
                $stockActual = $stockTotal - $stockAnterior;

                $sumStockResto = (float) $resto->sum(fn($l) => (float) $l->cantidad_restante);
                $sumCostoResto = (float) $resto->sum(fn($l) => (float) $l->cantidad_restante * (float) $l->costo);
                $costoActual = $sumStockResto > 0 ? $sumCostoResto / $sumStockResto : $costoPonderado;
            }
        } else {
            // Sin stock positivo (0 o negativo).
            $stockAnterior = 0.0;
            $costoAnterior = null;
            $stockActual = $stockTotal;
            $costoActual = $costoPonderado;
        }

        $pa->stock_fraccion = $stockTotal;
        $pa->costo = $costoPonderado;
        $pa->costo_con_flete = $costoPonderado;
        $pa->costo_anterior = $costoAnterior;
        $pa->stock_costo_anterior = $stockAnterior;
        $pa->costo_actual = $costoActual;
        $pa->stock_costo_actual = $stockActual;
        $pa->save();
    }
}
