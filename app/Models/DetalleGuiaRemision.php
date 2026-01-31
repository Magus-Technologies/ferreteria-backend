<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleGuiaRemision extends Model
{
    protected $table = 'detalle_guia_remision';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'guia_remision_id',
        'producto_id',
        'producto_almacen_id',
        'unidad_derivada_inmutable_id',
        'unidad_derivada_inmutable_name',
        'factor',
        'cantidad',
        'peso_total',
        'unidad_derivada_venta_id',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'peso_total' => 'decimal:3',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ============================================
    // RELACIONES
    // ============================================

    /**
     * Guía de remisión a la que pertenece
     */
    public function guiaRemision(): BelongsTo
    {
        return $this->belongsTo(GuiaRemision::class);
    }

    /**
     * Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Producto en almacén específico
     */
    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class);
    }

    /**
     * Unidad derivada inmutable
     */
    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }

    /**
     * Unidad derivada de venta (si la guía viene de una venta)
     */
    public function unidadDerivadaVenta(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_venta_id');
    }

    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================

    /**
     * Calcular la cantidad en unidad base
     */
    public function getCantidadBaseAttribute(): float
    {
        return (float) $this->cantidad * (float) $this->factor;
    }

    /**
     * Verificar si el detalle está vinculado a una venta
     */
    public function tieneVenta(): bool
    {
        return !is_null($this->unidad_derivada_venta_id);
    }
}
