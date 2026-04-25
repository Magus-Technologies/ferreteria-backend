<?php

namespace App\Models;

use App\Enums\EstadoProveedorCalificacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorCalificacion extends Model
{
    protected $table = 'proveedor_calificaciones';

    protected $fillable = [
        'proveedor_id',
        'estado',
        'razon',
        'observacion',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoProveedorCalificacion::class,
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
