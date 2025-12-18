<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EgresoDinero extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'egresodinero';

    /**
     * Clave primaria es string (CUID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Timestamps en camelCase (convención Prisma)
     */
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'monto',
        'vuelto',
        'observaciones',
        'estado',
        'despliegue_de_pago_id',
        'user_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'vuelto' => 'decimal:2',
            'estado' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a un despliegue de pago
     */
    public function despliegueDePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Tiene muchas compras
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'egreso_dinero_id');
    }
}
