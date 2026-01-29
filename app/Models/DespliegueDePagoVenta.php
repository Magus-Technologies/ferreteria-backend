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
        'numero_operacion_id',
        'sobrecargo_aplicado',
        'referencia',
        'recibe_efectivo',
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
}
