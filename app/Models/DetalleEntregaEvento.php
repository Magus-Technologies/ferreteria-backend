<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DetalleEntregaEvento — cuánto de cada línea solicitada se despachó en
 * ESTE evento.
 *
 * Apunta a `detalleentregaproducto` (la línea "solicitada" en la orden
 * lógica) y a `entregaevento` (el despacho físico). La cantidad real
 * entregada de una línea = SUM de detalles eventos con estado='en'.
 */
class DetalleEntregaEvento extends Model
{
    protected $table = 'detalleentregaevento';

    public $timestamps = false;

    protected $fillable = [
        'entrega_evento_id',
        'detalle_entrega_producto_id',
        'cantidad',
        'ubicacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    public function entregaEvento(): BelongsTo
    {
        return $this->belongsTo(EntregaEvento::class);
    }

    public function detalleEntregaProducto(): BelongsTo
    {
        return $this->belongsTo(DetalleEntregaProducto::class, 'detalle_entrega_producto_id');
    }
}
