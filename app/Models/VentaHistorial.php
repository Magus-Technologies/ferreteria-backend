<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaHistorial extends Model
{
    protected $table = 'venta_historial';

    const CREATED_AT = 'fecha';
    const UPDATED_AT = null;

    protected $fillable = [
        'venta_id',
        'accion',
        'descripcion',
        'datos_anteriores',
        'datos_nuevos',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePorAccion($query, string $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    public static function registrar(
        string $ventaId,
        string $accion,
        ?string $descripcion = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
        ?string $userId = null
    ): self {
        return self::create([
            'venta_id' => $ventaId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
