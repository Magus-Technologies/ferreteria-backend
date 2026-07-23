<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Concepto de movimiento interno: etiqueta de solo nombre (ej. "EFECTIVO A
 * YAPE") que describe el movimiento entre sub-cajas. No es un método de pago.
 */
class ConceptoMovimientoInterno extends Model
{
    protected $table = 'conceptos_movimiento_interno';

    protected $fillable = [
        'nombre',
    ];
}
