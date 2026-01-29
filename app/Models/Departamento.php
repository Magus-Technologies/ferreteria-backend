<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $table = 'ubigeo_inei';
    public $timestamps = false;
    protected $primaryKey = 'id_ubigeo';

    protected $fillable = [
        'departamento',
        'provincia',
        'distrito',
        'nombre',
    ];

    /**
     * Scope para obtener solo departamentos
     */
    public function scopeDepartamentos($query)
    {
        return $query->where('provincia', '00')
                    ->where('distrito', '00')
                    ->orderBy('nombre', 'asc');
    }

    /**
     * Relación con provincias
     */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'departamento', 'departamento')
                    ->where('distrito', '00')
                    ->where('provincia', '!=', '00');
    }
}
