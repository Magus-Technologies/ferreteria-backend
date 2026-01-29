<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadDerivada extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivada';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'name',
        'estado',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    /**
     * Relación: Tiene muchas configuraciones de productos
     */
    public function productosAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenUnidadDerivada::class, 'unidad_derivada_id');
    }
}
