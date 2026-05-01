<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminoEmpresa extends Model
{
    protected $table = 'termino_empresa';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'contenido',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
