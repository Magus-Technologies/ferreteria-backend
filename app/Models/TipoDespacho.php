<?php

namespace App\Models;

use App\Enums\CodigoTipoDespacho;
use Illuminate\Database\Eloquent\Model;

class TipoDespacho extends Model
{
    protected $table = 'tipo_despacho';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    protected $casts = [
        'codigo' => CodigoTipoDespacho::class,
    ];
}
