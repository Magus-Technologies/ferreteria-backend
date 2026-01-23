<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistribucionEfectivoVendedor extends Model
{
    protected $table = 'distribucion_efectivo_vendedores';

    protected $fillable = [
        'apertura_cierre_caja_id',
        'user_id',
        'monto',
        'conteo_billetes_monedas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'conteo_billetes_monedas' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con apertura_cierre_caja
     */
    public function aperturaCierreCaja(): BelongsTo
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_caja_id');
    }

    /**
     * Relación con user (vendedor)
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
