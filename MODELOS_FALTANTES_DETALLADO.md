# Análisis Detallado de Modelos Faltantes en Laravel Backend

Análisis exhaustivo de la base de datos `ferreteria2` comparando Prisma vs Laravel Eloquent.

---

## 📊 Resumen Ejecutivo

| Métrica | Valor |
|---------|-------|
| **Tablas en BD** | 74 tablas |
| **Modelos Prisma** | 51 modelos |
| **Modelos Laravel** | 16 modelos |
| **Modelos Faltantes** | **35 modelos** |
| **Progreso** | 31.4% ✅ |

---

## ✅ Modelos YA Implementados en Laravel (16)

```
✓ Almacen              → almacen
✓ Categoria            → categoria
✓ Cliente              → cliente
✓ Compra               → compra
✓ Cotizacion           → cotizacion
✓ Empresa              → empresa
✓ Marca                → marca
✓ Permission           → permission
✓ Producto             → producto
✓ ProductoAlmacen      → productoalmacen
✓ Proveedor            → proveedor
✓ Role                 → role
✓ Ubicacion            → ubicacion
✓ UnidadMedida         → unidadmedida
✓ User                 → user
✓ Venta                → venta
```

---

## ❌ MODELOS FALTANTES - ANÁLISIS DETALLADO

---

## 🔴 PRIORIDAD ALTA - Funcionalidad Core (11 modelos)

### 1. IngresoSalida
**Tabla:** `ingresosalida`
**Modelo Prisma:** `IngresoSalida`

**Campos:**
```php
id                    int (PK, autoincrement)
fecha                 datetime
tipo_documento        enum (TipoDocumento)
serie                 int
numero                int
descripcion           string (nullable)
estado                boolean (default: true)
almacen_id            int (FK → almacen)
tipo_ingreso_id       int (FK → tipoingresosalida)
proveedor_id          int (FK → proveedor, nullable)
user_id               string (FK → user)
created_at            datetime
updated_at            datetime
```

**Relaciones:**
- `belongsTo`: Almacen, TipoIngresoSalida, Proveedor (nullable), User
- `hasMany`: ProductoAlmacenIngresoSalida

---

### 2. ProductoAlmacenIngresoSalida
**Tabla:** `productoalmaceningresosalida`
**Modelo Prisma:** `ProductoAlmacenIngresoSalida`

**Campos:**
```php
id                  int (PK, autoincrement)
ingreso_id          int (FK → ingresosalida)
costo               decimal(9,4)
producto_almacen_id int (FK → productoalmacen)
```

**Índices:**
- UNIQUE: [ingreso_id, producto_almacen_id]

**Relaciones:**
- `belongsTo`: IngresoSalida, ProductoAlmacen
- `hasMany`: UnidadDerivadaInmutableIngresoSalida

---

### 3. UnidadDerivadaInmutableIngresoSalida
**Tabla:** `unidadderivadainmutableingresosalida`
**Modelo Prisma:** `UnidadDerivadaInmutableIngresoSalida`

**Campos:**
```php
id                                 int (PK, autoincrement)
unidad_derivada_inmutable_id       int (FK → unidadderivadainmutable)
producto_almacen_ingreso_salida_id int (FK → productoalmaceningresosalida)
factor                             decimal(9,3)
cantidad                           decimal(9,3)
cantidad_restante                  decimal(9,3)
lote                               string (nullable)
vencimiento                        datetime (nullable)
```

**Relaciones:**
- `belongsTo`: ProductoAlmacenIngresoSalida, UnidadDerivadaInmutable
- `hasMany`: HistorialUnidadDerivadaInmutableIngresoSalida

---

### 4. HistorialUnidadDerivadaInmutableIngresoSalida
**Tabla:** `historialunidadderivadainmutableingresosalida`
**Modelo Prisma:** `HistorialUnidadDerivadaInmutableIngresoSalida`

**Campos:**
```php
id                                          int (PK, autoincrement)
unidad_derivada_inmutable_ingreso_salida_id int (FK)
stock_anterior                              decimal(9,3)
stock_nuevo                                 decimal(9,3)
```

**Relaciones:**
- `belongsTo`: UnidadDerivadaInmutableIngresoSalida

---

### 5. TipoIngresoSalida
**Tabla:** `tipoingresosalida`
**Modelo Prisma:** `TipoIngresoSalida`

**Campos:**
```php
id       int (PK, autoincrement)
name     string (unique)
estado   boolean (default: true)
```

**Relaciones:**
- `hasMany`: IngresoSalida

---

### 6. RecepcionAlmacen
**Tabla:** `recepcionalmacen`
**Modelo Prisma:** `RecepcionAlmacen`

**Campos:**
```php
id                          int (PK, autoincrement)
numero                      int
observaciones               string (nullable)
fecha                       datetime
transportista_razon_social  string (nullable)
transportista_ruc           string (nullable)
transportista_placa         string (nullable)
transportista_licencia      string (nullable)
transportista_dni           string (nullable)
transportista_name          string (nullable)
transportista_guia_remision string (nullable)
estado                      boolean (default: true)
user_id                     string (FK → user)
compra_id                   string (FK → compra)
created_at                  datetime
updated_at                  datetime
```

**Índices:**
- INDEX: [fecha]

**Relaciones:**
- `belongsTo`: Compra, User
- `hasMany`: ProductoAlmacenRecepcion

---

### 7. ProductoAlmacenRecepcion
**Tabla:** `productoalmacenrecepcion`
**Modelo Prisma:** `ProductoAlmacenRecepcion`

**Campos:**
```php
id                  int (PK, autoincrement)
recepcion_id        int (FK → recepcionalmacen)
costo               decimal(9,4)
producto_almacen_id int (FK → productoalmacen)
```

**Índices:**
- UNIQUE: [recepcion_id, producto_almacen_id]

**Relaciones:**
- `belongsTo`: RecepcionAlmacen, ProductoAlmacen
- `hasMany`: UnidadDerivadaInmutableRecepcion

---

### 8. UnidadDerivadaInmutableRecepcion
**Tabla:** `unidadderivadainmutablerecepcion`
**Modelo Prisma:** `UnidadDerivadaInmutableRecepcion`

**Campos:**
```php
id                            int (PK, autoincrement)
unidad_derivada_inmutable_id  int (FK → unidadderivadainmutable)
producto_almacen_recepcion_id int (FK → productoalmacenrecepcion)
factor                        decimal(9,3)
cantidad                      decimal(9,3)
cantidad_restante             decimal(9,3)
lote                          string (nullable)
vencimiento                   datetime (nullable)
flete                         decimal(9,4) (default: 0)
bonificacion                  boolean (default: false)
```

**Índices:**
- UNIQUE: [producto_almacen_recepcion_id, unidad_derivada_inmutable_id, bonificacion]

**Relaciones:**
- `belongsTo`: ProductoAlmacenRecepcion, UnidadDerivadaInmutable
- `hasMany`: HistorialUnidadDerivadaInmutableRecepcion

---

### 9. HistorialUnidadDerivadaInmutableRecepcion
**Tabla:** `historialunidadderivadainmutablerecepcion`
**Modelo Prisma:** `HistorialUnidadDerivadaInmutableRecepcion`

**Campos:**
```php
id                                     int (PK, autoincrement)
unidad_derivada_inmutable_recepcion_id int (FK)
stock_anterior                         decimal(9,3)
stock_nuevo                            decimal(9,3)
created_at                             datetime
```

**Índices:**
- INDEX: [created_at]

**Relaciones:**
- `belongsTo`: UnidadDerivadaInmutableRecepcion

---

### 10. EntregaProducto
**Tabla:** `entregaproducto`
**Modelo Prisma:** `EntregaProducto`

**Campos:**
```php
id                   int (PK, autoincrement)
venta_id             string (FK → venta)
tipo_entrega         enum TipoEntrega (default: 'Inmediata')
tipo_despacho        enum TipoDespacho (default: 'EnTienda')
estado_entrega       enum EstadoEntrega (default: 'Pendiente')
fecha_entrega        datetime
fecha_programada     datetime (nullable)
hora_inicio          string (nullable)
hora_fin             string (nullable)
direccion_entrega    string (nullable)
observaciones        string (nullable)
almacen_salida_id    int (FK → almacen)
chofer_id            string (FK → user, nullable)
user_id              string (FK → user)
created_at           datetime
updated_at           datetime
```

**Índices:**
- INDEX: [venta_id, fecha_entrega, estado_entrega]

**Enums:**
```php
enum TipoEntrega: string {
    case Inmediata = 'in';
    case Programada = 'pr';
}

enum TipoDespacho: string {
    case EnTienda = 'et';
    case Domicilio = 'do';
}

enum EstadoEntrega: string {
    case Pendiente = 'pe';
    case EnCamino = 'ec';
    case Entregado = 'en';
    case Cancelado = 'ca';
}
```

**Relaciones:**
- `belongsTo`: Venta, Almacen (as almacen_salida), User (as chofer, nullable), User (as cajero)
- `hasMany`: DetalleEntregaProducto

---

### 11. DetalleEntregaProducto
**Tabla:** `detalleentregaproducto`
**Modelo Prisma:** `DetalleEntregaProducto`

**Campos:**
```php
id                       int (PK, autoincrement)
entrega_producto_id      int (FK → entregaproducto)
unidad_derivada_venta_id int (FK → unidadderivadainmutableventa)
cantidad_entregada       decimal(9,3)
ubicacion                string (nullable)
```

**Índices:**
- UNIQUE: [entrega_producto_id, unidad_derivada_venta_id]

**Relaciones:**
- `belongsTo`: EntregaProducto, UnidadDerivadaInmutableVenta

---

## 🟡 PRIORIDAD ALTA - Sistema de Caja y Pagos (7 modelos)

### 12. SubCaja
**Tabla:** `subcaja`
**Modelo Prisma:** `SubCaja`

**Campos:**
```php
id   string (PK, cuid)
name string (unique)
```

**Relaciones:**
- `hasMany`: MetodoDePago

---

### 13. MetodoDePago
**Tabla:** `metododepago`
**Modelo Prisma:** `MetodoDePago`

**Campos:**
```php
id              string (PK, cuid)
name            string (unique)
cuenta_bancaria string (nullable)
monto           decimal(9,2) (default: 0)
subcaja_id      string (FK → subcaja)
```

**Relaciones:**
- `belongsTo`: SubCaja
- `hasMany`: DespliegueDePago

---

### 14. DespliegueDePago
**Tabla:** `desplieguedepago`
**Modelo Prisma:** `DespliegueDePago`

**Campos:**
```php
id                string (PK, cuid)
name              string (unique)
adicional         decimal(9,2) (default: 0)
mostrar           boolean (default: true)
metodo_de_pago_id string (FK → metododepago)
```

**Relaciones:**
- `belongsTo`: MetodoDePago
- `hasMany`: DespliegueDePagoVenta, PagoDeCompra, EgresoDinero, IngresoDinero
- `hasMany`: Compra (foreign key en Compra)

---

### 15. DespliegueDePagoVenta
**Tabla:** `desplieguedepagoventa`
**Modelo Prisma:** `DespliegueDePagoVenta`

**Campos:**
```php
id                    int (PK, autoincrement)
venta_id              string (FK → venta)
despliegue_de_pago_id string (FK → desplieguedepago)
monto                 decimal(9,4)
```

**Índices:**
- UNIQUE: [venta_id, despliegue_de_pago_id]

**Relaciones:**
- `belongsTo`: Venta, DespliegueDePago

---

### 16. PagoDeCompra
**Tabla:** `pagodecompra`
**Modelo Prisma:** `PagoDeCompra`

**Campos:**
```php
id                    string (PK, cuid)
estado                boolean (default: true)
compra_id             string (FK → compra)
despliegue_de_pago_id string (FK → desplieguedepago)
monto                 decimal(9,2)
```

**Relaciones:**
- `belongsTo`: Compra, DespliegueDePago

---

### 17. AperturaYCierreCaja
**Tabla:** `aperturaycierrecaja`
**Modelo Prisma:** `AperturaYCierreCaja`

**Campos:**
```php
id             string (PK, cuid)
fecha_apertura datetime (default: now)
monto_apertura decimal(9,2) (default: 0)
fecha_cierre   datetime (nullable)
monto_cierre   decimal(9,2) (nullable)
user_id        string (FK → user)
```

**Índices:**
- INDEX: [fecha_apertura, fecha_cierre]

**Relaciones:**
- `belongsTo`: User

---

### 18. EgresoDinero
**Tabla:** `egresodinero`
**Modelo Prisma:** `EgresoDinero`

**Campos:**
```php
id                    string (PK, cuid)
monto                 decimal(9,2)
descripcion           string (nullable)
fecha                 datetime
despliegue_de_pago_id string (FK → desplieguedepago)
user_id               string (FK → user)
created_at            datetime
updated_at            datetime
```

**Relaciones:**
- `belongsTo`: DespliegueDePago, User
- `hasMany`: Compra (foreign key en Compra)

---

### 19. IngresoDinero
**Tabla:** `ingresodinero`
**Modelo Prisma:** `IngresoDinero`

**Campos:**
```php
id                    string (PK, cuid)
monto                 decimal(9,2)
descripcion           string (nullable)
fecha                 datetime
despliegue_de_pago_id string (FK → desplieguedepago)
user_id               string (FK → user)
created_at            datetime
updated_at            datetime
```

**Relaciones:**
- `belongsTo`: DespliegueDePago, User

---

## 🟠 PRIORIDAD MEDIA - Relaciones de Ventas y Compras (6 modelos)

### 20. ProductoAlmacenVenta
**Tabla:** `productoalmacenventa`
**Modelo Prisma:** `ProductoAlmacenVenta`

**Campos:**
```php
id                  int (PK, autoincrement)
venta_id            string (FK → venta)
costo               decimal(9,4)
producto_almacen_id int (FK → productoalmacen)
```

**Índices:**
- UNIQUE: [venta_id, producto_almacen_id]

**Relaciones:**
- `belongsTo`: Venta, ProductoAlmacen
- `hasMany`: UnidadDerivadaInmutableVenta

---

### 21. UnidadDerivadaInmutableVenta
**Tabla:** `unidadderivadainmutableventa`
**Modelo Prisma:** `UnidadDerivadaInmutableVenta`

**Campos:**
```php
id                           int (PK, autoincrement)
unidad_derivada_inmutable_id int (FK → unidadderivadainmutable)
producto_almacen_venta_id    int (FK → productoalmacenventa)
factor                       decimal(9,3)
cantidad                     decimal(9,3)
cantidad_pendiente           decimal(9,3)
precio                       decimal(9,4)
recargo                      decimal(9,4) (default: 0)
descuento_tipo               enum DescuentoTipo (default: 'Monto')
descuento                    decimal(9,4) (default: 0)
comision                     decimal(9,4) (default: 0)
```

**Índices:**
- UNIQUE: [producto_almacen_venta_id, unidad_derivada_inmutable_id]

**Enums:**
```php
enum DescuentoTipo: string {
    case Porcentaje = '%';
    case Monto = 'm';
}
```

**Relaciones:**
- `belongsTo`: ProductoAlmacenVenta, UnidadDerivadaInmutable
- `hasMany`: DetalleEntregaProducto

---

### 22. ProductoAlmacenCompra
**Tabla:** `productoalmacencompra`
**Modelo Prisma:** `ProductoAlmacenCompra`

**Campos:**
```php
id                  int (PK, autoincrement)
compra_id           string (FK → compra)
costo               decimal(9,4)
producto_almacen_id int (FK → productoalmacen)
```

**Índices:**
- UNIQUE: [compra_id, producto_almacen_id]

**Relaciones:**
- `belongsTo`: Compra, ProductoAlmacen
- `hasMany`: UnidadDerivadaInmutableCompra

---

### 23. UnidadDerivadaInmutableCompra
**Tabla:** `unidadderivadainmutablecompra`
**Modelo Prisma:** `UnidadDerivadaInmutableCompra`

**Campos:**
```php
id                           int (PK, autoincrement)
unidad_derivada_inmutable_id int (FK → unidadderivadainmutable)
producto_almacen_compra_id   int (FK → productoalmacencompra)
factor                       decimal(9,3)
cantidad                     decimal(9,3)
cantidad_pendiente           decimal(9,3)
lote                         string (nullable)
vencimiento                  datetime (nullable)
flete                        decimal(9,4) (default: 0)
bonificacion                 boolean (default: false)
```

**Índices:**
- UNIQUE: [producto_almacen_compra_id, unidad_derivada_inmutable_id, bonificacion]
- INDEX: [cantidad_pendiente]

**Relaciones:**
- `belongsTo`: ProductoAlmacenCompra, UnidadDerivadaInmutable

---

### 24. ProductoAlmacenCotizacion
**Tabla:** `productoalmacencotizacion`
**Modelo Prisma:** `ProductoAlmacenCotizacion`

**Campos:**
```php
id                  int (PK, autoincrement)
cotizacion_id       string (FK → cotizacion)
costo               decimal(9,4)
producto_almacen_id int (FK → productoalmacen)
```

**Índices:**
- UNIQUE: [cotizacion_id, producto_almacen_id]

**Relaciones:**
- `belongsTo`: Cotizacion, ProductoAlmacen
- `hasMany`: UnidadDerivadaInmutableCotizacion

---

### 25. UnidadDerivadaInmutableCotizacion
**Tabla:** `unidadderivadainmutablecotizacion`
**Modelo Prisma:** `UnidadDerivadaInmutableCotizacion`

**Campos:**
```php
id                              int (PK, autoincrement)
unidad_derivada_inmutable_id    int (FK → unidadderivadainmutable)
producto_almacen_cotizacion_id  int (FK → productoalmacencotizacion)
factor                          decimal(9,3)
cantidad                        decimal(9,3)
precio                          decimal(9,4)
recargo                         decimal(9,4) (default: 0)
descuento_tipo                  enum DescuentoTipo (default: 'Monto')
descuento                       decimal(9,4) (default: 0)
```

**Índices:**
- UNIQUE: [producto_almacen_cotizacion_id, unidad_derivada_inmutable_id]

**Relaciones:**
- `belongsTo`: ProductoAlmacenCotizacion, UnidadDerivadaInmutable

---

## 🟢 PRIORIDAD BAJA - Entidades Auxiliares (4 modelos)

### 26. UnidadDerivada
**Tabla:** `unidadderivada`
**Modelo Prisma:** `UnidadDerivada`

**Campos:**
```php
id               int (PK, autoincrement)
name             string
factor           decimal(9,3)
factor_compra    decimal(9,3)
producto_id      int (FK → producto)
unidad_medida_id int (FK → unidadmedida)
estado           boolean (default: true)
created_at       datetime
updated_at       datetime
```

**Relaciones:**
- `belongsTo`: Producto, UnidadMedida
- `hasMany`: ProductoAlmacenUnidadDerivada

---

### 27. ProductoAlmacenUnidadDerivada
**Tabla:** `productoalmacenunidadderivada`
**Modelo Prisma:** `ProductoAlmacenUnidadDerivada`

**Campos:**
```php
id                  int (PK, autoincrement)
producto_almacen_id int (FK → productoalmacen)
unidad_derivada_id  int (FK → unidadderivada)
precio_compra       decimal(9,4)
precio_venta        decimal(9,4)
created_at          datetime
updated_at          datetime
```

**Índices:**
- UNIQUE: [producto_almacen_id, unidad_derivada_id]

**Relaciones:**
- `belongsTo`: ProductoAlmacen, UnidadDerivada

---

### 28. UnidadDerivadaInmutable
**Tabla:** `unidadderivadainmutable`
**Modelo Prisma:** `UnidadDerivadaInmutable`

**Campos:**
```php
id   int (PK, autoincrement)
name string (unique)
```

**Relaciones:**
- `hasMany`: UnidadDerivadaInmutableCompra
- `hasMany`: UnidadDerivadaInmutableVenta
- `hasMany`: UnidadDerivadaInmutableCotizacion
- `hasMany`: UnidadDerivadaInmutableIngresoSalida
- `hasMany`: UnidadDerivadaInmutableRecepcion

---

### 29. SerieDocumento
**Tabla:** `seriedocumento`
**Modelo Prisma:** `SerieDocumento`

**Campos:**
```php
id             int (PK, autoincrement)
serie          string
tipo_documento enum TipoDocumento
empresa_id     int (FK → empresa)
estado         boolean (default: true)
created_at     datetime
updated_at     datetime
```

**Índices:**
- UNIQUE: [serie, tipo_documento, empresa_id]

**Relaciones:**
- `belongsTo`: Empresa

---

## ⚪ PRIORIDAD BAJA - Proveedor Relacionados (3 modelos)

### 30. Chofer
**Tabla:** `chofer`
**Modelo Prisma:** `Chofer`

**Campos:**
```php
id           string (PK, cuid)
name         string
dni          string (unique)
licencia     string (unique)
telefono     string (nullable)
estado       boolean (default: true)
proveedor_id int (FK → proveedor)
created_at   datetime
updated_at   datetime
```

**Relaciones:**
- `belongsTo`: Proveedor

---

### 31. Vendedor
**Tabla:** `vendedor`
**Modelo Prisma:** `Vendedor`

**Campos:**
```php
id           string (PK, cuid)
name         string
telefono     string (nullable)
email        string (nullable, unique)
estado       boolean (default: true)
proveedor_id int (FK → proveedor)
created_at   datetime
updated_at   datetime
```

**Relaciones:**
- `belongsTo`: Proveedor

---

### 32. Carro
**Tabla:** `carro`
**Modelo Prisma:** `Carro`

**Campos:**
```php
id           string (PK, cuid)
placa        string (unique)
marca        string (nullable)
modelo       string (nullable)
estado       boolean (default: true)
proveedor_id int (FK → proveedor)
created_at   datetime
updated_at   datetime
```

**Relaciones:**
- `belongsTo`: Proveedor

---

## ⚫ NO IMPLEMENTAR - NextAuth (3 tablas)

Estas tablas son de NextAuth y NO deben implementarse en Laravel:

- ~~Account~~ → `account` (OAuth)
- ~~Session~~ → `session` (NextAuth sessions)
- ~~Authenticator~~ → `authenticator` (WebAuthn)
- ~~VerificationToken~~ → `verificationtoken`

**Razón:** Laravel usa Sanctum para autenticación API.

---

## 📋 Plan de Implementación Recomendado

### Fase 1: Almacén e Inventario (5 modelos)
```bash
php artisan make:model IngresoSalida
php artisan make:model ProductoAlmacenIngresoSalida
php artisan make:model UnidadDerivadaInmutableIngresoSalida
php artisan make:model HistorialUnidadDerivadaInmutableIngresoSalida
php artisan make:model TipoIngresoSalida
```

### Fase 2: Recepciones (4 modelos)
```bash
php artisan make:model RecepcionAlmacen
php artisan make:model ProductoAlmacenRecepcion
php artisan make:model UnidadDerivadaInmutableRecepcion
php artisan make:model HistorialUnidadDerivadaInmutableRecepcion
```

### Fase 3: Entregas (2 modelos)
```bash
php artisan make:model EntregaProducto
php artisan make:model DetalleEntregaProducto
```

### Fase 4: Sistema de Caja y Pagos (7 modelos)
```bash
php artisan make:model SubCaja
php artisan make:model MetodoDePago
php artisan make:model DespliegueDePago
php artisan make:model DespliegueDePagoVenta
php artisan make:model PagoDeCompra
php artisan make:model AperturaYCierreCaja
php artisan make:model EgresoDinero
php artisan make:model IngresoDinero
```

### Fase 5: Detalles de Ventas y Compras (6 modelos)
```bash
php artisan make:model ProductoAlmacenVenta
php artisan make:model UnidadDerivadaInmutableVenta
php artisan make:model ProductoAlmacenCompra
php artisan make:model UnidadDerivadaInmutableCompra
php artisan make:model ProductoAlmacenCotizacion
php artisan make:model UnidadDerivadaInmutableCotizacion
```

### Fase 6: Auxiliares (7 modelos)
```bash
php artisan make:model UnidadDerivada
php artisan make:model ProductoAlmacenUnidadDerivada
php artisan make:model UnidadDerivadaInmutable
php artisan make:model SerieDocumento
php artisan make:model Chofer
php artisan make:model Vendedor
php artisan make:model Carro
```

---

## ⚙️ Template de Configuración

Todos los modelos deben usar esta configuración base:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NombreModelo extends Model
{
    // Tabla en minúsculas sin guiones bajos (convención Prisma)
    protected $table = 'nombredetabla';

    // Si usa CUID como PK
    protected $keyType = 'string';
    public $incrementing = false;

    // Timestamps en camelCase (convención Prisma)
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    // Si NO usa timestamps
    public $timestamps = false;

    protected $fillable = [
        // Lista de campos
    ];

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
            // Otros casts
        ];
    }

    // Relaciones aquí
}
```

---

## 🎯 Siguiente Paso Inmediato

**Comenzar con Fase 1 y 2** (9 modelos):
- Sistema de ingresos/salidas de almacén
- Sistema de recepciones de compras

Estos son fundamentales para la operación del almacén.

---

## 📚 Referencias

- **Schema Prisma:** `C:\laragon\www\ferreteria2\prisma\schema.prisma`
- **Modelos Prisma:** `C:\laragon\www\ferreteria2\prisma\models\`
- **Modelos Laravel:** `C:\laragon\www\ferreteria-backend\app\Models\`
- **Base de Datos:** `ferreteria2` (MySQL)
- **Total de Tablas:** 74 tablas verificadas

---

**Análisis realizado:** 18 de Diciembre 2025
**Versión:** 2.0 (Detallado)
**Estado:** ✅ Verificado contra base de datos real
