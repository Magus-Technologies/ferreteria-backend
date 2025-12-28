<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    /**
     * Verificar si el código es válido
     */
    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    /**
     * Marcar el código como usado
     */
    public function markAsUsed(): void
    {
        $this->update(['used' => true]);
    }
}
