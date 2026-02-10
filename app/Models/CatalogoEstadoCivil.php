<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoEstadoCivil extends Model
{
    protected $table = 'catalogo_estado_civil';
    
    protected $fillable = [
        'codigo',
        'descripcion',
        'orden',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Scope para obtener solo estados civiles activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para ordenar por campo orden
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden', 'asc');
    }
}
