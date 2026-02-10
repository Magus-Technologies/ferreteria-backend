<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeCompraAplicado extends Model
{
    protected $table = 'vales_compra_aplicados';

    const CREATED_AT = 'fecha_aplicacion';
    const UPDATED_AT = null; // No tiene updated_at

    protected $fillable = [
        'vale_compra_id',
        'venta_id',
        'cliente_id',
        'cantidad_productos',
        'descuento_aplicado',
        'descuento_tipo',
        'genera_vale_futuro',
        'codigo_vale_generado',
        'fecha_validez_generado',
        'usado',
        'fecha_uso',
        'aplicado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_productos' => 'decimal:3',
            'descuento_aplicado' => 'decimal:4',
            'genera_vale_futuro' => 'boolean',
            'fecha_validez_generado' => 'date',
            'usado' => 'boolean',
            'fecha_uso' => 'date',
            'fecha_aplicacion' => 'datetime',
        ];
    }

    // Relaciones

    public function valeCompra(): BelongsTo
    {
        return $this->belongsTo(ValeCompra::class, 'vale_compra_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por');
    }

    // Scopes

    public function scopeValesFuturos($query)
    {
        return $query->where('genera_vale_futuro', true);
    }

    public function scopeValesUsados($query)
    {
        return $query->where('usado', true);
    }

    public function scopeValesPendientes($query)
    {
        return $query->where('genera_vale_futuro', true)
                    ->where('usado', false)
                    ->where('fecha_validez_generado', '>=', now());
    }

    public function scopePorCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    // Métodos auxiliares

    public function esValido(): bool
    {
        if (!$this->genera_vale_futuro) {
            return false;
        }

        if ($this->usado) {
            return false;
        }

        if ($this->fecha_validez_generado && $this->fecha_validez_generado < now()) {
            return false;
        }

        return true;
    }

    public function marcarComoUsado(): void
    {
        $this->update([
            'usado' => true,
            'fecha_uso' => now(),
        ]);
    }
}
