<?php

namespace App\Models;

use App\Services\Cache\ProductoCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacen extends Model
{
    protected $table = 'productoalmacen';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'stock_fraccion',
        'costo',
        'costo_anterior',
        'costo_actual',
        'costo_con_flete',
        'stock_costo_anterior',
        'stock_costo_actual',
        'ubicacion_id',
    ];

    protected static function booted(): void
    {
        static::updated(function (ProductoAlmacen $productoAlmacen) {
            if ($productoAlmacen->wasChanged('stock_fraccion')) {
                app(ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);
                // ⚡ Invalidar también el listado ligero del modal — el campo
                // `stock_fraccion` se incluye en el payload del listado
                // (vía productoEnAlmacenes.stock_fraccion), así que cualquier
                // cambio de stock debe invalidarlo. Sin esto, el modal muestra
                // stock viejo hasta 10 min después de una venta/compra.
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_ligero_{$productoAlmacen->almacen_id}"
                );
                \Illuminate\Support\Facades\Cache::forget(
                    "productos_listado_completo_{$productoAlmacen->almacen_id}"
                );
            }
        });

        // Nuevo producto asignado a un almacén: invalidar búsquedas cacheadas que no lo incluían
        static::created(function (ProductoAlmacen $productoAlmacen) {
            app(ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);
            \Illuminate\Support\Facades\Cache::forget(
                "productos_listado_ligero_{$productoAlmacen->almacen_id}"
            );
            \Illuminate\Support\Facades\Cache::forget(
                "productos_listado_completo_{$productoAlmacen->almacen_id}"
            );
        });

        // Producto removido del almacén: invalidar búsquedas que aún lo listaban
        static::deleted(function (ProductoAlmacen $productoAlmacen) {
            app(ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);
            \Illuminate\Support\Facades\Cache::forget(
                "productos_listado_ligero_{$productoAlmacen->almacen_id}"
            );
            \Illuminate\Support\Facades\Cache::forget(
                "productos_listado_completo_{$productoAlmacen->almacen_id}"
            );
        });
    }

    protected function casts(): array
    {
        return [
            'stock_fraccion' => 'decimal:3',
            'costo' => 'decimal:4',
            'costo_anterior' => 'decimal:4',
            'costo_actual' => 'decimal:4',
            'costo_con_flete' => 'decimal:4',
            'stock_costo_anterior' => 'decimal:3',
            'stock_costo_actual' => 'decimal:3',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(ProductoAlmacenCompra::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(ProductoAlmacenVenta::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(ProductoAlmacenCotizacion::class);
    }

    public function ingresosSalidas(): HasMany
    {
        return $this->hasMany(ProductoAlmacenIngresoSalida::class);
    }

    public function recepcionesAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenRecepcion::class);
    }

    /**
     * Lotes PEPS (capas de costo). Orden FIFO: secuencia asc (más viejo primero).
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(ProductoAlmacenLote::class, 'producto_almacen_id')
            ->orderBy('secuencia')
            ->orderBy('id');
    }

    public function unidadesDerivadas(): HasMany
    {
        return $this->hasMany(ProductoAlmacenUnidadDerivada::class);
    }
}
