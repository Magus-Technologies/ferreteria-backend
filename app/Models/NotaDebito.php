<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class NotaDebito extends Model
{
    protected $table = 'nota_debito';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tipo_documento',
        'serie',
        'numero',
        'venta_id',
        'comprobante_id_referencia',
        'motivo_id',
        'descripcion',
        'monto_total',
        'monto_igv',
        'monto_subtotal',
        'referencia_documento',
        'fecha',
        'estado',
        'usuario_id',
        'almacen_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto_total' => 'decimal:2',
        'monto_igv' => 'decimal:2',
        'monto_subtotal' => 'decimal:2',
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
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoNota::class, 'motivo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    /**
     * Obtener el comprobante electrónico asociado a esta nota de débito
     */
    public function comprobanteElectronico(): HasOne
    {
        return $this->hasOne(ComprobanteElectronico::class, 'serie', 'serie')
            ->where('tipo_comprobante', '08')
            ->where('correlativo', $this->numero);
    }

    /**
     * Accessor para obtener el comprobante electrónico de forma dinámica
     */
    public function getComprobanteElectronicoAttribute()
    {
        if (!isset($this->attributes['serie']) || !isset($this->attributes['numero'])) {
            return null;
        }

        return ComprobanteElectronico::with('detalles')
            ->where('serie', $this->attributes['serie'])
            ->where('correlativo', $this->attributes['numero'])
            ->where('tipo_comprobante', '08')
            ->first();
    }

    public function comprobanteReferencia(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id_referencia');
    }

    // Scopes
    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorAlmacen($query, int $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    public function scopePorUsuario($query, string $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    // Helper methods
    public function getNumeroCompletoAttribute(): string
    {
        return "{$this->serie}-{$this->numero}";
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function estaEnviado(): bool
    {
        return $this->estado === 'enviado';
    }

    public function estaAceptado(): bool
    {
        return $this->estado === 'aceptado';
    }

    public function estaRechazado(): bool
    {
        return $this->estado === 'rechazado';
    }

    public function estaCancelado(): bool
    {
        return $this->estado === 'cancelado';
    }

    public function puedeEditarse(): bool
    {
        return in_array($this->estado, ['borrador', 'pendiente']);
    }

    public function puedeEnviarse(): bool
    {
        return in_array($this->estado, ['borrador', 'pendiente', 'rechazado']);
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, ['borrador', 'pendiente']);
    }
}
