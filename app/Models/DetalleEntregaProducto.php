<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleEntregaProducto extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'detalleentregaproducto';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'entrega_producto_id',
        'unidad_derivada_venta_id',
        'cantidad_entregada',
        'ubicacion',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'cantidad_entregada' => 'decimal:3',
        ];
    }

    /**
     * Relación: Pertenece a una entrega de producto
     */
    public function entregaProducto(): BelongsTo
    {
        return $this->belongsTo(EntregaProducto::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada de venta
     */
    public function unidadDerivadaVenta(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_venta_id');
    }
}
