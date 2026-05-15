<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoDevolucion extends Model
{
    protected $table = 'prestamo_devolucion';
    public $timestamps = false;

    protected $fillable = [
        'prestamo_id',
        'ingreso_salida_id',
        'fecha_devolucion',
        'user_id',
        'numero_devolucion',
        'observaciones',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_devolucion' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id', 'id');
    }

    public function ingresoSalida(): BelongsTo
    {
        return $this->belongsTo(IngresoSalida::class, 'ingreso_salida_id');
    }

    public function productosDevueltos(): HasMany
    {
        return $this->hasMany(PrestamoProductoDevuelto::class, 'prestamo_devolucion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}