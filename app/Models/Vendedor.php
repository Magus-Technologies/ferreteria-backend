<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendedor extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'vendedor';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'dni',
        'nombres',
        'direccion',
        'telefono',
        'email',
        'estado',
        'cumple',
        'proveedor_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'cumple' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a un proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
