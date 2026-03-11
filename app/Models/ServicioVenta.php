<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioVenta extends Model
{
    protected $table = 'servicio_venta';

    protected $fillable = [
        'venta_id',
        'servicio_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'referencia',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
