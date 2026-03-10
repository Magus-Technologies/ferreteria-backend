<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraProducto extends Model
{
    protected $table = 'orden_compra_productos';
    public $timestamps = false;

    protected $fillable = [
        'orden_compra_id',
        'producto_id',
        'requerimiento_interno_producto_id',
        'codigo',
        'nombre',
        'marca',
        'unidad',
        'cantidad',
        'cantidad_pendiente',
        'precio',
        'subtotal',
        'flete',
        'vencimiento',
        'lote',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_pendiente' => 'decimal:3',
            'precio' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'flete' => 'decimal:2',
            'vencimiento' => 'date',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function requerimientoInternoProducto(): BelongsTo
    {
        return $this->belongsTo(RequerimientoInternoProducto::class, 'requerimiento_interno_producto_id');
    }
}
