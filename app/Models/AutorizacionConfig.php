<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutorizacionConfig extends Model
{
    protected $table = 'autorizaciones_config';

    protected $fillable = [
        'role_id',
        'modulo',
        'accion',
        'requiere_autorizacion',
        'autorizador_id',
        'tipo_autorizador',
        'cargo_autorizador',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requiere_autorizacion' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizador_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
