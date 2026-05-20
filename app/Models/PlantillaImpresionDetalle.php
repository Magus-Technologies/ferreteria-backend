<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaImpresionDetalle extends Model
{
    protected $table = 'plantilla_impresion_detalles';

    protected $fillable = [
        'empresa_id',
        'comprobante',
        'formato',
        'estilos',
        'mensajes_extra',
        'estilos_secciones',
    ];

    protected $casts = [
        'estilos' => 'array',
        'mensajes_extra' => 'array',
        'estilos_secciones' => 'array',
    ];
}
