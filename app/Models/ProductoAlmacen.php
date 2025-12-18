<?php

namespace App\Models;

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
        'ubicacion_id',
    ];

    protected function casts(): array
    {
        return [
            'stock_fraccion' => 'decimal:3',
            'costo' => 'decimal:4',
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

    public function unidadesDerivadas(): HasMany
    {
        return $this->hasMany(ProductoAlmacenUnidadDerivada::class);
    }
}
