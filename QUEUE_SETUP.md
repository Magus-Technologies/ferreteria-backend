# Configuración de Colas (Queue) para Laravel

## ⚠️ IMPORTANTE: Problema con Importación de Productos

Si la importación de productos **"mata el servidor"** o queda colgada, es porque **NO HAY UN WORKER DE COLAS CORRIENDO**.

## 🔧 Solución Rápida

### Opción 1: Usar Cola Síncrona (Desarrollo)

En tu archivo `.env`, cambia:

```env
QUEUE_CONNECTION=sync
```

Con esto, los jobs se ejecutarán de forma **síncrona** (inmediata) sin necesidad de workers.

**Ventajas:**
- ✅ No requiere workers corriendo
- ✅ Simple para desarrollo
- ✅ Funciona inmediatamente

**Desventajas:**
- ❌ Bloquea la petición HTTP hasta que termine
- ❌ No hay procesamiento en segundo plano
- ❌ Timeout si la importación es muy grande (>500 productos)

---

### Opción 2: Usar Cola de Base de Datos (Recomendado)

1. **Configurar `.env`:**

```env
QUEUE_CONNECTION=database
```

2. **Crear las tablas de jobs (si no existen):**

```bash
php artisan queue:table
php artisan migrate
```

3. **Iniciar el worker:**

```bash
php artisan queue:work --queue=default --tries=3 --timeout=1800
```

**Ventajas:**
- ✅ Procesamiento en segundo plano
- ✅ No bloquea peticiones HTTP
- ✅ Puede manejar importaciones grandes
- ✅ Reintentos automáticos en caso de fallo

**Desventajas:**
- ⚠️ Requiere mantener un worker corriendo
- ⚠️ Más complejo de configurar

---

### Opción 3: Usar Redis (Producción)

1. **Instalar Redis:**

```bash
# Windows (con Laragon)
# Redis viene preinstalado, solo actívalo desde el panel

# Linux
sudo apt-get install redis-server
```

2. **Instalar Predis:**

```bash
composer require predis/predis
```

3. **Configurar `.env`:**

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

4. **Iniciar el worker:**

```bash
php artisan queue:work redis --queue=default --tries=3 --timeout=1800
```

**Ventajas:**
- ✅ Más rápido que database
- ✅ Mejor para producción
- ✅ Soporta múltiples workers

**Desventajas:**
- ⚠️ Requiere Redis instalado
- ⚠️ Más complejo de configurar

---

## 🚀 Workers en Producción

### Supervisor (Linux)

Para mantener el worker corriendo automáticamente en producción:

1. **Instalar Supervisor:**

```bash
sudo apt-get install supervisor
```

2. **Crear configuración:**

```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

3. **Contenido del archivo:**

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
stopwaitsecs=3600
```

4. **Reiniciar Supervisor:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 🧪 Verificar que el Worker Está Corriendo

```bash
# Ver jobs pendientes
php artisan queue:monitor

# Ver trabajos fallidos
php artisan queue:failed

# Reintentar trabajos fallidos
php artisan queue:retry all
```

---

## 📊 Monitoreo con Laravel Horizon (Opcional)

Si usas Redis, puedes usar Horizon para monitorear:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Accede a: `http://localhost/horizon`

---

## 🔄 Cambios Recientes en el Código

- ✅ Cambiada cola de `imports` a `default` para evitar problemas
- ✅ Aumentado tamaño de batch de 25 a 100 productos (más rápido)
- ✅ Reducido delay entre batches de 100ms a 10ms (10x más rápido)
- ✅ Timeout aumentado a 30 minutos (1800 segundos)

---

## 💡 Tips

1. **Desarrollo:** Usa `QUEUE_CONNECTION=sync`
2. **Producción pequeña:** Usa `QUEUE_CONNECTION=database`
3. **Producción grande:** Usa `QUEUE_CONNECTION=redis` con Supervisor
4. **Nunca** uses `sync` en producción con importaciones grandes
5. **Siempre** monitorea los logs: `storage/logs/laravel.log`

---

## 🐛 Troubleshooting

### El servidor se cuelga al importar
- ✅ Usa `QUEUE_CONNECTION=sync` o inicia un worker

### El job nunca se procesa
- ✅ Verifica que el worker esté corriendo: `ps aux | grep queue`
- ✅ Revisa los logs: `tail -f storage/logs/laravel.log`

### Timeout después de 30 segundos
- ✅ Aumenta `max_execution_time` en `php.ini`
- ✅ Usa workers en lugar de `sync`

### Memory limit exceeded
- ✅ Aumenta `memory_limit` en `php.ini` a 512M o 1G
- ✅ Reduce el tamaño de batch en `ImportProductosJob.php`
