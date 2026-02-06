<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ComprobanteElectronico extends Model
{
    protected $table = 'comprobantes_electronicos';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tipo_documento',
        'documento_id',
        'serie',
        'numero',
        'fecha_emision',
        'fecha_envio_sunat',
        'estado_sunat',
        'codigo_sunat',
        'mensaje_sunat',
        'xml_path',
        'cdr_path',
        'hash_cpe',
        'hash_cdr',
        'numero_ticket_sunat',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_envio_sunat' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function documento(): MorphTo
    {
        return $this->morphTo('documento', 'tipo_documento', 'documento_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleComprobanteElectronico::class, 'comprobante_id');
    }

    public function intentosEnvio(): HasMany
    {
        return $this->hasMany(IntentoEnvioSunat::class, 'comprobante_id')
            ->orderBy('created_at', 'desc');
    }

    // Scopes
    public function scopePorTipoDocumento($query, string $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }

    public function scopePorEstadoSunat($query, string $estado)
    {
        return $query->where('estado_sunat', $estado);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_emision', [$fechaInicio, $fechaFin]);
    }

    // Helper methods
    public function getNumeroCompletoAttribute(): string
    {
        return "{$this->serie}-{$this->numero}";
    }

    public function tieneXml(): bool
    {
        return !empty($this->xml_path) && file_exists(storage_path('app/' . $this->xml_path));
    }

    public function tieneCdr(): bool
    {
        return !empty($this->cdr_path) && file_exists(storage_path('app/' . $this->cdr_path));
    }

    public function estaAceptado(): bool
    {
        return $this->estado_sunat === 'aceptado';
    }

    public function estaRechazado(): bool
    {
        return $this->estado_sunat === 'rechazado';
    }

    public function estaPendiente(): bool
    {
        return $this->estado_sunat === 'pendiente';
    }

    public function fueEnviado(): bool
    {
        return !empty($this->fecha_envio_sunat);
    }
}
