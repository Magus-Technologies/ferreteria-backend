<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SolicitudAutorizacion extends Model
{
    protected $table = 'solicitudes_autorizacion';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'solicitante_id',
        'autorizador_id',
        'role_id',
        'modulo',
        'accion',
        'descripcion',
        'metadata',
        'estado',
        'tipo_aprobacion',
        'duracion_horas',
        'respondido_por',
        'respondido_at',
        'comentario_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'duracion_horas' => 'integer',
            'respondido_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    // Relaciones

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizador_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }

    public function autorizacionOtorgada(): HasOne
    {
        return $this->hasOne(AutorizacionOtorgada::class, 'solicitud_id');
    }

    // Scopes

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeDelAutorizador($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('autorizador_id', $userId)
              ->orWhereNull('autorizador_id');
        });
    }
}
