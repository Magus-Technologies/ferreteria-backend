<?php

namespace App\Services\Kardex;

use App\Models\KardexInventario;
use Illuminate\Support\Facades\DB;

class KardexInventarioService
{
    /**
     * Registra un movimiento en kardex de inventario
     */
    public function registrar(array $data)
    {
        // 1. Asegurar que tenemos el producto_almacen_id si no viene
        $almacenId = $data['almacen_id'] ?? null;
        $productoId = $data['producto_id'] ?? null;
        $productoAlmacenId = $data['producto_almacen_id'] ?? null;

        $productoAlmacen = null;
        if ($productoAlmacenId) {
            $productoAlmacen = \App\Models\ProductoAlmacen::find($productoAlmacenId);
        } elseif ($productoId && $almacenId) {
            $productoAlmacen = \App\Models\ProductoAlmacen::where('producto_id', $productoId)
                ->where('almacen_id', $almacenId)
                ->first();
        }

        // 2. Calcular el factor de conversión de la unidad derivada
        $cantidad = (float) ($data['cantidad'] ?? 1);
        $cantidadFraccion = (float) ($data['cantidad_fraccion'] ?? $cantidad);
        if (isset($data['factor'])) {
            $factor = (float) $data['factor'];
        } else {
            $factor = $cantidad > 0 ? ($cantidadFraccion / $cantidad) : 1;
        }

        // 3. Cantidades Base (Fracciones)
        $cantIngresoBase = (float) ($data['entrada'] ?? 0);
        $cantSalidaBase = (float) ($data['salida'] ?? 0);

        // 4. Obtener el stock actual real en fracción
        $stockAnteriorBase = 0;
        if (isset($data['stock_anterior_override'])) {
            $stockAnteriorBase = (float) $data['stock_anterior_override'];
            unset($data['stock_anterior_override']);
        } elseif ($productoAlmacen) {
            $stockAnteriorBase = (float) $productoAlmacen->stock_fraccion;
        }

        if ($productoAlmacen) {
            $data['producto_almacen_id'] = $productoAlmacen->id;
            $data['producto_id'] = $productoAlmacen->producto_id;
            $data['almacen_id'] = $productoAlmacen->almacen_id;
        }

        if (!isset($data['usuario_id'])) {
            $data['usuario_id'] = auth()->id();
        }

        // 5. Costos: Nominal (transacción) y Base (promedio)
        $costoNominal = isset($data['costo']) ? (float) $data['costo'] : 0;
        $costoBaseTransaccion = ($factor > 0) ? ($costoNominal / $factor) : $costoNominal;

        $costoAnteriorBase = $productoAlmacen ? (float) $productoAlmacen->costo : 0;
        $costoActualBase = $costoAnteriorBase;

        if ($cantIngresoBase > 0) {
            $totalStockNuevo = $stockAnteriorBase + $cantIngresoBase;
            if ($totalStockNuevo > 0) {
                // Cálculo de Costo Promedio Ponderado (siempre en base unitaria)
                $valorAnterior = $stockAnteriorBase * $costoAnteriorBase;
                $valorEntrada = $cantIngresoBase * $costoBaseTransaccion;
                $costoActualBase = ($valorAnterior + $valorEntrada) / $totalStockNuevo;

                if ($productoAlmacen) {
                    $productoAlmacen->update(['costo' => $costoActualBase]);
                }
            }
        }

        // 6. Preparar datos finales para persistencia
        $nuevoStockBase = $stockAnteriorBase + $cantIngresoBase - $cantSalidaBase;

        $data['stock_anterior'] = $stockAnteriorBase;
        $data['cant_ingreso'] = $cantIngresoBase;
        $data['cant_salida'] = $cantSalidaBase;
        $data['stock_actual'] = $nuevoStockBase;

        // Mantenemos redundancia de columnas 'entrada' y 'salida' para compatibilidad con frontend si se requiere
        $data['entrada'] = $cantIngresoBase;
        $data['salida'] = $cantSalidaBase;

        // Guardamos los costos
        $data['costo'] = $costoNominal;
        $data['costo_anterior'] = $costoAnteriorBase;
        $data['costo_actual'] = $costoActualBase;

        // Limpiar factor si no está en fillable (aunque sí lo agregamos al modelo antes)
        // unset($data['factor']); 

        \Log::info('KardexInventario registrar:', [
            'unidad' => $data['unidad'] ?? '-',
            'nominal' => $data['cantidad'],
            'base' => $cantIngresoBase ?: $cantSalidaBase,
            'costo_nominal' => $costoNominal,
            'costo_base' => $costoBaseTransaccion,
            'costo_actual_base' => $costoActualBase,
        ]);

        return KardexInventario::create($data);
    }

    /**
     * Registra una compra en kardex inventario (referencia)
     */
    public function registrarCompraReferencia($compra, $productoAlmacen, $unidad, $costo, $orden = 0)
    {
        // Extraer el valor del enum si es necesario
        $tipoDocumentoValue = $compra->tipo_documento instanceof \BackedEnum
            ? $compra->tipo_documento->value
            : (string) $compra->tipo_documento;

        $tipoDocumento = match ($tipoDocumentoValue) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $tipoDocumentoValue,
        };

        return $this->registrar([
            'tipo' => 'compra',
            'movimiento' => 'REFERENCIA',
            'fecha' => $compra->fecha,
            'documento' => "Compra {$tipoDocumento} {$compra->serie}-{$compra->numero} (Creada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => 0,
            'referencia_id' => $compra->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'proveedor_id' => $compra->proveedor_id,
            'proveedor_nombre' => $compra->proveedor?->razon_social ?? $compra->proveedor?->nombre_comercial ?? 'Sin proveedor',
            'almacen_id' => $compra->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una compra procesada en kardex inventario
     */
    public function registrarCompraProcesada($compra, $productoAlmacen, $unidad, $costo, $orden = 1)
    {
        $tipoDocumentoValue = $compra->tipo_documento instanceof \BackedEnum
            ? $compra->tipo_documento->value
            : (string) $compra->tipo_documento;

        $tipoDocumento = match ($tipoDocumentoValue) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $tipoDocumentoValue,
        };

        return $this->registrar([
            'tipo' => 'compra',
            'movimiento' => 'COMPRA',
            'fecha' => $compra->fecha,
            'documento' => "Compra {$tipoDocumento} {$compra->serie}-{$compra->numero} (Procesada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $compra->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'proveedor_id' => $compra->proveedor_id,
            'proveedor_nombre' => $compra->proveedor?->razon_social ?? $compra->proveedor?->nombre_comercial ?? 'Sin proveedor',
            'almacen_id' => $compra->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una recepción en kardex inventario
     */
    public function registrarRecepcion($recepcion, $productoAlmacen, $unidad, $costo, $orden = 2, $stockAnteriorOverride = null)
    {
        // Obtener proveedor de la compra asociada a la recepción
        $proveedorId = null;
        $proveedorNombre = null;

        if ($recepcion->compra_id) {
            $compra = \App\Models\Compra::with('proveedor')->find($recepcion->compra_id);
            if ($compra && $compra->proveedor) {
                $proveedorId = $compra->proveedor_id;
                $proveedorNombre = $compra->proveedor->razon_social ?? $compra->proveedor->nombre_comercial ?? 'Sin proveedor';
            }
        }

        $dataToRegister = [
            'tipo' => 'recepcion',
            'movimiento' => 'ENTRADA',
            'fecha' => $recepcion->fecha,
            'documento' => "Recepcion REC-{$recepcion->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'flete' => $unidad->flete ?? 0, // Incluir flete para el costo promedio
            'factor' => $unidad->factor,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $recepcion->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'proveedor_id' => $proveedorId,
            'proveedor_nombre' => $proveedorNombre,
            'almacen_id' => $productoAlmacen->almacen_id,
            'orden' => $orden,
        ];

        // Si se proporciona un stock anterior específico, usarlo en lugar del actual
        if ($stockAnteriorOverride !== null) {
            $dataToRegister['stock_anterior_override'] = $stockAnteriorOverride;
        }

        return $this->registrar($dataToRegister);
    }

    /**
     * Registra una anulación de recepción en kardex inventario
     */
    public function registrarAnulacionRecepcion($recepcion, $productoAlmacen, $unidad, $costo, $orden = 5, $stockAnteriorOverride = null)
    {
        // Obtener proveedor de la compra asociada a la recepción
        $proveedorId = null;
        $proveedorNombre = null;

        if ($recepcion->compra_id) {
            $compra = \App\Models\Compra::with('proveedor')->find($recepcion->compra_id);
            if ($compra && $compra->proveedor) {
                $proveedorId = $compra->proveedor_id;
                $proveedorNombre = $compra->proveedor->razon_social ?? $compra->proveedor->nombre_comercial ?? 'Sin proveedor';
            }
        }

        $dataToRegister = [
            'tipo' => 'recepcion_anulada',
            'movimiento' => 'ANULACION',
            'fecha' => now(),
            'documento' => "Recepcion REC-{$recepcion->numero} (Anulada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $recepcion->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'proveedor_id' => $proveedorId,
            'proveedor_nombre' => $proveedorNombre,
            'almacen_id' => $productoAlmacen->almacen_id,
            'orden' => $orden,
        ];

        if ($stockAnteriorOverride !== null) {
            $dataToRegister['stock_anterior_override'] = $stockAnteriorOverride;
        }

        return $this->registrar($dataToRegister);
    }

    /**
     * Registra un ingreso en kardex inventario
     */
    public function registrarIngreso($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 3, $stockAnteriorOverride = null)
    {
        $data = [
            'tipo' => 'cuadre',
            'movimiento' => 'ENTRADA',
            'fecha' => $ingresoSalida->fecha,
            'documento' => "Ingreso ING-{$ingresoSalida->serie}-{$ingresoSalida->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $ingresoSalida->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $ingresoSalida->almacen_id,
            'orden' => $orden,
        ];
        if ($stockAnteriorOverride !== null) {
            $data['stock_anterior_override'] = $stockAnteriorOverride;
        }
        return $this->registrar($data);
    }

    /**
     * Registra una salida en kardex inventario
     */
    public function registrarSalida($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 4, $stockAnteriorOverride = null)
    {
        $data = [
            'tipo' => 'cuadre',
            'movimiento' => 'SALIDA',
            'fecha' => $ingresoSalida->fecha,
            'documento' => "Salida SAL-{$ingresoSalida->serie}-{$ingresoSalida->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $ingresoSalida->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $ingresoSalida->almacen_id,
            'orden' => $orden,
        ];
        if ($stockAnteriorOverride !== null) {
            $data['stock_anterior_override'] = $stockAnteriorOverride;
        }
        return $this->registrar($data);
    }

    /**
     * Registra una anulación de ingreso en kardex inventario
     */
    public function registrarAnulacionIngreso($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 6, $stockAnteriorOverride = null)
    {
        $data = [
            'tipo' => 'cuadre',
            'movimiento' => 'ANULADA_ENTRADA',
            'fecha' => now(),
            'documento' => "Ingreso ING-{$ingresoSalida->serie}-{$ingresoSalida->numero} (Anulado)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $ingresoSalida->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $ingresoSalida->almacen_id,
            'orden' => $orden,
        ];
        if ($stockAnteriorOverride !== null) {
            $data['stock_anterior_override'] = $stockAnteriorOverride;
        }
        return $this->registrar($data);
    }

    /**
     * Registra una anulación de salida en kardex inventario
     */
    public function registrarAnulacionSalida($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 7, $stockAnteriorOverride = null)
    {
        $data = [
            'tipo' => 'cuadre',
            'movimiento' => 'ANULADA_SALIDA',
            'fecha' => now(),
            'documento' => "Salida SAL-{$ingresoSalida->serie}-{$ingresoSalida->numero} (Anulada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $ingresoSalida->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $ingresoSalida->almacen_id,
            'orden' => $orden,
        ];
        if ($stockAnteriorOverride !== null) {
            $data['stock_anterior_override'] = $stockAnteriorOverride;
        }
        return $this->registrar($data);
    }

    /**
     * Registra cambio de compra de 'cr' (creada) a 'pr' (procesada)
     * Se registra cuando la compra pasa a estado procesado
     */
    public function registrarCompraProcesadaDesdeCreada($compra, $productoAlmacen, $unidad, $costo, $orden = 1)
    {
        $tipoDocumentoValue = $compra->tipo_documento instanceof \BackedEnum
            ? $compra->tipo_documento->value
            : (string) $compra->tipo_documento;

        $tipoDocumento = match ($tipoDocumentoValue) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $tipoDocumentoValue,
        };

        return $this->registrar([
            'tipo' => 'compra',
            'movimiento' => 'COMPRA',
            'fecha' => $compra->fecha,
            'documento' => "Compra {$tipoDocumento} {$compra->serie}-{$compra->numero} (Procesada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $compra->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'proveedor_id' => $compra->proveedor_id,
            'proveedor_nombre' => $compra->proveedor?->razon_social ?? $compra->proveedor?->nombre_comercial ?? 'Sin proveedor',
            'almacen_id' => $compra->almacen_id,
            'orden' => $orden,
        ]);
    }

    public function getPaginated(
        ?int $productoId,
        ?int $almacenId,
        ?string $desde,
        ?string $hasta,
        ?string $tipo,
        int $perPage = 50,
        int $page = 1
    ) {
        $query = DB::table('kardex_inventarios')
            ->leftJoin('producto', 'kardex_inventarios.producto_id', '=', 'producto.id')
            ->select('kardex_inventarios.*', 'producto.unidades_contenidas');

        if ($productoId) {
            $query->where('producto_id', $productoId);
        }

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        if ($desde) {
            $query->whereDate('fecha', '>=', $desde);
        }

        if ($hasta) {
            $query->whereDate('fecha', '<=', $hasta);
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        $total = $query->count();

        // Obtener TODAS las filas ordenadas DESCENDENTE (más recientes primero)
        $allRows = $query->orderBy('fecha', 'desc')->orderBy('orden', 'desc')->get();

        // Si proveedor_nombre está vacío pero proveedor_id existe, buscar el nombre
        foreach ($allRows as $row) {
            if (empty($row->proveedor_nombre) && !empty($row->proveedor_id)) {
                $proveedor = DB::table('proveedores')->where('id', $row->proveedor_id)->first();
                if ($proveedor) {
                    $row->proveedor_nombre = $proveedor->razon_social ?? $proveedor->nombre_comercial ?? 'Sin proveedor';
                }
            }
        }

        // Aplicar paginación a los resultados
        if ($perPage == -1) {
            $rowsWithStock = $allRows;
        } else {
            $offset = ($page - 1) * $perPage;
            $rowsWithStock = $allRows->slice($offset, $perPage);
        }

        return response()->json([
            'data' => $rowsWithStock,
            'total' => $total,
            'current_page' => (int) ($perPage == -1 ? 1 : $page),
            'per_page' => $perPage,
            'last_page' => (int) ($perPage == -1 ? 1 : ceil($total / $perPage)),
        ]);
    }
}
