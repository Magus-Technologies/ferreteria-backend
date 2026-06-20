<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEntrega extends Model
{
    protected $table = 'configuracion_entrega';

    protected $fillable = ['clave', 'valor'];

    protected $casts = ['valor' => 'array'];
}
