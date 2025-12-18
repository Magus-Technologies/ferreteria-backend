<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AperturaYCierreCaja extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'aperturaycierrecaja';

    /**
     * Clave primaria es string (CUID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'fecha_apertura',
        'monto_apertura',
        'fecha_cierre',
        'monto_cierre',
        'user_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'fecha_cierre' => 'datetime',
            'monto_cierre' => 'decimal:2',
        ];
    }

    /**
     * Relación: Pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
