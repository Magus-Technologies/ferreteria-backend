<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentoEnvioSunat extends Model
{
    protected $table = 'intentos_envio_sunat';

    protected $fillable = [
        'comprobante_id',
        'fecha_intento',
        'exitoso',
        'codigo_respuesta',
        'mensaje_respuesta',
        'detalle_error',
        'modo_envio',
    ];

    protected $casts = [
        'fecha_intento' => 'datetime',
        'exitoso' => 'boolean',
    ];

    // Relationships
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id');
    }

    // Scopes
    public function scopeExitosos($query)
    {
        return $query->where('exitoso', true);
    }

    public function scopeFallidos($query)
    {
        return $query->where('exitoso', false);
    }

    public function scopePorModoEnvio($query, string $modo)
    {
        return $query->where('modo_envio', $modo);
    }

    // Helper methods
    public function fueExitoso(): bool
    {
        return $this->exitoso === true;
    }

    public function fallo(): bool
    {
        return $this->exitoso === false;
    }
}
