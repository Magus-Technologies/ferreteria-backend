<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    protected $table = 'almacen';

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function productosEnAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacen::class);
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function ingresosSalidas(): HasMany
    {
        return $this->hasMany(IngresoSalida::class);
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function entregasProductos(): HasMany
    {
        return $this->hasMany(EntregaProducto::class);
    }

    public function seriesDocumentos(): HasMany
    {
        return $this->hasMany(SerieDocumento::class);
    }
}
