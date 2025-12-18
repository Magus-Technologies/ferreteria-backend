<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCaja extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'subcaja';

    /**
     * Clave primaria es string (CUID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Relación: Tiene muchos métodos de pago
     */
    public function metodosDePago(): HasMany
    {
        return $this->hasMany(MetodoDePago::class, 'subcaja_id');
    }
}
