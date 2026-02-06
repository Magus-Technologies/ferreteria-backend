<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleComprobanteElectronico extends Model
{
    protected $table = 'detalles_comprobante_electronico';

    protected $fillable = [
        'comprobante_id',
        'item',
        'codigo_producto',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'valor_unitario',
        'precio_unitario',
        'valor_venta',
        'igv',
        'tipo_afectacion_igv',
        'total_impuestos',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'valor_unitario' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'valor_venta' => 'decimal:2',
        'igv' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
    ];

    // Relationships
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id');
    }

    // Helper methods
    public function getMontoTotalAttribute(): float
    {
        return $this->valor_venta + $this->igv;
    }
}
