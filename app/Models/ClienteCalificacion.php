<?php

namespace App\Models;

use App\Enums\EstadoClienteCalificacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteCalificacion extends Model
{
    protected $table = 'cliente_calificaciones';

    protected $fillable = [
        'cliente_id',
        'estado',
        'razon',
        'observacion',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoClienteCalificacion::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
