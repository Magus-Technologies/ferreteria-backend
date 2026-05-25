<?php

namespace App\Models;

use App\Enums\CodigoQuienEntrega;
use Illuminate\Database\Eloquent\Model;

class QuienEntrega extends Model
{
    protected $table = 'quien_entrega';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => CodigoQuienEntrega::class,
    ];
}
