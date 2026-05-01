<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DireccionEmpresa extends Model
{
    protected $table = 'direccion_empresa';

    protected $fillable = [
        'empresa_id',
        'alias',
        'direccion',
        'ubigeo_id',
        'departamento',
        'provincia',
        'distrito',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
