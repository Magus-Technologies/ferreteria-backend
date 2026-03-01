<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicio';

    protected $fillable = [
        'nombre',
        'precio',
        'codigo_sunat',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function serviciosVenta(): HasMany
    {
        return $this->hasMany(ServicioVenta::class);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('nombre', 'like', "%{$search}%");
        }
        return $query;
    }
}
