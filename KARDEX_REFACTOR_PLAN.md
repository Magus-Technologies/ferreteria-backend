# Plan de Refactorización del Sistema Kardex

## Objetivo
Migrar el sistema kardex de un modelo de **lectura con lógica compleja (UNION ALL)** a un modelo de **registro de datos en tablas + lectura simple**.

## Cambio Fundamental

### Antes (Actual - INCORRECTO)
- Kardex era un **VIEW/REPORT** que leía de múltiples tablas de transacciones
- Usaba UNION ALL para combinar VENTAS, COMPRAS, RECEPCIONES, INGRESOS, SALIDAS
- Toda la lógica de construcción de filas estaba en `kardexController.php`
- Las tablas `kardex_inventarios` y `kardex_facturacions` estaban **vacías**

### Después (Nuevo - CORRECTO)
- Kardex es un **REGISTRO DE DATOS** en tablas de la BD
- Cuando ocurre un evento (crear venta, cambiar estado compra, etc.), se **REGISTRA** la fila en la tabla
- Los services solo **LEEN** de las tablas sin lógica compleja
- Las tablas `kardex_inventarios` y `kardex_facturacions` contienen **TODOS LOS DATOS**

---

## Estructura de Datos a Registrar

### Tabla: `kardex_facturacions`
Campos que se guardan cuando ocurren eventos de facturación:

```
- id (UUID)
- tipo (venta, cotizacion, prestamo, guia)
- movimiento (VENTA, DEVOLUCIÓN, REFERENCIA, SALIDA)
- fecha (datetime del evento)
- documento (string: "Factura 001-0001")
- unidad (string: "UNIDAD", "CAJA", etc.)
- cantidad (decimal: cantidad en unidad base)
- cantidad_fraccion (decimal: cantidad * factor)
- precio (decimal: precio unitario)
- costo (decimal: costo unitario)
- entrada (decimal: cantidad que entra - 0 para salidas)
- salida (decimal: cantidad que sale - 0 para entradas)
- referencia_id (ID del documento: venta_id, cotizacion_id, etc.)
- producto_id (FK)
- producto_nombre (string)
- producto_codigo (string)
- almacen_id (FK)
- orden (int: para ordenar múltiples líneas del mismo documento)
- created_at, updated_at
```

### Tabla: `kardex_inventarios`
Campos que se guardan cuando ocurren eventos de inventario:

```
- id (UUID)
- tipo (compra, recepcion, ingreso, salida, recepcion_anulada)
- movimiento (COMPRA, ENTRADA, SALIDA, ANULACION, REFERENCIA)
- fecha (datetime del evento)
- documento (string: "Compra Factura 001-0001" o "Recepcion REC-001")
- unidad (string: "UNIDAD", "CAJA", etc.)
- cantidad (decimal: cantidad en unidad base)
- cantidad_fraccion (decimal: cantidad * factor)
- precio (decimal: precio unitario)
- costo (decimal: costo unitario)
- entrada (decimal: cantidad que entra - 0 para salidas)
- salida (decimal: cantidad que sale - 0 para entradas)
- referencia_id (ID del documento: compra_id, recepcion_id, etc.)
- producto_id (FK)
- producto_nombre (string)
- producto_codigo (string)
- almacen_id (FK)
- orden (int: para ordenar múltiples líneas del mismo documento)
- created_at, updated_at
```

---

## Eventos que Disparan Registros

### KARDEX FACTURACIÓN (kardex_facturacions)

#### 1. VENTA CREADA (VentaController::store)
**Condición:** `estado_de_venta != 'ee'` (NO en espera)

Para cada producto en la venta:
```
tipo: 'venta'
movimiento: 'VENTA'
fecha: venta.fecha
documento: "Factura 001-0001" (o Boleta, Nota de Venta)
entrada: 0
salida: cantidad_fraccion
referencia_id: venta.id
orden: 1
```

#### 2. VENTA ANULADA (VentaController::update estado a 'an')
**Condición:** `estado_de_venta = 'an'`

Para cada producto en la venta anulada:
```
tipo: 'venta'
movimiento: 'DEVOLUCIÓN'
fecha: now()
documento: "Anulación Factura 001-0001"
entrada: cantidad_fraccion
salida: 0
referencia_id: venta.id
orden: 2
```

**Nota:** Se registra SOLO cuando la venta cambia a estado 'an', no al crear

#### 3. VENTA EN ESPERA → CREADA (VentaController::update estado 'ee' → 'cr')
**Condición:** Cambio de estado de 'ee' a 'cr'

Para cada producto:
```
tipo: 'venta'
movimiento: 'VENTA'
fecha: venta.fecha
documento: "Factura 001-0001"
entrada: 0
salida: cantidad_fraccion
referencia_id: venta.id
orden: 1
```

**Nota:** Se registra cuando la venta pasa de borrador a creada

#### 4. COTIZACIÓN (REFERENCIA - no afecta stock)
**Condición:** `estado_cotizacion != 'ca'` (NO cancelada)

Para cada producto:
```
tipo: 'cotizacion'
movimiento: 'REFERENCIA'
fecha: cotizacion.fecha
documento: "Cotizacion 001 (Pendiente/Convertida/Vencida)"
entrada: 0
salida: 0
referencia_id: cotizacion.id
orden: 2
```

**Nota:** Solo referencia, no afecta stock

#### 5. GUÍA DE REMISIÓN (SALIDA)
**Condición:** `afecta_stock = 1 AND estado != 'ANULADA'`

Para cada producto:
```
tipo: 'guia'
movimiento: 'SALIDA'
fecha: guia.fecha_emision
documento: "Guia 001-0001"
entrada: 0
salida: cantidad_fraccion
referencia_id: guia.id
orden: 4
```

#### 6. PRÉSTAMO (SALIDA)
**Condición:** `tipo_operacion = 'PRESTAR'`

Para cada producto:
```
tipo: 'prestamo'
movimiento: 'SALIDA'
fecha: prestamo.fecha
documento: "Prestamo PREST-001"
entrada: 0
salida: cantidad_fraccion
referencia_id: prestamo.id
orden: 3
```

---

### KARDEX INVENTARIO (kardex_inventarios)

#### 1. COMPRA CREADA (CompraController::store)
**Condición:** `estado_de_compra = 'cr'` (Creada)

Para cada producto:
```
tipo: 'compra'
movimiento: 'REFERENCIA'
fecha: compra.fecha
documento: "Compra Factura 001-0001 (Creada)"
entrada: 0
salida: 0
referencia_id: compra.id
orden: 0
saldo_anterior: saldo_actual (no cambia)
saldo: saldo_actual (no cambia)
```

**Nota:** Solo referencia, NO afecta stock, NO cambia saldo

#### 2. COMPRA PROCESADA (CompraController::update estado 'cr' → 'pr')
**Condición:** Cambio de estado a `estado_de_compra = 'pr'` (Procesada)

Para cada producto:
```
tipo: 'compra'
movimiento: 'COMPRA'
fecha: compra.fecha
documento: "Compra Factura 001-0001 (Procesada)"
entrada: cantidad_fraccion
salida: 0
referencia_id: compra.id
orden: 1
saldo_anterior: saldo_actual
saldo: saldo_actual + entrada
```

**Nota:** Se registra SOLO cuando pasa a 'pr', no al crear. Afecta stock y saldo.

#### 3. RECEPCIÓN CREADA (RecepcionAlmacenController::store)
**Condición:** `estado = 1` (Activa)

Para cada producto en la recepción:
```
tipo: 'recepcion'
movimiento: 'ENTRADA'
fecha: recepcion.fecha
documento: "Recepcion REC-001"
entrada: cantidad_fraccion
salida: 0
referencia_id: recepcion.id
orden: 2
```

#### 4. RECEPCIÓN ANULADA (RecepcionAlmacenController::destroy)
**Condición:** `estado = 0 AND anulada = 1`

Para cada producto en la recepción anulada:
```
tipo: 'recepcion_anulada'
movimiento: 'ANULACION'
fecha: now()
documento: "Recepcion REC-001 (Anulada)"
entrada: 0
salida: cantidad_fraccion
referencia_id: recepcion.id
orden: 5
```

**Nota:** Se registra cuando se anula, no al crear

#### 5. INGRESO CREADO (IngresoSalidaController::store)
**Condición:** `tipo_documento = 'in' AND estado = 1`

Para cada producto en el ingreso:
```
tipo: 'ingreso'
movimiento: 'ENTRADA'
fecha: ingreso.fecha
documento: "Ingreso ING-001-0001"
entrada: cantidad_fraccion
salida: 0
referencia_id: ingreso.id
orden: 3
```

#### 6. SALIDA CREADA (IngresoSalidaController::store)
**Condición:** `tipo_documento = 'sa' AND estado = 1`

Para cada producto en la salida:
```
tipo: 'salida'
movimiento: 'SALIDA'
fecha: salida.fecha
documento: "Salida SAL-001-0001"
entrada: 0
salida: cantidad_fraccion
referencia_id: salida.id
orden: 4
```

#### 7. INGRESO ANULADO (IngresoSalidaController::destroy)
**Condición:** `tipo_documento = 'in' AND estado = 0`

Para cada producto:
```
tipo: 'ingreso_anulado'
movimiento: 'ANULACION'
fecha: now()
documento: "Ingreso ING-001-0001 (Anulado)"
entrada: 0
salida: cantidad_fraccion
referencia_id: ingreso.id
orden: 6
```

#### 8. SALIDA ANULADA (IngresoSalidaController::destroy)
**Condición:** `tipo_documento = 'sa' AND estado = 0`

Para cada producto:
```
tipo: 'salida_anulada'
movimiento: 'ANULACION'
fecha: now()
documento: "Salida SAL-001-0001 (Anulada)"
entrada: cantidad_fraccion
salida: 0
referencia_id: salida.id
orden: 7
```

---

## Cambios en Services

### KardexFacturacionService
```php
// ✅ MANTENER - Solo lectura simple
public function getPaginated(
    ?int $productoId,
    ?int $almacenId,
    ?string $desde,
    ?string $hasta,
    ?string $tipo,
    int $perPage = 50,
    int $page = 1
)
// Lee de kardex_facturacions SIN lógica compleja
// Solo filtra por producto_id, almacen_id, fecha, tipo
// Ordena por fecha ASC, orden ASC
// Retorna datos tal como están en la tabla

// ✅ MANTENER - Solo registro
public function registrar(array $data)
// Guarda una fila en kardex_facturacions
// Datos: tipo, movimiento, fecha, documento, unidad, cantidad, 
//        cantidad_fraccion, precio, costo, entrada, salida, 
//        referencia_id, producto_id, producto_nombre, producto_codigo, 
//        almacen_id, orden
```

### KardexInventarioService
```php
// ✅ MANTENER - Solo lectura simple
public function getPaginated(
    ?int $productoId,
    ?int $almacenId,
    ?string $desde,
    ?string $hasta,
    ?string $tipo,
    int $perPage = 50,
    int $page = 1
)
// Lee de kardex_inventarios SIN lógica compleja
// Solo filtra por producto_id, almacen_id, fecha, tipo
// Ordena por fecha ASC, orden ASC
// Retorna datos tal como están en la tabla

// ✅ MANTENER - Solo registro
public function registrar(array $data)
// Guarda una fila en kardex_inventarios
// Datos: tipo, movimiento, fecha, documento, unidad, cantidad, 
//        cantidad_fraccion, precio, costo, entrada, salida, 
//        referencia_id, producto_id, producto_nombre, producto_codigo, 
//        almacen_id, orden
```

---

## Cambios en Controllers - Registro

### VentaController::store
**Agregar al final (después de crear la venta):**

Solo si `estado_de_venta != 'ee'`:
```php
if ($estadoVentaStr !== 'ee') {
    foreach ($validated['productos_por_almacen'] ?? [] as $producto) {
        $productoAlmacen = ProductoAlmacen::find($producto['producto_almacen_id'] ?? null);
        if (!$productoAlmacen && isset($producto['producto_id'])) {
            $productoAlmacen = ProductoAlmacen::where('producto_id', $producto['producto_id'])
                ->where('almacen_id', $validated['almacen_id'])->first();
        }
        if (!$productoAlmacen) continue;

        foreach ($producto['unidades_derivadas'] as $unidad) {
            $this->kardexFacturacionService->registrar([
                'tipo' => 'venta',
                'movimiento' => 'VENTA',
                'fecha' => $venta->fecha,
                'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
                'unidad' => $unidad['unidad_derivada_inmutable_name'],
                'cantidad' => $unidad['cantidad'],
                'cantidad_fraccion' => $unidad['cantidad'] * $unidad['factor'],
                'precio' => $unidad['precio'],
                'costo' => $producto['costo'],
                'entrada' => 0,
                'salida' => $unidad['cantidad'] * $unidad['factor'],
                'referencia_id' => $venta->id,
                'producto_id' => $productoAlmacen->producto_id,
                'producto_nombre' => $productoAlmacen->producto->name,
                'producto_codigo' => $productoAlmacen->producto->cod_producto,
                'almacen_id' => $venta->almacen_id,
                'orden' => 1,
            ]);
        }
    }
}
```

### VentaController::update (cambio de estado)
**Agregar cuando estado cambia a 'an' (anulada):**
```php
if ($oldEstado !== EstadoDeVenta::Anulado && $newEstado === EstadoDeVenta::Anulado) {
    foreach ($venta->productosPorAlmacen as $pav) {
        foreach ($pav->unidadesDerivadas as $unidad) {
            $this->kardexFacturacionService->registrar([
                'tipo' => 'venta',
                'movimiento' => 'DEVOLUCIÓN',
                'fecha' => now(),
                'documento' => "Anulación {$tipoDocumento} {$venta->serie}-{$venta->numero}",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => $unidad->precio,
                'costo' => $pav->costo,
                'entrada' => $unidad->cantidad * $unidad->factor,
                'salida' => 0,
                'referencia_id' => $venta->id,
                'producto_id' => $pav->productoAlmacen->producto_id,
                'producto_nombre' => $pav->productoAlmacen->producto->name,
                'producto_codigo' => $pav->productoAlmacen->producto->cod_producto,
                'almacen_id' => $venta->almacen_id,
                'orden' => 2,
            ]);
        }
    }
}

// Cambio de 'ee' (en espera) a 'cr' (creada)
if ($oldEstado === EstadoDeVenta::EnEspera && $newEstado !== EstadoDeVenta::EnEspera) {
    foreach ($venta->productosPorAlmacen as $pav) {
        foreach ($pav->unidadesDerivadas as $unidad) {
            $this->kardexFacturacionService->registrar([
                'tipo' => 'venta',
                'movimiento' => 'VENTA',
                'fecha' => $venta->fecha,
                'documento' => "{$tipoDocumento} {$venta->serie}-{$venta->numero}",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => $unidad->precio,
                'costo' => $pav->costo,
                'entrada' => 0,
                'salida' => $unidad->cantidad * $unidad->factor,
                'referencia_id' => $venta->id,
                'producto_id' => $pav->productoAlmacen->producto_id,
                'producto_nombre' => $pav->productoAlmacen->producto->name,
                'producto_codigo' => $pav->productoAlmacen->producto->cod_producto,
                'almacen_id' => $venta->almacen_id,
                'orden' => 1,
            ]);
        }
    }
}
```

### CompraController::store
**Agregar al final (solo referencia, no afecta stock):**
```php
foreach ($compra->productosPorAlmacen as $pac) {
    foreach ($pac->unidadesDerivadas as $unidad) {
        $this->kardexInventarioService->registrar([
            'tipo' => 'compra',
            'movimiento' => 'REFERENCIA',
            'fecha' => $compra->fecha,
            'documento' => "Compra {$compra->tipo_documento} {$compra->serie}-{$compra->numero} (Creada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $pac->costo,
            'entrada' => 0,
            'salida' => 0,
            'referencia_id' => $compra->id,
            'producto_id' => $pac->productoAlmacen->producto_id,
            'producto_nombre' => $pac->productoAlmacen->producto->name,
            'producto_codigo' => $pac->productoAlmacen->producto->cod_producto,
            'almacen_id' => $compra->almacen_id,
            'orden' => 0,
        ]);
    }
}
```

### CompraController::update (cambio de estado)
**Agregar cuando estado cambia a 'pr' (procesada):**
```php
if ($oldEstado !== EstadoDeCompra::Procesado && $newEstado === EstadoDeCompra::Procesado) {
    foreach ($compra->productosPorAlmacen as $pac) {
        foreach ($pac->unidadesDerivadas as $unidad) {
            $this->kardexInventarioService->registrar([
                'tipo' => 'compra',
                'movimiento' => 'COMPRA',
                'fecha' => $compra->fecha,
                'documento' => "Compra {$compra->tipo_documento} {$compra->serie}-{$compra->numero} (Procesada)",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => 0,
                'costo' => $pac->costo,
                'entrada' => $unidad->cantidad * $unidad->factor,
                'salida' => 0,
                'referencia_id' => $compra->id,
                'producto_id' => $pac->productoAlmacen->producto_id,
                'producto_nombre' => $pac->productoAlmacen->producto->name,
                'producto_codigo' => $pac->productoAlmacen->producto->cod_producto,
                'almacen_id' => $compra->almacen_id,
                'orden' => 1,
            ]);
        }
    }
}
```

### RecepcionAlmacenController::store
**Agregar al final (después de crear la recepción):**
```php
foreach ($recepcion->productosPorAlmacen as $par) {
    foreach ($par->unidadesDerivadas as $unidad) {
        $this->kardexInventarioService->registrar([
            'tipo' => 'recepcion',
            'movimiento' => 'ENTRADA',
            'fecha' => $recepcion->fecha,
            'documento' => "Recepcion REC-{$recepcion->numero}",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $par->costo,
            'entrada' => $unidad->cantidad * $unidad->factor,
            'salida' => 0,
            'referencia_id' => $recepcion->id,
            'producto_id' => $par->productoAlmacen->producto_id,
            'producto_nombre' => $par->productoAlmacen->producto->name,
            'producto_codigo' => $par->productoAlmacen->producto->cod_producto,
            'almacen_id' => $par->productoAlmacen->almacen_id,
            'orden' => 2,
        ]);
    }
}
```

### RecepcionAlmacenController::destroy (cuando se anula)
**Agregar cuando se anula la recepción:**
```php
foreach ($recepcion->productosPorAlmacen as $par) {
    foreach ($par->unidadesDerivadas as $unidad) {
        $this->kardexInventarioService->registrar([
            'tipo' => 'recepcion_anulada',
            'movimiento' => 'ANULACION',
            'fecha' => now(),
            'documento' => "Recepcion REC-{$recepcion->numero} (Anulada)",
            'unidad' => $unidad->unidadDerivadaInmutable->name,
            'cantidad' => $unidad->cantidad,
            'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
            'precio' => 0,
            'costo' => $par->costo,
            'entrada' => 0,
            'salida' => $unidad->cantidad * $unidad->factor,
            'referencia_id' => $recepcion->id,
            'producto_id' => $par->productoAlmacen->producto_id,
            'producto_nombre' => $par->productoAlmacen->producto->name,
            'producto_codigo' => $par->productoAlmacen->producto->cod_producto,
            'almacen_id' => $par->productoAlmacen->almacen_id,
            'orden' => 5,
        ]);
    }
}
```

### IngresoSalidaController::store
**Agregar al final (después de crear ingreso o salida):**

Para INGRESOS (tipo_documento='in'):
```php
if ($ingresoSalida->tipo_documento === 'in') {
    foreach ($ingresoSalida->productosPorAlmacen as $pais) {
        foreach ($pais->unidadesDerivadas as $unidad) {
            $this->kardexInventarioService->registrar([
                'tipo' => 'ingreso',
                'movimiento' => 'ENTRADA',
                'fecha' => $ingresoSalida->fecha,
                'documento' => "Ingreso ING-{$ingresoSalida->serie}-{$ingresoSalida->numero}",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => 0,
                'costo' => $pais->costo,
                'entrada' => $unidad->cantidad * $unidad->factor,
                'salida' => 0,
                'referencia_id' => $ingresoSalida->id,
                'producto_id' => $pais->productoAlmacen->producto_id,
                'producto_nombre' => $pais->productoAlmacen->producto->name,
                'producto_codigo' => $pais->productoAlmacen->producto->cod_producto,
                'almacen_id' => $ingresoSalida->almacen_id,
                'orden' => 3,
            ]);
        }
    }
}
```

Para SALIDAS (tipo_documento='sa'):
```php
if ($ingresoSalida->tipo_documento === 'sa') {
    foreach ($ingresoSalida->productosPorAlmacen as $pais) {
        foreach ($pais->unidadesDerivadas as $unidad) {
            $this->kardexInventarioService->registrar([
                'tipo' => 'salida',
                'movimiento' => 'SALIDA',
                'fecha' => $ingresoSalida->fecha,
                'documento' => "Salida SAL-{$ingresoSalida->serie}-{$ingresoSalida->numero}",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => 0,
                'costo' => $pais->costo,
                'entrada' => 0,
                'salida' => $unidad->cantidad * $unidad->factor,
                'referencia_id' => $ingresoSalida->id,
                'producto_id' => $pais->productoAlmacen->producto_id,
                'producto_nombre' => $pais->productoAlmacen->producto->name,
                'producto_codigo' => $pais->productoAlmacen->producto->cod_producto,
                'almacen_id' => $ingresoSalida->almacen_id,
                'orden' => 4,
            ]);
        }
    }
}
```

### IngresoSalidaController::destroy (cuando se anula)
**Agregar cuando se anula ingreso o salida:**

Para INGRESOS anulados:
```php
if ($ingresoSalida->tipo_documento === 'in') {
    foreach ($ingresoSalida->productosPorAlmacen as $pais) {
        foreach ($pais->unidadesDerivadas as $unidad) {
            $this->kardexInventarioService->registrar([
                'tipo' => 'ingreso_anulado',
                'movimiento' => 'ANULACION',
                'fecha' => now(),
                'documento' => "Ingreso ING-{$ingresoSalida->serie}-{$ingresoSalida->numero} (Anulado)",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => 0,
                'costo' => $pais->costo,
                'entrada' => 0,
                'salida' => $unidad->cantidad * $unidad->factor,
                'referencia_id' => $ingresoSalida->id,
                'producto_id' => $pais->productoAlmacen->producto_id,
                'producto_nombre' => $pais->productoAlmacen->producto->name,
                'producto_codigo' => $pais->productoAlmacen->producto->cod_producto,
                'almacen_id' => $ingresoSalida->almacen_id,
                'orden' => 6,
            ]);
        }
    }
}
```

Para SALIDAS anuladas:
```php
if ($ingresoSalida->tipo_documento === 'sa') {
    foreach ($ingresoSalida->productosPorAlmacen as $pais) {
        foreach ($pais->unidadesDerivadas as $unidad) {
            $this->kardexInventarioService->registrar([
                'tipo' => 'salida_anulada',
                'movimiento' => 'ANULACION',
                'fecha' => now(),
                'documento' => "Salida SAL-{$ingresoSalida->serie}-{$ingresoSalida->numero} (Anulada)",
                'unidad' => $unidad->unidadDerivadaInmutable->name,
                'cantidad' => $unidad->cantidad,
                'cantidad_fraccion' => $unidad->cantidad * $unidad->factor,
                'precio' => 0,
                'costo' => $pais->costo,
                'entrada' => $unidad->cantidad * $unidad->factor,
                'salida' => 0,
                'referencia_id' => $ingresoSalida->id,
                'producto_id' => $pais->productoAlmacen->producto_id,
                'producto_nombre' => $pais->productoAlmacen->producto->name,
                'producto_codigo' => $pais->productoAlmacen->producto->cod_producto,
                'almacen_id' => $ingresoSalida->almacen_id,
                'orden' => 7,
            ]);
        }
    }
}
```

---

## Cambios en Controllers - Lectura

### KardexFacturacionController::index
```php
public function index(Request $request)
{
    $validated = $request->validate([
        'producto_id' => 'nullable|integer',
        'almacen_id' => 'nullable|integer',
        'desde' => 'nullable|date',
        'hasta' => 'nullable|date',
        'tipo' => 'nullable|string',
        'per_page' => 'nullable|integer|min:-1|max:200',
        'page' => 'nullable|integer|min:1',
    ]);

    return $this->kardexFacturacionService->getPaginated(
        $validated['producto_id'] ?? null,
        $validated['almacen_id'] ?? null,
        $validated['desde'] ?? null,
        $validated['hasta'] ?? null,
        $validated['tipo'] ?? null,
        $validated['per_page'] ?? 50,
        $validated['page'] ?? 1
    );
}
```

### KardexInventarioController::index
```php
public function index(Request $request)
{
    $validated = $request->validate([
        'producto_id' => 'nullable|integer',
        'almacen_id' => 'nullable|integer',
        'desde' => 'nullable|date',
        'hasta' => 'nullable|date',
        'tipo' => 'nullable|string',
        'per_page' => 'nullable|integer|min:-1|max:200',
        'page' => 'nullable|integer|min:1',
    ]);

    return $this->kardexInventarioService->getPaginated(
        $validated['producto_id'] ?? null,
        $validated['almacen_id'] ?? null,
        $validated['desde'] ?? null,
        $validated['hasta'] ?? null,
        $validated['tipo'] ?? null,
        $validated['per_page'] ?? 50,
        $validated['page'] ?? 1
    );
}
```

---

## Resumen de Cambios

| Componente | Antes | Después |
|-----------|-------|---------|
| **Kardex** | VIEW/REPORT con UNION ALL | TABLA con datos registrados |
| **Services** | Tenían `getPaginated()` con lógica compleja | Solo `registrar()` y `getPaginated()` simple |
| **Controllers** | Leían de múltiples tablas | Llaman a `registrar()` cuando ocurren eventos |
| **Tablas** | Vacías | Llenas con datos de eventos |
| **Lectura** | Lógica compleja en SQL | Lectura simple de tabla |

---

## Ventajas del Nuevo Modelo

✅ **Separación de responsabilidades**: Registro vs Lectura  
✅ **Mejor rendimiento**: Lectura simple sin UNION ALL  
✅ **Datos auditables**: Cada evento queda registrado  
✅ **Fácil de mantener**: Lógica clara en controllers  
✅ **Escalable**: Agregar nuevos eventos es simple  
✅ **Consistencia**: Datos siempre correctos en la tabla  
