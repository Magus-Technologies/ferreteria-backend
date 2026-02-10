<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoNota extends Model
{
    protected $table = 'motivo_nota';

    protected $fillable = [
        'codigo_sunat',
        'descripcion',
        'tipo',
        'estado',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    // Relationships
    public function notasDebito(): HasMany
    {
        return $this->hasMany(NotaDebito::class, 'motivo_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeNotasDebito($query)
    {
        return $query->where('tipo', 'ND');
    }

    public function scopeNotasCredito($query)
    {
        return $query->where('tipo', 'NC');
    }

    // Helper methods
    public function esNotaDebito(): bool
    {
        return $this->tipo === 'ND';
    }

    public function esNotaCredito(): bool
    {
        return $this->tipo === 'NC';
    }
}
