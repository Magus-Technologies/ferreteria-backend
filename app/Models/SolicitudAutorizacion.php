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
        'cargo_autorizador',
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

    /**
     * Solicitudes que el usuario indicado puede autorizar.
     *
     * Una solicitud es visible para U si:
     *  - U es el autorizador específico (autorizador_id == U.id), o
     *  - la solicitud se dirigió a un cargo y U ocupa ese cargo
     *    (cargo_autorizador == U.cargo), o
     *  - no tiene ni usuario ni cargo destino (fallback) y U es ADMINISTRADOR.
     * En todos los casos se excluye al propio solicitante.
     */
    public function scopeDelAutorizador($query, string $userId)
    {
        $user = User::find($userId);
        $cargo = $user?->cargo;
        $esAdmin = $user && strtoupper((string) $user->rol_sistema) === 'ADMINISTRADOR';

        return $query
            ->where('solicitante_id', '!=', $userId)
            ->where(function ($q) use ($userId, $cargo, $esAdmin) {
                $q->where('autorizador_id', $userId);

                if (!empty($cargo)) {
                    $q->orWhere('cargo_autorizador', $cargo);
                }

                if ($esAdmin) {
                    $q->orWhere(function ($qq) {
                        $qq->whereNull('autorizador_id')
                           ->whereNull('cargo_autorizador');
                    });
                }
            });
    }
}
