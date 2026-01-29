# 🔄 APIs de Movimientos de Caja

## 📋 Índice
- [Préstamos entre Cajas](#préstamos-entre-cajas)
- [Movimientos Internos](#movimientos-internos)
- [Historial de Transacciones](#historial-de-transacciones)
- [Casos de Uso](#casos-de-uso)

---

## 🤝 Préstamos entre Cajas

### 1. Listar Préstamos
```http
GET /api/cajas/prestamos
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "01JKXYZ...",
        "sub_caja_origen_id": 4,
        "sub_caja_destino_id": 1,
        "monto": "200.00",
        "estado": "pendiente",
        "motivo": "Préstamo para dar vueltos",
        "fecha_prestamo": "2026-01-19T10:30:00",
        "fecha_devolucion": null,
        "sub_caja_origen": {
          "id": 4,
          "nombre": "Efectivo - Yorsh"
        },
        "sub_caja_destino": {
          "id": 1,
          "nombre": "Efectivo - Victor"
        },
        "user_presta": {
          "id": "ct93...",
          "name": "Yorsh"
        },
        "user_recibe": {
          "id": "ct94...",
          "name": "Victor"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 2. Crear Préstamo
```http
POST /api/cajas/prestamos
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "sub_caja_origen_id": 4,
  "sub_caja_destino_id": 1,
  "monto": 200.00,
  "despliegue_de_pago_id": "cmj8o0plw0004uk0opeg9u7u6",
  "motivo": "Préstamo para dar vueltos",
  "user_recibe_id": "ct93fh5k3dbu10oi2t74caj"
}
```

**Validaciones:**
- `sub_caja_origen_id`: Requerido, debe existir
- `sub_caja_destino_id`: Requerido, debe existir, diferente al origen
- `monto`: Requerido, mayor a 0
- `despliegue_de_pago_id`: Opcional, debe existir si se envía
- `motivo`: Opcional, máximo 1000 caracteres
- `user_recibe_id`: Requerido, debe existir

**Respuesta Exitosa (201):**
```json
{
  "success": true,
  "message": "Préstamo registrado exitosamente",
  "data": {
    "id": "01JKXYZ...",
    "sub_caja_origen_id": 4,
    "sub_caja_destino_id": 1,
    "monto": "200.00",
    "estado": "pendiente",
    "fecha_prestamo": "2026-01-19T10:30:00"
  }
}
```

**Respuesta Error - Saldo Insuficiente (400):**
```json
{
  "success": false,
  "message": "Saldo insuficiente en la sub-caja origen",
  "data": {
    "saldo_disponible": "150.00",
    "monto_solicitado": "200.00"
  }
}
```

---

### 3. Devolver Préstamo
```http
POST /api/cajas/prestamos/{id}/devolver
Authorization: Bearer {token}
```

**Respuesta Exitosa:**
```json
{
  "success": true,
  "message": "Préstamo devuelto exitosamente",
  "data": {
    "id": "01JKXYZ...",
    "estado": "devuelto",
    "fecha_devolucion": "2026-01-19T15:30:00"
  }
}
```

**Respuesta Error - Ya Devuelto (400):**
```json
{
  "success": false,
  "message": "Este préstamo ya fue devuelto o cancelado"
}
```

---

## 🔄 Movimientos Internos

### 1. Listar Movimientos Internos
```http
GET /api/cajas/movimientos-internos
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "01JKXYZ...",
        "sub_caja_origen_id": 4,
        "sub_caja_destino_id": 5,
        "monto": "500.00",
        "justificacion": "Transferencia bancaria - Depósito en cuenta BCP",
        "comprobante": "DEP-001234",
        "fecha": "2026-01-19T11:00:00",
        "sub_caja_origen": {
          "id": 4,
          "nombre": "Efectivo"
        },
        "sub_caja_destino": {
          "id": 5,
          "nombre": "BCP"
        },
        "user": {
          "id": "ct93...",
          "name": "Yorsh"
        }
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

---

### 2. Crear Movimiento Interno
```http
POST /api/cajas/movimientos-internos
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "sub_caja_origen_id": 4,
  "sub_caja_destino_id": 5,
  "monto": 500.00,
  "despliegue_de_pago_id": "cmj8o0pmm0008uk0osi9l8w88",
  "justificacion": "Transferencia bancaria - Depósito en cuenta BCP",
  "comprobante": "DEP-001234"
}
```

**Validaciones:**
- `sub_caja_origen_id`: Requerido, debe existir
- `sub_caja_destino_id`: Requerido, debe existir, diferente al origen
- `monto`: Requerido, mayor a 0
- `despliegue_de_pago_id`: Opcional, debe existir si se envía
- `justificacion`: Requerido, máximo 1000 caracteres
- `comprobante`: Opcional, máximo 255 caracteres

**Respuesta Exitosa (201):**
```json
{
  "success": true,
  "message": "Movimiento interno registrado exitosamente",
  "data": {
    "id": "01JKXYZ...",
    "sub_caja_origen_id": 4,
    "sub_caja_destino_id": 5,
    "monto": "500.00",
    "justificacion": "Transferencia bancaria - Depósito en cuenta BCP",
    "fecha": "2026-01-19T11:00:00"
  }
}
```

**Respuesta Error - No es propietario (403):**
```json
{
  "success": false,
  "message": "Solo puedes mover dinero entre tus propias sub-cajas"
}
```

**Respuesta Error - Saldo Insuficiente (400):**
```json
{
  "success": false,
  "message": "Saldo insuficiente en la sub-caja origen",
  "data": {
    "saldo_disponible": "300.00",
    "monto_solicitado": "500.00"
  }
}
```

---

## 📊 Historial de Transacciones

### 1. Listar Transacciones de una Sub-Caja
```http
GET /api/cajas/sub-cajas/{subCajaId}/transacciones?per_page=15&page=1
Authorization: Bearer {token}
```

**Parámetros Query:**
- `per_page`: Número de registros por página (default: 15)
- `page`: Número de página (default: 1)

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": "01JKXYZ...",
      "sub_caja_id": 4,
      "tipo_transaccion": "ingreso",
      "monto": "150.00",
      "saldo_anterior": "5100.00",
      "saldo_nuevo": "5250.00",
      "descripcion": "Venta con factura #F001-00123",
      "referencia_id": "venta_123",
      "referencia_tipo": "venta",
      "fecha": "2026-01-19T14:30:00",
      "user": {
        "id": "ct93...",
        "name": "Yorsh"
      }
    },
    {
      "id": "01JKXYZ...",
      "sub_caja_id": 4,
      "tipo_transaccion": "prestamo_enviado",
      "monto": "200.00",
      "saldo_anterior": "5250.00",
      "saldo_nuevo": "5050.00",
      "descripcion": "Préstamo enviado a Efectivo - Victor",
      "referencia_id": "prestamo_456",
      "referencia_tipo": "prestamo",
      "fecha": "2026-01-19T15:00:00",
      "user": {
        "id": "ct93...",
        "name": "Yorsh"
      }
    },
    {
      "id": "01JKXYZ...",
      "sub_caja_id": 4,
      "tipo_transaccion": "movimiento_interno_salida",
      "monto": "500.00",
      "saldo_anterior": "5050.00",
      "saldo_nuevo": "4550.00",
      "descripcion": "Movimiento a BCP: Depósito en cuenta",
      "referencia_id": "movimiento_789",
      "referencia_tipo": "movimiento_interno",
      "fecha": "2026-01-19T16:00:00",
      "user": {
        "id": "ct93...",
        "name": "Yorsh"
      }
    }
  ],
  "pagination": {
    "total": 150,
    "per_page": 15,
    "current_page": 1,
    "last_page": 10
  }
}
```

---

### 2. Listar Transacciones de una Caja Principal
```http
GET /api/cajas/cajas-principales/{cajaPrincipalId}/transacciones?per_page=15&page=1
Authorization: Bearer {token}
```

**Para qué:** Ver el historial completo de todas las sub-cajas de un vendedor en un solo lugar.

**Parámetros Query:**
- `per_page`: Número de registros por página (default: 15)
- `page`: Número de página (default: 1)

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": "01JKXYZ...",
      "sub_caja_id": 4,
      "tipo_transaccion": "ingreso",
      "monto": "150.00",
      "saldo_anterior": "5100.00",
      "saldo_nuevo": "5250.00",
      "descripcion": "Venta con factura #F001-00123",
      "fecha": "2026-01-19T14:30:00",
      "sub_caja": {
        "id": 4,
        "nombre": "Efectivo",
        "caja_principal_id": 1
      },
      "user": {
        "id": "ct93...",
        "name": "Yorsh"
      }
    },
    {
      "id": "01JKXYZ...",
      "sub_caja_id": 5,
      "tipo_transaccion": "movimiento_interno_entrada",
      "monto": "500.00",
      "saldo_anterior": "1000.00",
      "saldo_nuevo": "1500.00",
      "descripcion": "Movimiento desde Efectivo: Depósito en cuenta",
      "fecha": "2026-01-19T16:00:00",
      "sub_caja": {
        "id": 5,
        "nombre": "BCP",
        "caja_principal_id": 1
      },
      "user": {
        "id": "ct93...",
        "name": "Yorsh"
      }
    }
  ],
  "pagination": {
    "total": 250,
    "per_page": 15,
    "current_page": 1,
    "last_page": 17
  }
}
```

---

## 🎯 Casos de Uso

### Caso 1: Préstamo entre Vendedores
**Escenario:** Yorsh presta S/ 200 a Victor para dar vueltos

1. Yorsh tiene S/ 1000 en su sub-caja "Efectivo"
2. Victor necesita S/ 200 para dar vueltos
3. Yorsh crea un préstamo:
   - Origen: Sub-caja Efectivo de Yorsh (ID: 4)
   - Destino: Sub-caja Efectivo de Victor (ID: 1)
   - Monto: S/ 200
   - Usuario que recibe: Victor

**Resultado:**
- Saldo Yorsh: S/ 1000 → S/ 800
- Saldo Victor: S/ 500 → S/ 700
- Se registra préstamo con estado "pendiente"
- Se crean 2 transacciones (una por cada sub-caja)

---

### Caso 2: Movimiento Interno
**Escenario:** Yorsh tiene mucho efectivo y va al banco a depositar

1. Yorsh tiene S/ 5000 en su sub-caja "Efectivo"
2. Va al banco y deposita S/ 3000 en su cuenta BCP
3. Registra el movimiento interno:
   - Origen: Sub-caja Efectivo (ID: 4)
   - Destino: Sub-caja BCP (ID: 5)
   - Monto: S/ 3000
   - Justificación: "Depósito en cuenta BCP"
   - Comprobante: "DEP-001234"

**Resultado:**
- Saldo Efectivo: S/ 5000 → S/ 2000
- Saldo BCP: S/ 1000 → S/ 4000
- Se crean 2 transacciones (una por cada sub-caja)

---

## ✅ Características Implementadas

### Préstamos entre Cajas
- ✅ Validación de saldos antes de prestar
- ✅ Actualización automática de saldos
- ✅ Registro en transacciones_caja
- ✅ Especificación de método de pago
- ✅ Estado del préstamo (pendiente/devuelto)
- ✅ Devolución de préstamos
- ✅ Historial de préstamos con paginación

### Movimientos Internos
- ✅ Validación de propiedad (solo propias sub-cajas)
- ✅ Validación de saldos
- ✅ Actualización automática de saldos
- ✅ Registro en transacciones_caja
- ✅ Especificación de método de pago
- ✅ Justificación obligatoria
- ✅ Comprobante opcional
- ✅ Historial de movimientos con paginación

---

## 🔐 Seguridad

- Todas las rutas requieren autenticación (`auth:sanctum`)
- Los movimientos internos solo permiten mover entre sub-cajas del mismo usuario
- Los préstamos requieren especificar el usuario que recibe
- Validación de saldos antes de cualquier operación
- Transacciones atómicas (rollback en caso de error)

---

## 📊 Tipos de Transacciones Registradas

### Préstamos
- `prestamo_enviado`: Registrado en la sub-caja origen
- `prestamo_recibido`: Registrado en la sub-caja destino
- `egreso`: Cuando se devuelve (sub-caja destino)
- `ingreso`: Cuando se devuelve (sub-caja origen)

### Movimientos Internos
- `movimiento_interno_salida`: Registrado en la sub-caja origen
- `movimiento_interno_entrada`: Registrado en la sub-caja destino

### Ventas y Otros
- `ingreso`: Ventas, cobros, devoluciones recibidas
- `egreso`: Gastos, retiros, devoluciones realizadas

---

## 🔍 Filtros y Búsqueda

### Por Sub-Caja
Útil para ver el historial de una sub-caja específica (ej: solo Efectivo, solo BCP)

```http
GET /api/cajas/sub-cajas/4/transacciones?per_page=20&page=1
```

### Por Caja Principal
Útil para ver el historial completo de un vendedor (todas sus sub-cajas)

```http
GET /api/cajas/cajas-principales/1/transacciones?per_page=20&page=1
```
