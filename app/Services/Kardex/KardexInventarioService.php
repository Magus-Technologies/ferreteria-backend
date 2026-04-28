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
        // Obtener el stock actual del producto_almacen en la BD
        // Este es el stock REAL en ese momento
        $productoAlmacen = \App\Models\ProductoAlmacen::where('producto_id', $data['producto_id'])
            ->where('almacen_id', $data['almacen_id'])
            ->first();
        
        if ($productoAlmacen) {
            $stockAnterior = (float) $productoAlmacen->stock_fraccion;
        } else {
            $stockAnterior = 0;
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
        
        \Log::info('Kardex registrar - datos a guardar:', [
            'producto_id' => $data['producto_id'],
            'almacen_id' => $data['almacen_id'],
            'stock_anterior' => $data['stock_anterior'],
            'cant_ingreso' => $data['cant_ingreso'],
            'cant_salida' => $data['cant_salida'],
            'stock_actual' => $data['stock_actual'],
            'entrada' => $data['entrada'] ?? 0,
            'salida' => $data['salida'] ?? 0,
        ]);
        
        $resultado = KardexInventario::create($data);
        
        \Log::info('Kardex registrado - resultado:', [
            'id' => $resultado->id,
            'stock_anterior' => $resultado->stock_anterior,
            'stock_actual' => $resultado->stock_actual,
        ]);
        
        return $resultado;
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
        
        $tipoDocumento = match($tipoDocumentoValue) {
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
        
        $tipoDocumento = match($tipoDocumentoValue) {
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
            'almacen_id' => $compra->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una recepción en kardex inventario
     */
    public function registrarRecepcion($recepcion, $productoAlmacen, $unidad, $costo, $orden = 2)
    {
        return $this->registrar([
            'tipo' => 'recepcion',
            'movimiento' => 'ENTRADA',
            'fecha' => $recepcion->fecha,
            'documento' => "Recepcion REC-{$recepcion->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $recepcion->id,
            'producto_id' => $productoAlmacen->producto_id,
            'producto_nombre' => $productoAlmacen->producto->name,
            'producto_codigo' => $productoAlmacen->producto->cod_producto,
            'almacen_id' => $productoAlmacen->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra una anulación de recepción en kardex inventario
     */
    public function registrarAnulacionRecepcion($recepcion, $productoAlmacen, $unidad, $costo, $orden = 5)
    {
        return $this->registrar([
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
            'almacen_id' => $productoAlmacen->almacen_id,
            'orden' => $orden,
        ]);
    }

    /**
     * Registra un ingreso en kardex inventario
     */
    public function registrarIngreso($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 3)
    {
        return $this->registrar([
            'tipo' => 'ingreso',
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
        ]);
    }

    /**
     * Registra una salida en kardex inventario
     */
    public function registrarSalida($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 4)
    {
        return $this->registrar([
            'tipo' => 'salida',
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
        ]);
    }

    /**
     * Registra una anulación de ingreso en kardex inventario
     */
    public function registrarAnulacionIngreso($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 6)
    {
        return $this->registrar([
            'tipo' => 'ingreso_anulado',
            'movimiento' => 'ANULACION',
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
        ]);
    }

    /**
     * Registra una anulación de salida en kardex inventario
     */
    public function registrarAnulacionSalida($ingresoSalida, $productoAlmacen, $unidad, $costo, $orden = 7)
    {
        return $this->registrar([
            'tipo' => 'salida_anulada',
            'movimiento' => 'ANULACION',
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
        ]);
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
        
        $tipoDocumento = match($tipoDocumentoValue) {
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
        $query = DB::table('kardex_inventarios');

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

        // Obtener TODAS las filas ordenadas
        $allRows = $query->orderBy('fecha', 'asc')->orderBy('orden', 'asc')->get();

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
