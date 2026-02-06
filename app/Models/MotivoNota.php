<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoNota extends Model
{
    protected $table = 'motivo_nota';

    protected $fillable = [
        'codigo',
        'descripcion',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relationships
    public function notasDebito(): HasMany
    {
        return $this->hasMany(NotaDebito::class, 'motivo_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeNotasDebito($query)
    {
        return $query->where('tipo', 'debito');
    }

    public function scopeNotasCredito($query)
    {
        return $query->where('tipo', 'credito');
    }

    // Helper methods
    public function esNotaDebito(): bool
    {
        return $this->tipo === 'debito';
    }

    public function esNotaCredito(): bool
    {
        return $this->tipo === 'credito';
    }
}
