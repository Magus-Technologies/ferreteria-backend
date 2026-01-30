<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoTraslado extends Model
{
    protected $table = 'motivos_traslado';

    protected $fillable = [
        'codigo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Scope para obtener solo motivos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para buscar por código o descripción
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('codigo', 'like', "%{$search}%")
              ->orWhere('descripcion', 'like', "%{$search}%");
        });
    }
}
