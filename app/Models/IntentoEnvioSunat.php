<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentoEnvioSunat extends Model
{
    protected $table = 'intentos_envio_sunat';
    
    // ✅ Disable timestamps since the table doesn't have created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'comprobante_id', // ✅ Fixed: was comprobante_electronico_id
        'numero_intento',
        'fecha_intento',
        'resultado',
        'codigo_respuesta',
        'mensaje_respuesta',
        'ticket_numero',
    ];

    protected $casts = [
        'fecha_intento' => 'datetime',
    ];

    // Relationships
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id'); // ✅ Fixed column name
    }

    // Scopes
    public function scopeExitosos($query)
    {
        return $query->where('resultado', 'exitoso');
    }

    public function scopeFallidos($query)
    {
        return $query->where('resultado', 'fallido');
    }

    // Helper methods
    public function fueExitoso(): bool
    {
        return $this->resultado === 'exitoso';
    }

    public function fallo(): bool
    {
        return $this->resultado === 'fallido';
    }
}
