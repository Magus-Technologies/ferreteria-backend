<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distrito extends Model
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
     * Scope para obtener solo distritos
     */
    public function scopeDistritos($query)
    {
        return $query->where('distrito', '!=', '00')
                    ->orderBy('nombre', 'asc');
    }

    /**
     * Scope para filtrar por provincia
     */
    public function scopePorProvincia($query, $codigoDepartamento, $codigoProvincia)
    {
        return $query->where('departamento', $codigoDepartamento)
                    ->where('provincia', $codigoProvincia);
    }

    /**
     * Relación con provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia', 'provincia')
                    ->where('departamento', $this->departamento);
    }

    /**
     * Relación con departamento
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento', 'departamento');
    }
}
