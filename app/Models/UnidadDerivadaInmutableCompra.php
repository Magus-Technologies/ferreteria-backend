<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadDerivadaInmutableCompra extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutablecompra';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_id',
        'producto_almacen_compra_id',
        'factor',
        'cantidad',
        'cantidad_pendiente',
        'lote',
        'vencimiento',
        'flete',
        'bonificacion',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'cantidad_pendiente' => 'decimal:3',
            'vencimiento' => 'datetime',
            'flete' => 'decimal:4',
            'bonificacion' => 'boolean',
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén compra
     */
    public function productoAlmacenCompra(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenCompra::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable
     */
    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }
}
