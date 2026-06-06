<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Producto extends Model
{
    protected $table = 'producto';

    // Prisma usa camelCase para timestamps
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Invalida la cache del listado ligero cuando se crea/edita/elimina un Producto.
     *
     * El observer de `ProductoAlmacen` ya invalida cuando cambia `stock_fraccion`
     * (el dato de stock está en `producto_en_almacenes`, no en `producto`).
     * PERO si editan el `Producto` directamente (nombre, precio, marca, cod_producto,
     * cod_barra, etc.), el observer de `ProductoAlmacen` NO dispara y el modal
     * muestra datos viejos hasta que se toque el stock.
     *
     * Este observer cubre ese gap: cualquier cambio en `Producto` invalida
     * TODAS las caches de listado ligero de TODOS los almacenes (el producto
     * puede aparecer en varios almacenes).
     */
    protected static function booted(): void
    {
        $invalidate = function (Producto $producto) {
            $almacenIds = $producto->productoEnAlmacenes()->pluck('almacen_id')->all();
            foreach ($almacenIds as $almacenId) {
                Cache::forget("productos_listado_ligero_{$almacenId}");
                Cache::forget("productos_listado_completo_{$almacenId}");
            }
        };

        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'cod_producto',
        'cod_barra',
        'name',
        'name_ticket',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'accion_tecnica',
        'img',
        'ficha_tecnica',
        'stock_min',
        'stock_max',
        'unidades_contenidas',
        'estado',
        'permitido',
    ];

    protected function casts(): array
    {
        return [
            'stock_min' => 'decimal:3',
            'stock_max' => 'integer',
            'unidades_contenidas' => 'decimal:3',
            'estado' => 'boolean',
            'permitido' => 'boolean',
            'tiene_ingresos' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function productoEnAlmacenes(): HasMany
    {
        return $this->hasMany(ProductoAlmacen::class);
    }
}
