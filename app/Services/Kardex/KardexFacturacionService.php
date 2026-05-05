<?php

namespace App\Services\Kardex;

use App\Models\KardexFacturacion;
use Illuminate\Support\Facades\DB;

class KardexFacturacionService
{
    /**
     * Registra un movimiento en kardex de facturación
     * CORRECCIÓN: Usa el factor explícitamente pasado en lugar de recalcularlo
     */
    public function registrar(array $data)
    {
        // 1. Obtener el factor
        $factor = (float) ($data['factor'] ?? 1);
        if ($factor <= 0) {
            $cantidad = (float) ($data['cantidad'] ?? 1);
            $cantidadFraccion = (float) ($data['cantidad_fraccion'] ?? $cantidad);
            $factor = $cantidad > 0 ? ($cantidadFraccion / $cantidad) : 1;
        }

        // 2. Cantidades Base (Fracciones)
        $cantIngresoBase = (float) ($data['entrada'] ?? 0);
        $cantSalidaBase = (float) ($data['salida'] ?? 0);

        // 3. Cantidad Nominal (en la unidad del documento)
        if (!isset($data['cantidad']) || (float) $data['cantidad'] == 0) {
            if ($factor > 0) {
                $data['cantidad'] = ($cantIngresoBase > 0) ? ($cantIngresoBase / $factor) : ($cantSalidaBase / $factor);
            } else {
                $data['cantidad'] = $cantIngresoBase + $cantSalidaBase;
            }
        }

        // 4. Obtener el stock actual real en fracción
        $saldoAnteriorBase = 0;
        if (isset($data['stock_anterior_override'])) {
            $saldoAnteriorBase = (float) $data['stock_anterior_override'];
            unset($data['stock_anterior_override']);
        } else {
            $productoAlmacen = DB::table('productoalmacen')
                ->where('producto_id', $data['producto_id'])
                ->where('almacen_id', $data['almacen_id'])
                ->first();
            if ($productoAlmacen) {
                $saldoAnteriorBase = (float) $productoAlmacen->stock_fraccion;
            }
        }

        // 5. Capturar costos para trazabilidad
        $costoAnteriorBase = 0;
        $productoAlmacenRow = DB::table('productoalmacen')
            ->where('producto_id', $data['producto_id'])
            ->where('almacen_id', $data['almacen_id'])
            ->first();
        if ($productoAlmacenRow) {
            $costoAnteriorBase = (float) $productoAlmacenRow->costo;
        }

        // El costo nominal (transacción) suele ser el precio de venta o el costo unitario
        $costoNominal = isset($data['costo']) ? (float) $data['costo'] : 0;

        // 6. Preparar datos finales para persistencia
        $nuevoSaldoBase = $saldoAnteriorBase + $cantIngresoBase - $cantSalidaBase;

        $dataToSave = array_merge($data, [
            'stock_anterior' => $saldoAnteriorBase,
            'cant_ingreso' => $cantIngresoBase,
            'cant_salida' => $cantSalidaBase,
            'stock_actual' => $nuevoSaldoBase,
            'entrada' => $cantIngresoBase,
            'salida' => $cantSalidaBase,
            'costo' => $costoNominal,
            'costo_anterior' => $costoAnteriorBase,
            'costo_actual' => $costoAnteriorBase, // Las ventas no cambian el costo promedio
            'factor' => $factor,
        ]);

        \Log::info('KardexFacturacion registrar:', [
            'nominal' => $dataToSave['cantidad'],
            'base' => $cantIngresoBase ?: $cantSalidaBase,
            'costo_anterior' => $costoAnteriorBase,
        ]);

        return KardexFacturacion::create($dataToSave);
    }

    /**
     * Registra una venta en kardex facturación (cuando se crea)
     * Solo se registra si estado_de_venta != 'ee' (no en espera)
     * CORRECCIÓN: Pasar factor explícitamente
     */
    public function registrarVenta($venta, $productoAlmacen, $unidad, $costo, $orden = 1, $stockAnterior = null)
    {
        $tipoDocumento = match ($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        // Determinar si es contado o crédito
        $movimiento = $venta->forma_de_pago->value === 'co' ? 'VENTA CONTADO' : 'VENTA CRÉDITO';

        $cantSalida = $unidad['cantidad'] * $unidad['factor'];

        // Obtener el nombre del cliente directamente desde la BD si no está cargado
        $clienteNombre = 'Sin cliente';
        if ($venta->cliente_id) {
            if ($venta->relationLoaded('cliente') && $venta->cliente) {
                $clienteNombre = $this->obtenerNombreCliente($venta->cliente);
            } else {
                // Buscar el cliente directamente en la BD
                $cliente = \App\Models\Cliente::find($venta->cliente_id);
                if ($cliente) {
                    $clienteNombre = $this->obtenerNombreCliente($cliente);
                }
            }
        }

        $data = [
            'tipo' => 'venta',
            'movimiento' => $movimiento,
            'fecha' => $venta->fecha,
            'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad['unidad_derivada_inmutable_name'],
            'cantidad' => $unidad['cantidad'],
            'cantidad_fraccion' => $cantSalida,
            'factor' => (float) $unidad['factor'], // CORRECCIÓN: Pasar factor explícitamente
            'precio' => $unidad['precio'],
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $cantSalida,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $clienteNombre,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ];

        // Si se proporciona stock anterior en fracción, usarlo
        if ($stockAnterior !== null) {
            $data['stock_anterior_override'] = $stockAnterior;
        }

        return $this->registrar($data);
    }

    /**
     * Registra una devolución de venta en kardex facturación (cuando se anula)
     * Se registra cuando estado_de_venta cambia a 'an' (anulada)
     * CORRECCIÓN: Pasar factor explícitamente
     */
    public function registrarDevolucionVenta($venta, $productoAlmacen, $unidad, $costo, $orden = 2)
    {
        $tipoDocumento = match ($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        // Primero marcar los registros originales como anulados (respetando si es CONTADO o CRÉDITO)
        KardexFacturacion::where('referencia_id', $venta->id)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CONTADO')
            ->update(['movimiento' => 'VENTA CONTADO (ANULADA)']);

        KardexFacturacion::where('referencia_id', $venta->id)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CRÉDITO')
            ->update(['movimiento' => 'VENTA CRÉDITO (ANULADA)']);

        // También marcar las editadas como anuladas
        KardexFacturacion::where('referencia_id', $venta->id)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CONTADO (EDITADA)')
            ->update(['movimiento' => 'VENTA CONTADO (ANULADA)']);

        KardexFacturacion::where('referencia_id', $venta->id)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CRÉDITO (EDITADA)')
            ->update(['movimiento' => 'VENTA CRÉDITO (ANULADA)']);

        // Obtener el nombre del cliente directamente desde la BD si no está cargado
        $clienteNombre = 'Sin cliente';
        if ($venta->cliente_id) {
            if ($venta->relationLoaded('cliente') && $venta->cliente) {
                $clienteNombre = $this->obtenerNombreCliente($venta->cliente);
            } else {
                $cliente = \App\Models\Cliente::find($venta->cliente_id);
                if ($cliente) {
                    $clienteNombre = $this->obtenerNombreCliente($cliente);
                }
            }
        }

        // Luego registrar la devolución
        return $this->registrar([
            'tipo' => 'venta',
            'movimiento' => 'DEVOLUCIÓN',
            'fecha' => now(),
            'documento' => "Anulación {$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'factor' => (float) $unidad->factor, // CORRECCIÓN: Pasar factor explícitamente
            'precio' => $unidad->precio,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $clienteNombre,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una venta cuando cambia de 'ee' (en espera) a otro estado
     * Se registra cuando la venta pasa de borrador a creada
     * CORRECCIÓN: Pasar factor explícitamente
     */
    public function registrarVentaDesdeEspera($venta, $productoAlmacen, $unidad, $costo, $orden = 1)
    {
        $tipoDocumento = match ($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        // Determinar si es contado o crédito
        $movimiento = $venta->forma_de_pago->value === 'co' ? 'VENTA CONTADO' : 'VENTA CRÉDITO';

        // Obtener el nombre del cliente directamente desde la BD si no está cargado
        $clienteNombre = 'Sin cliente';
        if ($venta->cliente_id) {
            if ($venta->relationLoaded('cliente') && $venta->cliente) {
                $clienteNombre = $this->obtenerNombreCliente($venta->cliente);
            } else {
                $cliente = \App\Models\Cliente::find($venta->cliente_id);
                if ($cliente) {
                    $clienteNombre = $this->obtenerNombreCliente($cliente);
                }
            }
        }

        return $this->registrar([
            'tipo' => 'venta',
            'movimiento' => $movimiento,
            'fecha' => $venta->fecha,
            'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'factor' => (float) $unidad->factor, // CORRECCIÓN: Pasar factor explícitamente
            'precio' => $unidad->precio,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $clienteNombre,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Actualiza el kardex cuando se edita una venta
     * Mantiene los registros originales y crea ajustes por las diferencias
     */
    public function actualizarKardexVentaEditada($ventaId)
    {
        // Marcar los registros originales como editados (respetando si es CONTADO o CRÉDITO)
        KardexFacturacion::where('referencia_id', $ventaId)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CONTADO')
            ->update(['movimiento' => 'VENTA CONTADO (EDITADA)']);

        KardexFacturacion::where('referencia_id', $ventaId)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA CRÉDITO')
            ->update(['movimiento' => 'VENTA CRÉDITO (EDITADA)']);

        // Obtener registros antiguos del kardex (antes de la edición)
        $registrosAntiguos = KardexFacturacion::where('referencia_id', $ventaId)
            ->where('tipo', 'venta')
            ->whereIn('movimiento', ['VENTA CONTADO (EDITADA)', 'VENTA CRÉDITO (EDITADA)'])
            ->get()
            ->keyBy(function ($item) {
                return $item->producto_id . '_' . $item->unidad;
            });

        // Obtener la venta actualizada con todas sus relaciones
        $venta = \App\Models\Venta::with([
            'productosPorAlmacen.productoAlmacen.producto',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'cliente',
        ])->findOrFail($ventaId);

        $clienteNombre = $this->obtenerNombreCliente($venta->cliente);

        // Construir mapa de cantidades nuevas
        $cantidadesNuevas = [];
        foreach ($venta->productosPorAlmacen as $detalle) {
            $productoAlmacen = $detalle->productoAlmacen;
            if (!$productoAlmacen)
                continue;

            foreach ($detalle->unidadesDerivadas as $ud) {
                $key = $productoAlmacen->producto_id . '_' . $ud->unidadDerivadaInmutable->name;
                $cantidadesNuevas[$key] = [
                    'producto_almacen' => $productoAlmacen,
                    'unidad' => $ud,
                    'costo' => (float) $detalle->costo,
                ];
            }
        }

        $tipoDocumento = match ($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        // Obtener el último orden usado en kardex para esta venta
        $ultimoOrden = KardexFacturacion::where('referencia_id', $ventaId)
            ->where('tipo', 'venta')
            ->max('orden') ?? 0;
        $orden = $ultimoOrden + 1;

        // Comparar y crear ajustes
        foreach ($cantidadesNuevas as $key => $datos) {
            $productoAlmacen = $datos['producto_almacen'];
            $unidadNueva = $datos['unidad'];
            $costo = $datos['costo'];

            $cantidadNuevaFraccion = $unidadNueva->cantidad * $unidadNueva->factor;

            if (isset($registrosAntiguos[$key])) {
                // Producto existía en la venta original
                $registroAntiguo = $registrosAntiguos[$key];
                $cantidadAntiguaFraccion = (float) $registroAntiguo->salida;

                $diferencia = $cantidadNuevaFraccion - $cantidadAntiguaFraccion;

                if (abs($diferencia) > 0.001) { // Hay cambio en cantidad
                    if ($diferencia > 0) {
                        // Aumentó la cantidad: crear ajuste de SALIDA
                        $this->registrarAjustePorEdicion(
                            $venta,
                            $productoAlmacen,
                            $unidadNueva,
                            $costo,
                            abs($diferencia),
                            'salida',
                            $orden,
                            $tipoDocumento,
                            $clienteNombre
                        );
                    } else {
                        // Disminuyó la cantidad: crear ajuste de ENTRADA (devolución)
                        $this->registrarAjustePorEdicion(
                            $venta,
                            $productoAlmacen,
                            $unidadNueva,
                            $costo,
                            abs($diferencia),
                            'entrada',
                            $orden,
                            $tipoDocumento,
                            $clienteNombre
                        );
                    }
                    $orden++;
                }
            } else {
                // Producto nuevo agregado en la edición
                $this->registrarAjustePorEdicion(
                    $venta,
                    $productoAlmacen,
                    $unidadNueva,
                    $costo,
                    $cantidadNuevaFraccion,
                    'salida',
                    $orden,
                    $tipoDocumento,
                    $clienteNombre
                );
                $orden++;
            }
        }

        // Productos eliminados en la edición (estaban antes pero ya no están)
        foreach ($registrosAntiguos as $key => $registroAntiguo) {
            if (!isset($cantidadesNuevas[$key])) {
                // Producto fue eliminado: devolver todo al stock
                $cantidadAntiguaFraccion = (float) $registroAntiguo->salida;

                // Determinar el tipo de venta para el ajuste
                $tipoVenta = $venta->forma_de_pago->value === 'co' ? 'CONTADO' : 'CRÉDITO';
                $movimiento = "AJUSTE POR EDICIÓN ({$tipoVenta})";

                // Crear ajuste de ENTRADA para devolver al stock
                $data = [
                    'tipo' => 'venta',
                    'movimiento' => $movimiento,
                    'fecha' => now(),
                    'documento' => "Ajuste {$tipoDocumento} {$venta->serie}-{$venta->numero} (Producto eliminado)",
                    'unidad' => $registroAntiguo->unidad,
                    'cantidad' => $registroAntiguo->cantidad,
                    'cantidad_fraccion' => $cantidadAntiguaFraccion,
                    'precio' => $registroAntiguo->precio,
                    'costo' => $registroAntiguo->costo,
                    'entrada' => $cantidadAntiguaFraccion,
                    'salida' => 0,
                    'referencia_id' => $venta->id,
                    'producto_id' => $registroAntiguo->producto_id,
                    'producto_nombre' => $registroAntiguo->producto_nombre,
                    'producto_codigo' => $registroAntiguo->producto_codigo,
                    'cliente_id' => $venta->cliente_id,
                    'cliente_nombre' => $clienteNombre,
                    'almacen_id' => $venta->almacen_id,
                    'orden' => $orden,
                ];

                $this->registrar($data);
                $orden++;
            }
        }
    }

    /**
     * Registra un ajuste por edición de venta en kardex facturación
     * CORRECCIÓN: Pasar factor explícitamente
     */
    private function registrarAjustePorEdicion($venta, $productoAlmacen, $unidad, $costo, $cantidadFraccion, $tipo, $orden, $tipoDocumento, $clienteNombre = 'Sin cliente')
    {
        $cantidadUnidad = $cantidadFraccion / $unidad->factor;

        // Determinar el tipo de venta para el ajuste
        $tipoVenta = $venta->forma_de_pago->value === 'co' ? 'CONTADO' : 'CRÉDITO';
        $movimiento = "AJUSTE POR EDICIÓN ({$tipoVenta})";

        $data = [
            'tipo' => 'venta',
            'movimiento' => $movimiento,
            'fecha' => now(),
            'documento' => "Ajuste {$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $cantidadUnidad,
            'cantidad_fraccion' => $cantidadFraccion,
            'factor' => (float) $unidad->factor, // CORRECCIÓN: Pasar factor explícitamente
            'precio' => $unidad->precio,
            'costo' => $costo,
            'entrada' => $tipo === 'entrada' ? $cantidadFraccion : 0,
            'salida' => $tipo === 'salida' ? $cantidadFraccion : 0,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $clienteNombre,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ];

        return $this->registrar($data);
    }

    /**
     * Marca una venta como editada en kardex facturación
     */
    public function marcarVentaComoEditada($ventaId)
    {
        $this->actualizarKardexVentaEditada($ventaId);
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
        $query = DB::table('kardex_facturacions')
            ->leftJoin('producto', 'kardex_facturacions.producto_id', '=', 'producto.id')
            ->select('kardex_facturacions.*', 'producto.unidades_contenidas');

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

        // Obtener TODAS las filas ordenadas ASCENDENTE para calcular stock acumulado correctamente
        // (necesitamos calcular desde el más antiguo al más reciente)
        $allRows = $query->orderBy('fecha', 'asc')->orderBy('orden', 'asc')->get();

        // Calcular stock acumulado para TODAS las filas
        $stockPorProductoAlmacen = []; // Rastrear stock actual por producto-almacén
        $rowsWithStockAll = [];

        foreach ($allRows as $row) {
            $key = "{$row->producto_id}_{$row->almacen_id}";

            // Si el registro YA tiene stock_anterior y stock_actual guardados, usarlos
            // (esto ocurre cuando se registró correctamente al crear la venta)
            if ($row->stock_anterior !== null && $row->stock_actual !== null) {
                // Usar los valores guardados en la BD
                $stockAnterior = (float) $row->stock_anterior;
                $stockActual = (float) $row->stock_actual;

                // Actualizar el stock actual para la siguiente iteración
                $stockPorProductoAlmacen[$key] = $stockActual;
            } else {
                // Si NO tiene valores guardados, calcularlos (para registros antiguos)
                $stockAnterior = $stockPorProductoAlmacen[$key] ?? 0;

                $cantIngreso = (float) $row->entrada;
                $cantSalida = (float) $row->salida;

                $stockActual = $stockAnterior + $cantIngreso - $cantSalida;

                // Actualizar el stock actual para la siguiente iteración
                $stockPorProductoAlmacen[$key] = $stockActual;
            }

            // Si cliente_nombre está vacío pero cliente_id existe, buscar el nombre
            if (empty($row->cliente_nombre) && !empty($row->cliente_id)) {
                $cliente = DB::table('clientes')->where('id', $row->cliente_id)->first();
                if ($cliente) {
                    $row->cliente_nombre = $cliente->razon_social ?? $cliente->nombre_comercial ?? 'Sin cliente';
                }
            }

            // Crear objeto con todos los campos
            $rowData = (array) $row;
            $rowData['stock_anterior'] = $stockAnterior;
            $rowData['cant_ingreso'] = (float) $row->entrada;
            $rowData['cant_salida'] = (float) $row->salida;
            $rowData['stock_actual'] = $stockActual;

            $rowsWithStockAll[] = (object) $rowData;
        }

        // Invertir el orden para mostrar los más recientes primero
        $rowsWithStockAll = array_reverse($rowsWithStockAll);

        // Ahora aplicar paginación a los resultados ya calculados
        if ($perPage == -1) {
            $rowsWithStock = $rowsWithStockAll;
        } else {
            $offset = ($page - 1) * $perPage;
            $rowsWithStock = array_slice($rowsWithStockAll, $offset, $perPage);
        }

        return response()->json([
            'data' => $rowsWithStock,
            'total' => $total,
            'current_page' => (int) ($perPage == -1 ? 1 : $page),
            'per_page' => $perPage,
            'last_page' => (int) ($perPage == -1 ? 1 : ceil($total / $perPage)),
        ]);
    }

    /**
     * Obtiene el nombre completo del cliente según su tipo
     */
    private function obtenerNombreCliente($cliente): string
    {
        if (!$cliente) {
            return 'Sin cliente';
        }

        // Obtener el valor del enum si es un objeto TipoCliente
        $tipo = $cliente->tipo_cliente instanceof \App\Enums\TipoCliente
            ? $cliente->tipo_cliente->value
            : $cliente->tipo_cliente;

        // Si es persona jurídica (empresa), usar razon_social o nombre_comercial
        if ($tipo === 'j' || $tipo === 'e') {
            return $cliente->razon_social ?? $cliente->nombre_comercial ?? 'Sin cliente';
        }

        // Si es persona natural, usar nombres y apellidos
        if ($tipo === 'p') {
            $nombres = trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? ''));
            return $nombres ?: 'Sin cliente';
        }

        return 'Sin cliente';
    }
}
