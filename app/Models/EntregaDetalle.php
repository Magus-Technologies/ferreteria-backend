<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaDetalle extends Model
{
    protected $table = 'entrega_detalle';

    protected $fillable = [
        'entrega_id',
        'unidad_derivada_venta_id',
        'cantidad',
        'ubicacion',
        'detalle_legacy_id',
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
}
