<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDeCompra extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'pagodecompra';

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
        'estado',
        'compra_id',
        'despliegue_de_pago_id',
        'monto',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'monto' => 'decimal:2',
        ];
    }

    /**
     * Relación: Pertenece a una compra
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    /**
     * Relación: Pertenece a un despliegue de pago
     */
    public function despliegueDePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }
}
