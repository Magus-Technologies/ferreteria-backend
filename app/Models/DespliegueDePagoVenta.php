<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespliegueDePagoVenta extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'desplieguedepagoventa';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'venta_id',
        'despliegue_de_pago_id',
        'monto',
        'tipo',
        'numero_operacion_id',
        'sobrecargo_aplicado',
        'referencia',
        'recibe_efectivo',
        'fecha',
        'user_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
            'sobrecargo_aplicado' => 'decimal:4',
            'recibe_efectivo' => 'decimal:4',
            'fecha' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a una venta
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Relación: Pertenece a un despliegue de pago
     */
    public function despliegueDePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Pertenece a un número de operación
     */
    public function numeroOperacion(): BelongsTo
    {
        return $this->belongsTo(NumeroOperacionPago::class, 'numero_operacion_id');
    }

    /**
     * Relación: Usuario que registró este movimiento (cobro inicial, cobro
     * de diferencia o devolución).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
