<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoInternoProducto extends Model
{
    protected $table = 'requerimiento_interno_productos';
    public $timestamps = false;

    protected $fillable = [
        'requerimiento_id',
        'producto_id',
        'nombre_adicional',
        'cantidad',
        'cantidad_pendiente',
        'cantidad_ordenada',
        'orden_compra_codigo',
        'orden_compra_id',
        'unidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_pendiente' => 'decimal:3',
            'cantidad_ordenada' => 'decimal:3',
        ];
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function getCantidadRestanteAttribute(): float
    {
        $cantidadPendiente = (float) ($this->cantidad_pendiente ?? $this->cantidad ?? 0);
        $cantidadOrdenada = (float) ($this->cantidad_ordenada ?? 0);
        return $cantidadPendiente - $cantidadOrdenada;
    }

    public function getEstadoProductoAttribute(): string
    {
        $restante = $this->cantidad_restante;
        if ($restante <= 0) {
            return 'completo';
        } elseif ($this->cantidad_ordenada > 0) {
            return 'parcial';
        }
        return 'pendiente';
    }
}
