<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaDetalle extends Model
{
    protected $table = 'entrega_detalle';

    protected $fillable = [
        'entrega_id',
        'unidad_derivada_venta_id',
        'cantidad',
        'ubicacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
    ];

    // ─── Relaciones ─────────────────────────────────────────────

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function unidadDerivadaVenta(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_venta_id');
    }

    /** Despachos físicos (eventos) de esta línea de entrega. */
    public function detallesEvento(): HasMany
    {
        return $this->hasMany(DetalleEntregaEvento::class, 'entrega_detalle_id');
    }

    /**
     * Cantidad realmente entregada = suma de los detalles de eventos cuyo
     * evento está en estado 'en' (entregado). Modela la entrega PARCIAL:
     * "se pidió 10, se despacharon 4 el lunes y 3 el miércoles" → 7.
     */
    protected function cantidadEntregada(): Attribute
    {
        return Attribute::make(get: fn () => (float) DetalleEntregaEvento::query()
            ->where('entrega_detalle_id', $this->id)
            ->whereHas('entregaEvento', fn ($q) => $q->where('estado', 'en'))
            ->sum('cantidad'));
    }
}
