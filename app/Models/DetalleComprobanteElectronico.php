<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleComprobanteElectronico extends Model
{
    protected $table = 'comprobante_electronico_detalles'; // ✅ Tabla correcta

    protected $fillable = [
        'comprobante_electronico_id', // ✅ Nombre correcto de la columna
        'item',
        'codigo_producto',
        'codigo_producto_sunat',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'valor_unitario',
        'precio_unitario',
        'valor_venta',
        'precio_venta',
        'descuento',
        'cargo',
        'tipo_afectacion_igv',
        'porcentaje_igv',
        'igv',
        'isc',
        'otros_tributos',
        'total_impuestos',
        'informacion_adicional',
        'es_bonificacion',
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
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_electronico_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function unidadDerivada(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class, 'unidad_derivada_id');
    }

    // Helper methods
    public function getMontoTotalAttribute(): float
    {
        return $this->valor_venta + $this->igv;
    }
}
