<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoCargo extends Model
{
    protected $table = 'catalogo_cargos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'parent',
        'highlight',
        'staff',
        'estado',
    ];

    protected $casts = [
        'highlight' => 'boolean',
        'staff' => 'boolean',
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('descripcion', 'asc');
    }
}
