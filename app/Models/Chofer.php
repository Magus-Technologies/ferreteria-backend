<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chofer extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'chofer';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'dni',
        'name',
        'licencia',
        'proveedor_id',
    ];

    /**
     * Relación: Pertenece a un proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
