# API de Apertura y Cierre de Caja

Documentación completa de los endpoints para apertura, cierre e historial de cajas.

## Base URL
```
http://127.0.0.1:8000/api/cajas
```

---

## 📋 Índice
1. [Aperturar Caja](#1️⃣-aperturar-caja)
2. [Consultar Apertura Activa](#2️⃣-consultar-apertura-activa)
3. [Historial de Aperturas](#3️⃣-historial-de-aperturas)
4. [Obtener Caja Activa](#4️⃣-obtener-caja-activa-del-vendedor)
5. [Resumen de Movimientos](#5️⃣-obtener-resumen-de-movimientos)
6. [Validar Supervisor](#6️⃣-validar-supervisor)
7. [Cerrar Caja](#7️⃣-cerrar-caja)

---

## 1️⃣ Aperturar Caja

**Endpoint:** `POST /api/cajas/aperturar`

**Descripción:** Apertura una caja principal o agrega efectivo si ya está abierta.

### Headers
```
Authorization: Bearer {token}
```

### Request Body
```json
{
  "caja_principal_id": 1,
  "monto_apertura": 500.00
}
```

### Response 200 - Éxito
```json
{
  "success": true,
  "message": "Caja aperturada exitosamente",
  "data": {
    "id": "cm123abc",
    "caja_principal_id": 1,
    "monto_apertura": "500.00",
    "fecha_apertura": "2026-01-19T08:00:00.000Z",
    "estado": "abierta"
  }
}
```

---

## 2️⃣ Consultar Apertura Activa

**Endpoint:** `GET /api/cajas/consulta-apertura/{cajaPrincipalId}`

**Descripción:** Verifica si una caja principal tiene una apertura activa.

### Response 200 - Con Apertura
```json
{
  "success": true,
  "message": "Apertura activa encontrada",
  "data": {
    "id": "cm123abc",
    "caja_principal_id": 1,
    "monto_apertura": "500.00",
    "fecha_apertura": "2026-01-19T08:00:00.000Z",
    "estado": "abierta"
  }
}
```

### Response 200 - Sin Apertura
```json
{
  "success": true,
  "message": "No hay apertura activa",
  "data": null
}
```

---

## 3️⃣ Historial de Aperturas

**Endpoint:** `GET /api/cajas/historial-aperturas`

**Descripción:** Lista el historial de aperturas y cierres del vendedor actual.

### Headers
```
Authorization: Bearer {token}
```

### Query Parameters
- `per_page`: Registros por página (default: 15)
- `page`: Número de página (default: 1)

### Response 200 - Éxito
```json
{
  "success": true,
  "data": [
    {
      "id": "cm123abc",
      "caja_principal_id": 1,
      "monto_apertura": "500.00",
      "monto_cierre": "1250.00",
      "fecha_apertura": "2026-01-19T08:00:00.000Z",
      "fecha_cierre": "2026-01-19T18:00:00.000Z",
      "estado": "cerrada",
      "caja_principal": {
        "id": 1,
        "codigo": "V01-CAJA",
        "nombre": "Caja Principal - Victor"
      },
      "sub_caja": {
        "id": 5,
        "codigo": "V01-CAJA-001",
        "nombre": "Caja Chica"
      },
      "user": {
        "id": "ct93...",
        "name": "Victor"
      }
    },
    {
      "id": "cm456def",
      "caja_principal_id": 1,
      "monto_apertura": "800.00",
      "monto_cierre": null,
      "fecha_apertura": "2026-01-20T08:00:00.000Z",
      "fecha_cierre": null,
      "estado": "abierta",
      "caja_principal": {
        "id": 1,
        "codigo": "V01-CAJA",
        "nombre": "Caja Principal - Victor"
      },
      "sub_caja": {
        "id": 5,
        "codigo": "V01-CAJA-001",
        "nombre": "Caja Chica"
      },
      "user": {
        "id": "ct93...",
        "name": "Victor"
      }
    }
  ],
  "pagination": {
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2
  }
}
```

---

## 4️⃣ Obtener Caja Activa del Vendedor

**Endpoint:** `GET /api/cajas/activa`

**Descripción:** Obtiene la caja abierta del vendedor actual para poder cerrarla.

### Headers
```
Authorization: Bearer {token}
```

### Query Parameters (opcional para testing)
```
?user_id=ct93fh5k3dbu10oi2t74caj
```

### Response 200 - Éxito
```json
{
  "success": true,
  "data": {
    "id": "cm123abc",
    "caja_principal_id": 1,
    "sub_caja_id": 5,
    "user_id": "ct93fh5k3dbu10oi2t74caj",
    "monto_apertura": "800.00",
    "fecha_apertura": "2026-01-19T08:00:00.000Z",
    "estado": "abierta",
    "caja_principal": {
      "id": 1,
      "codigo": "V01-CAJA",
      "nombre": "Caja Principal - Victor"
    },
    "sub_caja_chica": {
      "id": 5,
      "codigo": "V01-CAJA-001",
      "nombre": "Caja Chica",
      "saldo_actual": "850.00"
    },
    "resumen": {
      "total_ventas": 500.00,
      "total_cobros": 500.00,
      "total_tarjetas": 200.00,
      "total_yape": 150.00,
      "total_izipay": 50.00,
      "total_transferencias": 0.00,
      "total_otros": 0.00,
      "total_efectivo_esperado": 100.00,
      "total_otros_ingresos": 50.00,
      "total_anulados": 0.00,
      "total_devoluciones": 0.00,
      "total_gastos": 0.00,
      "total_pagos": 0.00,
      "resumen_ventas": 500.00,
      "resumen_ingresos": 50.00,
      "resumen_egresos": 0.00,
      "total_en_caja": 850.00
    }
  }
}
```

### Response 404 - Sin caja abierta
```json
{
  "success": false,
  "message": "No tienes una caja abierta"
}
```

---

## 5️⃣ Obtener Resumen de Movimientos

**Endpoint:** `POST /api/cajas/{id}/cerrar`

**Descripción:** Cierra la caja del vendedor registrando el conteo de efectivo y calculando diferencias.

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
  "monto_cierre_efectivo": 850.00,
  "conteo_billetes_monedas": {
    "billete_200": 1,
    "billete_100": 2,
    "billete_50": 4,
    "billete_20": 5,
    "billete_10": 5,
    "moneda_5": 10,
    "moneda_2": 0,
    "moneda_1": 0,
    "moneda_050": 0,
    "moneda_020": 0,
    "moneda_010": 0
  },
  "total_cuentas": 400.00,
  "conceptos_adicionales": [
    {
      "concepto": "Venta especial",
      "numero": "001",
      "cantidad": 50.00
    }
  ],
  "comentarios": "Todo correcto",
  "supervisor_id": 1,
  "forzar_cierre": false
}
```

### Parámetros

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| monto_cierre_efectivo | number | ✅ | Total de efectivo contado |
| conteo_billetes_monedas | object | ❌ | Detalle del conteo (opcional) |
| total_cuentas | number | ✅ | Total de pagos digitales (tarjetas, yape, etc.) |
| conceptos_adicionales | array | ❌ | Conceptos extras registrados |
| comentarios | string | ❌ | Comentarios del cierre |
| supervisor_id | number | ❌ | ID del supervisor que revisa |
| forzar_cierre | boolean | ❌ | Forzar cierre aunque haya diferencias |

### Validaciones
- ✅ La caja debe estar abierta
- ✅ Solo el dueño de la caja puede cerrarla (o un admin)
- ✅ `monto_cierre_efectivo` debe ser >= 0
- ✅ Si hay diferencias mayores a 10 soles, requiere supervisor
- ✅ Si `forzar_cierre` es true, requiere supervisor obligatorio

### Response 200 - Éxito
```json
{
  "success": true,
  "message": "Caja cerrada exitosamente",
  "data": {
    "id": "cm123abc",
    "caja_principal_id": 1,
    "sub_caja_id": 5,
    "user_id": "ct93fh5k3dbu10oi2t74caj",
    "monto_apertura": "800.00",
    "fecha_apertura": "2026-01-19T08:00:00.000Z",
    "monto_cierre": "850.00",
    "fecha_cierre": "2026-01-19T18:00:00.000Z",
    "estado": "cerrada",
    "diferencias": {
      "efectivo_esperado": "850.00",
      "efectivo_contado": "850.00",
      "diferencia_efectivo": "0.00",
      "total_esperado": "1250.00",
      "total_contado": "1250.00",
      "diferencia_total": "0.00",
      "sobrante": "0.00",
      "faltante": "0.00"
    },
    "supervisor": {
      "id": 1,
      "name": "ADMIN"
    },
    "comentarios": "Todo correcto"
  }
}
```

### Response 400 - Caja ya cerrada
```json
{
  "success": false,
  "message": "Esta caja ya está cerrada"
}
```

### Response 400 - Diferencias requieren supervisor
```json
{
  "success": false,
  "message": "Las diferencias superan el límite permitido. Se requiere autorización de supervisor.",
  "data": {
    "diferencia": 15.50,
    "limite": 10.00,
    "requiere_supervisor": true
  }
}
```

### Response 403 - No autorizado
```json
{
  "success": false,
  "message": "No tienes permiso para cerrar esta caja"
}
```

---

## 5️⃣ Obtener Resumen de Movimientos

**Endpoint:** `GET /api/cajas/{id}/resumen-movimientos`

**Descripción:** Obtiene el detalle de todos los movimientos de la caja para el cierre.

### Response 200
```json
{
  "success": true,
  "data": {
    "ventas": [],
    "ingresos": [],
    "egresos": [],
    "anulaciones": [],
    "totales_por_metodo": {
      "efectivo": "100.00",
      "tarjeta": "200.00",
      "yape": "150.00",
      "izipay": "50.00",
      "transferencia": "0.00",
      "otros": "0.00"
    }
  }
}
```

---

## 6️⃣ Validar Supervisor

**Endpoint:** `POST /api/cajas/validar-supervisor`

**Descripción:** Valida las credenciales del supervisor para autorizar cierres con diferencias.

### Request Body
```json
{
  "email": "admin@aplication.com",
  "password": "password123"
}
```

### Response 200 - Éxito
```json
{
  "success": true,
  "data": {
    "supervisor_id": 1,
    "name": "ADMIN",
    "puede_autorizar": true
  }
}
```

### Response 401 - Credenciales inválidas
```json
{
  "success": false,
  "message": "Credenciales inválidas"
}
```

### Response 403 - Sin permisos
```json
{
  "success": false,
  "message": "El usuario no tiene permisos de supervisor"
}
```

---

## 7️⃣ Cerrar Caja

**Endpoint:** `POST /api/cajas/{id}/cerrar`

**Descripción:** Cierra la caja del vendedor registrando el conteo de efectivo y calculando diferencias.

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
  "monto_cierre_efectivo": 850.00,
  "conteo_billetes_monedas": {
    "billete_200": 1,
    "billete_100": 2,
    "billete_50": 4,
    "billete_20": 5,
    "billete_10": 5,
    "moneda_5": 10,
    "moneda_2": 0,
    "moneda_1": 0,
    "moneda_050": 0,
    "moneda_020": 0,
    "moneda_010": 0
  },
  "total_cuentas": 400.00,
  "conceptos_adicionales": [
    {
      "concepto": "Venta especial",
      "numero": "001",
      "cantidad": 50.00
    }
  ],
  "comentarios": "Todo correcto",
  "supervisor_id": 1,
  "forzar_cierre": false
}
```

### Parámetros

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| monto_cierre_efectivo | number | ✅ | Total de efectivo contado |
| conteo_billetes_monedas | object | ❌ | Detalle del conteo (opcional) |
| total_cuentas | number | ✅ | Total de pagos digitales (tarjetas, yape, etc.) |
| conceptos_adicionales | array | ❌ | Conceptos extras registrados |
| comentarios | string | ❌ | Comentarios del cierre |
| supervisor_id | number | ❌ | ID del supervisor que revisa |
| forzar_cierre | boolean | ❌ | Forzar cierre aunque haya diferencias |

### Validaciones
- ✅ La caja debe estar abierta
- ✅ Solo el dueño de la caja puede cerrarla (o un admin)
- ✅ `monto_cierre_efectivo` debe ser >= 0
- ✅ Si hay diferencias mayores a 10 soles, requiere supervisor
- ✅ Si `forzar_cierre` es true, requiere supervisor obligatorio

### Response 200 - Éxito
```json
{
  "success": true,
  "message": "Caja cerrada exitosamente",
  "data": {
    "id": "cm123abc",
    "caja_principal_id": 1,
    "sub_caja_id": 5,
    "user_id": "ct93fh5k3dbu10oi2t74caj",
    "monto_apertura": "800.00",
    "fecha_apertura": "2026-01-19T08:00:00.000Z",
    "monto_cierre": "850.00",
    "fecha_cierre": "2026-01-19T18:00:00.000Z",
    "estado": "cerrada",
    "diferencias": {
      "efectivo_esperado": "850.00",
      "efectivo_contado": "850.00",
      "diferencia_efectivo": "0.00",
      "total_esperado": "1250.00",
      "total_contado": "1250.00",
      "diferencia_total": "0.00",
      "sobrante": "0.00",
      "faltante": "0.00"
    },
    "supervisor": {
      "id": 1,
      "name": "ADMIN"
    },
    "comentarios": "Todo correcto"
  }
}
```

### Response 400 - Caja ya cerrada
```json
{
  "success": false,
  "message": "Esta caja ya está cerrada"
}
```

### Response 400 - Diferencias requieren supervisor
```json
{
  "success": false,
  "message": "Las diferencias superan el límite permitido. Se requiere autorización de supervisor.",
  "data": {
    "diferencia": 15.50,
    "limite": 10.00,
    "requiere_supervisor": true
  }
}
```

### Response 403 - No autorizado
```json
{
  "success": false,
  "message": "No tienes permiso para cerrar esta caja"
}
```

---

## 🔄 Flujo Completo de Cierre de Caja

### 1. Vendedor abre la página de cierre
- Frontend llama: `GET /api/cajas/activa`
- Muestra datos de apertura y resumen de movimientos

### 2. Vendedor cuenta el efectivo
- Usa el componente ConteoDinero
- Calcula automáticamente el total

### 3. Sistema calcula diferencias
- Frontend compara: efectivo contado vs efectivo esperado
- Muestra sobrante/faltante

### 4. Si hay diferencias grandes (> 10 soles)
- Muestra modal pidiendo supervisor
- Llama: `POST /api/cajas/validar-supervisor`

### 5. Vendedor finaliza el cierre
- Frontend llama: `POST /api/cajas/{id}/cerrar`
- Incluye `supervisor_id` si fue necesario

### 6. Sistema cierra la caja
- Actualiza estado a "cerrada"
- Registra fecha y hora de cierre
- Guarda diferencias

---

## 📊 Estructura de Base de Datos

La tabla `apertura_cierre_caja` incluye los siguientes campos para el cierre:

```sql
monto_cierre_efectivo DECIMAL(10,2) NULL
monto_cierre_cuentas DECIMAL(10,2) NULL
conteo_billetes_monedas JSON NULL
conceptos_adicionales JSON NULL
comentarios TEXT NULL
supervisor_id BIGINT UNSIGNED NULL
diferencia_efectivo DECIMAL(10,2) NULL
diferencia_total DECIMAL(10,2) NULL
forzar_cierre BOOLEAN DEFAULT FALSE
```

---

## 🧪 Testing

### Ejemplo con cURL - Obtener caja activa
```bash
curl -X GET "http://127.0.0.1:8000/api/cajas/activa?user_id=ct93fh5k3dbu10oi2t74caj" \
  -H "Accept: application/json"
```

### Ejemplo con cURL - Cerrar caja
```bash
curl -X POST "http://127.0.0.1:8000/api/cajas/cm123abc/cerrar" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "monto_cierre_efectivo": 850.00,
    "total_cuentas": 400.00,
    "comentarios": "Todo correcto"
  }'
```

### Ejemplo con cURL - Validar supervisor
```bash
curl -X POST "http://127.0.0.1:8000/api/cajas/validar-supervisor" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@aplication.com",
    "password": "password123"
  }'
```
