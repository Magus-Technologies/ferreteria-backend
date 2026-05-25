<?php

namespace App\Models;

use App\Enums\CodigoEstadoEntrega;
use Illuminate\Database\Eloquent\Model;

class EstadoEntrega extends Model
{
    protected $table = 'estado_entrega';

    protected $fillable = [
        'codigo',
        'nombre',
        'color',
        'orden',
        'es_final',
    ];

    protected $casts = [
        'es_final' => 'boolean',
        'codigo'   => CodigoEstadoEntrega::class,
    ];

    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden');
    }
}
