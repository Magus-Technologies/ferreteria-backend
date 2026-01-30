<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespliegueDePago extends Model
{
    protected $table = 'desplieguedepago';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'adicional',
        'mostrar',
        'metodo_de_pago_id',
        'requiere_numero_serie',
        'sobrecargo_porcentaje',
        'tipo_sobrecargo',
        'numero_celular',
        'activo',
    ];

    protected $casts = [
        'adicional' => 'decimal:2',
        'mostrar' => 'boolean',
        'requiere_numero_serie' => 'boolean',
        'sobrecargo_porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function metodoDePago(): BelongsTo
    {
        return $this->belongsTo(MetodoDePago::class, 'metodo_de_pago_id');
    }
}
