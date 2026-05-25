<?php

namespace App\Models;

use App\Enums\CodigoTipoEntrega;
use Illuminate\Database\Eloquent\Model;

class TipoEntrega extends Model
{
    protected $table = 'tipo_entrega';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'icono',
        'color',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo'   => 'boolean',
        'es_final' => 'boolean',
        'codigo'   => CodigoTipoEntrega::class,
    ];

    public function scopeActivo($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
