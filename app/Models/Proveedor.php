<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    public $timestamps = false;

    protected $table = 'proveedor';

    protected $fillable = [
        'razon_social',
        'ruc',
        'direccion',
        'telefono',
        'email',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function vendedores(): HasMany
    {
        return $this->hasMany(Vendedor::class);
    }

    public function carros(): HasMany
    {
        return $this->hasMany(Carro::class);
    }

    public function choferes(): HasMany
    {
        return $this->hasMany(Chofer::class);
    }

    public function ingresosSalidas(): HasMany
    {
        return $this->hasMany(IngresoSalida::class);
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(ProveedorCalificacion::class, 'proveedor_id');
    }

    /**
     * Obtener la última calificación del proveedor
     */
    public function ultimaCalificacion()
    {
        return $this->hasOne(ProveedorCalificacion::class, 'proveedor_id')->latest();
    }
}
