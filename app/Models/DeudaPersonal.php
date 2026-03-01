<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeudaPersonal extends Model
{
    protected $fillable = [
        'user_id',
        'arqueo_diario_id',
        'monto',
        'monto_original',
        'monto_abonado',
        'saldo_pendiente',
        'estado',
        'observaciones',
    ];
    
    protected $casts = [
        'monto' => 'decimal:2',
        'monto_original' => 'decimal:2',
        'monto_abonado' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];
    
    protected $appends = ['porcentaje_pagado', 'esta_pagada'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function arqueoDiario(): BelongsTo
    {
        return $this->belongsTo(ArqueoDiario::class);
    }
    
    public function abonos(): HasMany
    {
        return $this->hasMany(AbonoDeudaPersonal::class)->orderBy('fecha_abono', 'desc');
    }
    
    public function getPorcentajePagadoAttribute(): float
    {
        if ($this->monto_original == 0) return 0;
        return round(($this->monto_abonado / $this->monto_original) * 100, 2);
    }
    
    public function getEstaPagadaAttribute(): bool
    {
        return $this->saldo_pendiente <= 0;
    }
}
