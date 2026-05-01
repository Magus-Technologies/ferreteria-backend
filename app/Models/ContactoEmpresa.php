<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactoEmpresa extends Model
{
    protected $table = 'contacto_empresa';

    protected $fillable = [
        'empresa_id',
        'cargo',
        'nombre',
        'email',
        'celular',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
