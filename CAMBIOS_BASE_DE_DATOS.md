# Cambios en la Base de Datos - Migración a Laravel Backend

Este documento detalla todos los cambios realizados en la base de datos `ferreteria2` durante la migración del sistema de Next.js/Prisma a Laravel backend API.

---

## 📊 Información General

- **Base de datos:** `ferreteria2`
- **Motor:** MySQL
- **Fecha de migración:** 17-18 de Diciembre 2025
- **Objetivo:** Migrar autenticación de NextAuth a Laravel Sanctum manteniendo compatibilidad con datos existentes de Prisma

---

## 🆕 Tablas Nuevas Creadas

### 1. `sessions` (Laravel)
Tabla para el manejo de sesiones de Laravel.

```sql
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Gestión de sesiones de Laravel (aunque usamos tokens Sanctum para API)

---

### 2. `cache` y `cache_locks` (Laravel)
Tablas para el sistema de caché de Laravel.

```sql
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Sistema de caché de Laravel

---

### 3. `jobs` y `failed_jobs` (Laravel)
Tablas para el sistema de colas de Laravel.

```sql
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Sistema de colas y trabajos en segundo plano

---

### 4. `personal_access_tokens` (Laravel Sanctum)
Tabla para tokens de autenticación API (modificada posteriormente).

**Estructura inicial:**
```sql
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,  -- ⚠️ Era BIGINT (problema)
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Almacenar tokens de autenticación de Laravel Sanctum para API

---

## 🔧 Modificaciones a Tablas Existentes

### 1. Tabla `personal_access_tokens` - Cambio de Tipo de Dato

**Problema identificado:**
- La columna `tokenable_id` era de tipo `bigint unsigned`
- Los IDs de usuarios en Prisma son tipo `varchar(191)` (CUID)
- Al intentar crear un token, fallaba con: `Incorrect integer value: 'cmj8o0pf70001uk0o4d3tbyyx'`

**Solución aplicada:**

```sql
-- Migración: 2025_12_18_020051_modify_personal_access_tokens_for_string_ids.php

-- 1. Eliminar índice existente
ALTER TABLE `personal_access_tokens`
DROP INDEX `personal_access_tokens_tokenable_type_tokenable_id_index`;

-- 2. Cambiar tipo de columna de bigint a varchar
ALTER TABLE `personal_access_tokens`
MODIFY COLUMN `tokenable_id` varchar(191) NOT NULL;

-- 3. Recrear índice con nuevo tipo de dato
ALTER TABLE `personal_access_tokens`
ADD INDEX `personal_access_tokens_tokenable_type_tokenable_id_index`
(`tokenable_type`, `tokenable_id`);
```

**Estructura final:**
```sql
`tokenable_id` varchar(191) NOT NULL  -- ✅ Ahora soporta CUID
```

**Fecha:** 18 de Diciembre 2025
**Estado:** ✅ Aplicado exitosamente

---

## 📝 Actualizaciones de Datos

### 1. Usuario ADMIN - Actualización de Contraseña

**Cambio realizado:**
```sql
UPDATE `user`
SET `password` = '$argon2id$v=19$m=65536,t=4,p=1$...'
WHERE `email` = 'admin@aplication.com';
```

**Detalles:**
- Usuario: `admin@aplication.com`
- Nueva contraseña: `12345` (hasheada con Argon2)
- ID del usuario: `cmj8o0pf70001uk0o4d3tbyyx`

**Fecha:** 17 de Diciembre 2025
**Propósito:** Establecer contraseña conocida para pruebas de autenticación

---

### 2. Asignación de Rol al Usuario ADMIN

**Estado:** ✅ Ya existía en la base de datos

La relación entre el usuario ADMIN y el rol `admin_global` ya existía en la tabla `_roletouser` de Prisma:

```sql
-- Verificación (no fue necesario insertar)
SELECT * FROM _roletouser WHERE A = 1 AND B = 'cmj8o0pf70001uk0o4d3tbyyx';
-- Resultado: Relación ya existe
```

**Estructura de la relación:**
- Tabla: `_roletouser` (tabla intermedia de Prisma)
- Columna A (INT): `role.id` = 1 (admin_global)
- Columna B (VARCHAR): `user.id` = 'cmj8o0pf70001uk0o4d3tbyyx'

**Permisos asignados:** 88 permisos a través del rol `admin_global`

---

## 🔗 Tablas de Prisma Utilizadas (Sin Modificar)

Las siguientes tablas de Prisma se mantienen sin cambios y son utilizadas por el backend de Laravel:

### Autenticación y Permisos
- `user` - Usuarios del sistema
- `permission` - Permisos disponibles (88 registros)
- `role` - Roles del sistema (1 registro: admin_global)
- `_permissiontouser` - Relación muchos a muchos (permisos directos)
- `_roletouser` - Relación muchos a muchos (roles de usuarios)
- `_permissiontorole` - Relación muchos a muchos (permisos de roles)

### Tablas de Negocio (Sin cambios)
- `empresa`
- `cliente`
- `proveedor`
- `producto`
- `almacen`
- `venta`
- `compra`
- `cotizacion`
- Y todas las demás tablas existentes de Prisma

---

## ⚙️ Configuración de Eloquent Models

Para que Laravel funcione correctamente con las tablas de Prisma, se configuraron los modelos Eloquent:

### Convenciones de Prisma vs Laravel

| Aspecto | Prisma | Laravel | Configuración Requerida |
|---------|--------|---------|------------------------|
| Nombres de tablas | Singular (`user`) | Plural (`users`) | `protected $table = 'user'` |
| Timestamps | camelCase (`createdAt`) | snake_case (`created_at`) | `const CREATED_AT = 'createdAt'` |
| IDs | String (CUID) | BigInt autoincremental | `protected $keyType = 'string'`<br>`public $incrementing = false` |

### Ejemplo: User Model

```php
class User extends Authenticatable
{
    protected $table = 'user';              // Tabla singular
    protected $keyType = 'string';          // ID es string (CUID)
    public $incrementing = false;           // ID no autoincremental

    const CREATED_AT = 'createdAt';         // Timestamp en camelCase
    const UPDATED_AT = 'updatedAt';         // Timestamp en camelCase
}
```

---

## 📊 Resumen de Cambios

### Tablas Creadas: 7
1. `sessions`
2. `cache`
3. `cache_locks`
4. `jobs`
5. `failed_jobs`
6. `personal_access_tokens`
7. `migrations` (control de migraciones de Laravel)

### Tablas Modificadas: 1
1. `personal_access_tokens` - columna `tokenable_id` (bigint → varchar)

### Datos Actualizados: 1
1. Usuario `admin@aplication.com` - contraseña actualizada

### Tablas de Prisma Utilizadas: 90+ (sin modificar)

---

## 🔐 Seguridad

### Credenciales de Prueba

**⚠️ ADVERTENCIA:** Las siguientes credenciales son SOLO para desarrollo

```
Email: admin@aplication.com
Password: 12345
```

**🚨 IMPORTANTE:** Cambiar estas credenciales antes de producción

---

## 🎯 Estado Final

### ✅ Funcionando Correctamente
- ✅ Autenticación con Laravel Sanctum
- ✅ Tokens API con IDs tipo string (CUID)
- ✅ Sistema de permisos (88 permisos vía rol admin_global)
- ✅ Compatibilidad total con base de datos de Prisma
- ✅ Relaciones Eloquent configuradas correctamente

### 📋 Pendientes
- [ ] Migrar Server Actions a endpoints de API
- [ ] Crear controllers para todas las entidades
- [ ] Implementar middleware de permisos
- [ ] Pruebas exhaustivas de endpoints

---

## 📚 Referencias

- **Migración principal:** `php artisan migrate` (17 Dic 2025)
- **Migración personalizada:** `2025_12_18_020051_modify_personal_access_tokens_for_string_ids.php`
- **Base de datos:** `ferreteria2`
- **Laravel:** v12.x
- **Sanctum:** Laravel Sanctum API Authentication

---

## 🔄 Rollback (Si es necesario)

Para revertir cambios en `personal_access_tokens`:

```bash
php artisan migrate:rollback --step=1
```

Esto ejecutará el método `down()` de la migración y restaurará `tokenable_id` a `bigint unsigned`.

---

**Documentado por:** Claude Code AI Assistant
**Fecha:** 18 de Diciembre 2025
**Versión:** 1.0
