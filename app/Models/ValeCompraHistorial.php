<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeCompraHistorial extends Model
{
    protected $table = 'vales_compra_historial';

    const CREATED_AT = 'fecha';
    const UPDATED_AT = null; // No tiene updated_at

    protected $fillable = [
        'vale_compra_id',
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

    // Relaciones

    public function valeCompra(): BelongsTo
    {
        return $this->belongsTo(ValeCompra::class, 'vale_compra_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes

    public function scopePorAccion($query, string $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    // Método estático para crear registro de historial

    public static function registrar(
        int $valeCompraId,
        string $accion,
        ?string $descripcion = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
        ?string $userId = null
    ): self {
        return self::create([
            'vale_compra_id' => $valeCompraId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
