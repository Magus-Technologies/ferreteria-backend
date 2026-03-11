<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AutorizacionOtorgada extends Model
{
    protected $table = 'autorizaciones_otorgadas';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'modulo',
        'accion',
        'tipo',
        'fecha_expiracion',
        'otorgada_por',
        'solicitud_id',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'fecha_expiracion' => 'datetime',
            'activa' => 'boolean',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function otorgadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgada_por');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudAutorizacion::class, 'solicitud_id');
    }

    // Scopes

    public function scopeActivas($query)
    {
        return $query->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_expiracion')
                  ->orWhere('fecha_expiracion', '>', now());
            });
    }

    // Helpers

    public function estaVigente(): bool
    {
        if (!$this->activa) {
            return false;
        }

        if ($this->tipo === 'permanente') {
            return true;
        }

        return $this->fecha_expiracion && $this->fecha_expiracion->isFuture();
    }
}
