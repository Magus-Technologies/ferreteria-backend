<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAlmacenUnidadDerivada extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmacenunidadderivada';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'producto_almacen_id',
        'unidad_derivada_id',
        'factor',
        'precio_publico',
        'comision_publico',
        'precio_especial',
        'comision_especial',
        'activador_especial',
        'precio_minimo',
        'comision_minimo',
        'activador_minimo',
        'precio_ultimo',
        'comision_ultimo',
        'activador_ultimo',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'precio_publico' => 'decimal:3',
            'comision_publico' => 'decimal:3',
            'precio_especial' => 'decimal:3',
            'comision_especial' => 'decimal:3',
            'activador_especial' => 'decimal:3',
            'precio_minimo' => 'decimal:3',
            'comision_minimo' => 'decimal:3',
            'activador_minimo' => 'decimal:3',
            'precio_ultimo' => 'decimal:3',
            'comision_ultimo' => 'decimal:3',
            'activador_ultimo' => 'decimal:3',
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén
     */
    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada
     */
    public function unidadDerivada(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivada::class);
    }
}
