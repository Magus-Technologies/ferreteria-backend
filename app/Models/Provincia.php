<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
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
     * Scope para obtener solo provincias
     */
    public function scopeProvincias($query)
    {
        return $query->where('distrito', '00')
                    ->where('provincia', '!=', '00')
                    ->orderBy('nombre', 'asc');
    }

    /**
     * Scope para filtrar por departamento
     */
    public function scopePorDepartamento($query, $codigoDepartamento)
    {
        return $query->where('departamento', $codigoDepartamento);
    }

    /**
     * Relación con departamento
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento', 'departamento');
    }

    /**
     * Relación con distritos
     */
    public function distritos(): HasMany
    {
        return $this->hasMany(Distrito::class, 'provincia', 'provincia')
                    ->where('departamento', $this->departamento)
                    ->where('distrito', '!=', '00');
    }
}
