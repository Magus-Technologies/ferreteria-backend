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
        if ($factor <= 0) {
            $factor = 1;
        }

        // 3. Obtener el stock actual real en fracción (o usar el override si se proporciona)
        $stockAnteriorFraccion = 0;
        if (isset($data['stock_anterior_override'])) {
            // Usar el stock anterior proporcionado explícitamente
            $stockAnteriorFraccion = (float) $data['stock_anterior_override'];
            unset($data['stock_anterior_override']); // Remover para no guardarlo en la BD
        } elseif ($productoAlmacen) {
            $stockAnteriorFraccion = (float) $productoAlmacen->stock_fraccion;
        } else {
            $stockAnteriorFraccion = 0;
            \Log::warning('Kardex registrar - No se encontró ProductoAlmacen:', $data);
        }
        
        if ($productoAlmacen) {
            $data['producto_almacen_id'] = $productoAlmacen->id;
            // Asegurar que producto_id y almacen_id coincidan con el registro encontrado
            $data['producto_id'] = $productoAlmacen->producto_id;
            $data['almacen_id'] = $productoAlmacen->almacen_id;
        }
        
        // 4. Registrar el usuario que realiza el movimiento
        if (!isset($data['usuario_id'])) {
            $data['usuario_id'] = auth()->id();
        }
        
        // 5. Calcular stock en fracción después de esta transacción
        $cantIngreso = (float) ($data['entrada'] ?? 0);
        $cantSalida = (float) ($data['salida'] ?? 0);
        $stockActualFraccion = $stockAnteriorFraccion + $cantIngreso - $cantSalida;
        
        // 6. Convertir stocks de fracción a unidad derivada
        // Dividir el stock en fracción por el factor para obtener el stock en la unidad derivada
        $stockAnteriorUnidadDerivada = $factor > 0 ? ($stockAnteriorFraccion / $factor) : 0;
        $stockActualUnidadDerivada = $factor > 0 ? ($stockActualFraccion / $factor) : 0;
        
        // 7. Agregar los valores calculados a los datos (en unidad derivada)
        $data['stock_anterior'] = $stockAnteriorUnidadDerivada;
        $data['cant_ingreso'] = $cantIngreso;
        $data['cant_salida'] = $cantSalida;
        $data['stock_actual'] = $stockActualUnidadDerivada;
        
        \Log::info('Kardex registrar - datos a guardar:', [
            'producto_id' => $data['producto_id'],
            'almacen_id' => $data['almacen_id'],
            'unidad' => $data['unidad'] ?? 'N/A',
            'factor' => $factor,
            'stock_anterior_fraccion' => $stockAnteriorFraccion,
            'stock_anterior_unidad_derivada' => $stockAnteriorUnidadDerivada,
            'cant_ingreso' => $data['cant_ingreso'],
            'cant_salida' => $data['cant_salida'],
            'stock_actual_fraccion' => $stockActualFraccion,
            'stock_actual_unidad_derivada' => $stockActualUnidadDerivada,
        ]);
        
        $resultado = KardexInventario::create($data);
        
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
            'tipo' => 'ingreso',
            'movimiento' => 'CUADRE INGRESO',
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
            'tipo' => 'salida',
            'movimiento' => 'CUADRE SALIDA',
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
