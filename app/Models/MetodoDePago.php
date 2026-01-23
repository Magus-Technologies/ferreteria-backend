<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoDePago extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'metododepago';

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
        'id',
        'name',
        'cuenta_bancaria',
        'nombre_titular',
        'monto',
        'monto_inicial',
        'subcaja_id',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_inicial' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación: Pertenece a una subcaja
     */
    public function subcaja(): BelongsTo
    {
        return $this->belongsTo(SubCaja::class);
    }

    /**
     * Relación: Tiene muchos despliegues de pago
     */
    public function desplieguesDePagos(): HasMany
    {
        return $this->hasMany(DespliegueDePago::class, 'metodo_de_pago_id');
    }
}
