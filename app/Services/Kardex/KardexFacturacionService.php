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
        // Obtener el saldo actual del producto_almacen en la BD
        // Este es el saldo REAL en ese momento
        $productoAlmacen = DB::table('productoalmacen')
            ->where('producto_id', $data['producto_id'])
            ->where('almacen_id', $data['almacen_id'])
            ->first();
        
        if ($productoAlmacen) {
            $saldoAnterior = (float) $productoAlmacen->stock_fraccion;
        } else {
            $saldoAnterior = 0;
        }
        
        // Calcular saldo actual después de esta transacción
        $cantIngreso = (float) ($data['entrada'] ?? 0);
        $cantSalida = (float) ($data['salida'] ?? 0);
        $saldoActual = $saldoAnterior + $cantIngreso - $cantSalida;
        
        // Agregar los valores calculados a los datos
        $data['stock_anterior'] = $saldoAnterior;
        $data['cant_ingreso'] = $cantIngreso;
        $data['cant_salida'] = $cantSalida;
        $data['stock_actual'] = $saldoActual;
        
        return KardexFacturacion::create($data);
    }

    /**
     * Registra una venta en kardex facturación (cuando se crea)
     * Solo se registra si estado_de_venta != 'ee' (no en espera)
     */
    public function registrarVenta($venta, $productoAlmacen, $unidad, $costo, $orden = 1, $stockAnterior = null)
    {
        $tipoDocumento = match($venta->tipo_documento->value) {
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
            default => $venta->tipo_documento->value,
        };

        $cantSalida = $unidad['cantidad'] * $unidad['factor'];

        $data = [
            'tipo' => 'venta',
            'movimiento' => 'VENTA',
            'fecha' => $venta->fecha,
            'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
            'unidad' => $unidad['unidad_derivada_inmutable_name'],
            'cantidad' => $unidad['cantidad'],
            'cantidad_fraccion' => $cantSalida,
            'precio' => $unidad['precio'],
            'costo' => $costo,
            'entrada' => 0,
            'salida' => $cantSalida,
            'referencia_id' => $venta->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $venta->almacen_id,
            'orden' => $orden,
        ];

        // Si se proporciona stock anterior, usarlo directamente
        if ($stockAnterior !== null) {
            $data['stock_anterior'] = $stockAnterior;
            $data['cant_ingreso'] = 0;
            $data['cant_salida'] = $cantSalida;
            $data['stock_actual'] = $stockAnterior - $cantSalida;
            
            return KardexFacturacion::create($data);
        }

        // Si no se proporciona, usar el método registrar que consulta la BD
        return $this->registrar($data);
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

    /**
     * Marca una venta como editada en kardex facturación
     * Actualiza el registro existente cambiando el movimiento a "VENTA EDITADA"
     */
    public function marcarVentaComoEditada($ventaId)
    {
        // Actualizar todos los registros de kardex de esta venta
        KardexFacturacion::where('referencia_id', $ventaId)
            ->where('tipo', 'venta')
            ->where('movimiento', 'VENTA')
            ->update([
                'movimiento' => 'VENTA EDITADA',
                'updated_at' => now(),
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
}
