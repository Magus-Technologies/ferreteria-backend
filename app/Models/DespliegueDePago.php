<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespliegueDePago extends Model
{
    protected $table = 'DespliegueDePago';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'adicional',
        'mostrar',
        'metodo_de_pago_id',
    ];

    protected $casts = [
        'adicional' => 'decimal:2',
        'mostrar' => 'boolean',
    ];

    public function metodoDePago(): BelongsTo
    {
        return $this->belongsTo(MetodoDePago::class, 'metodo_de_pago_id');
    }
}
