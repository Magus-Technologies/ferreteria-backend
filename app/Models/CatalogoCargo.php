<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'role_id',
    ];

    protected $casts = [
        'highlight' => 'boolean',
        'staff' => 'boolean',
        'estado' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('descripcion', 'asc');
    }
}
