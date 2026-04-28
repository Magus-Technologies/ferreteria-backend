<?php

namespace App\Services\Kardex;

use App\Models\KardexFacturacion;
use Illuminate\Support\Facades\DB;

class KardexFacturacionService
{
    /**
     * Registra un movimiento en kardex de facturación
     */
    public function registrar(array $data)
    {
        // Calcular stock_anterior basado en todas las filas anteriores del mismo producto-almacén
        // Obtener todas las filas anteriores del mismo producto-almacén ordenadas por fecha y orden
        $filasAnteriores = DB::table('kardex_facturacions')
            ->where('producto_id', $data['producto_id'])
            ->where('almacen_id', $data['almacen_id'])
            ->orderBy('fecha', 'asc')
            ->orderBy('orden', 'asc')
            ->get();
        
        // Calcular stock acumulado hasta ahora
        $stockAnterior = 0;
        foreach ($filasAnteriores as $fila) {
            $stockAnterior += (float) $fila->entrada - (float) $fila->salida;
        }
        
        // Si no hay filas anteriores, obtener el stock inicial del producto_almacen
        if ($filasAnteriores->isEmpty()) {
            $productoAlmacen = DB::table('producto_almacen')
                ->where('producto_id', $data['producto_id'])
                ->where('almacen_id', $data['almacen_id'])
                ->first();
            
            if ($productoAlmacen) {
                $stockAnterior = (float) $productoAlmacen->stock_fraccion;
            }
        }
        
        // Calcular stock actual después de esta transacción
        $cantIngreso = (float) ($data['entrada'] ?? 0);
        $cantSalida = (float) ($data['salida'] ?? 0);
        $stockActual = $stockAnterior + $cantIngreso - $cantSalida;
        
        // Agregar los valores calculados a los datos
        $data['stock_anterior'] = $stockAnterior;
        $data['cant_ingreso'] = $cantIngreso;
        $data['cant_salida'] = $cantSalida;
        $data['stock_actual'] = $stockActual;
        
        return KardexFacturacion::create($data);
    }

    /**
     * Registra una venta en kardex facturación (cuando se crea)
     * Solo se registra si estado_de_venta != 'ee' (no en espera)
     */
    public function registrarVenta($venta, $productoAlmacen, $unidad, $costo, $orden = 1)
    {
        $tipoDocumento = match($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        return $this->registrar([
            'tipo' => 'venta',
            'movimiento' => 'VENTA',
            'fecha' => $venta->fecha,
            'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad['unidad_derivada_inmutable_name'],
            'cantidad' => $unidad['cantidad'],
            'cantidad_fraccion' => $unidad['cantidad'] * $unidad['factor'],
            'precio' => $unidad['precio'],
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad['cantidad'] * $unidad['factor'],
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una devolución de venta en kardex facturación (cuando se anula)
     * Se registra cuando estado_de_venta cambia a 'an' (anulada)
     */
    public function registrarDevolucionVenta($venta, $productoAlmacen, $unidad, $costo, $orden = 2)
    {
        $tipoDocumento = match($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        return $this->registrar([
            'tipo' => 'venta',
            'movimiento' => 'DEVOLUCIÓN',
            'fecha' => now(),
            'documento' => "Anulación {$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => $unidad->precio,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una venta cuando cambia de 'ee' (en espera) a otro estado
     * Se registra cuando la venta pasa de borrador a creada
     */
    public function registrarVentaDesdeEspera($venta, $productoAlmacen, $unidad, $costo, $orden = 1)
    {
        $tipoDocumento = match($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        return $this->registrar([
            'tipo' => 'venta',
            'movimiento' => 'VENTA',
            'fecha' => $venta->fecha,
            'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => $unidad->precio,
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $venta->almacen_id,
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
        $query = DB::table('kardex_facturacions');

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

        // Obtener TODAS las filas ordenadas para calcular stock acumulado correctamente
        $allRows = $query->orderBy('fecha', 'asc')->orderBy('orden', 'asc')->get();

        // Calcular stock acumulado para TODAS las filas
        $stockPorProductoAlmacen = []; // Rastrear stock actual por producto-almacén
        $rowsWithStockAll = [];

        foreach ($allRows as $row) {
            $key = "{$row->producto_id}_{$row->almacen_id}";
            
            // Obtener stock anterior para este producto-almacén
            $stockAnterior = $stockPorProductoAlmacen[$key] ?? 0;
            
            // Calcular cant_ingreso y cant_salida
            $cantIngreso = (float) $row->entrada;
            $cantSalida = (float) $row->salida;
            
            // Calcular stock actual
            $stockActual = $stockAnterior + $cantIngreso - $cantSalida;
            
            // Actualizar el stock actual para la siguiente iteración
            $stockPorProductoAlmacen[$key] = $stockActual;

            // Crear objeto con todos los campos, sobrescribiendo los valores calculados
            $rowData = (array) $row;
            $rowData['stock_anterior'] = $stockAnterior;
            $rowData['cant_ingreso'] = $cantIngreso;
            $rowData['cant_salida'] = $cantSalida;
            $rowData['stock_actual'] = $stockActual;
            
            $rowsWithStockAll[] = (object) $rowData;
        }

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
}
