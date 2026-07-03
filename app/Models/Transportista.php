<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transportista extends Model
{
    /**
     * Tabla asociada al modelo
     */
    protected $table = 'transportista';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'ruc',
        'razon_social',
        'nro_mtc',
        'estado',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'estado' => 'boolean',
    ];
}
