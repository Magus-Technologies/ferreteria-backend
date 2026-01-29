<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumeroOperacionPago extends Model
{
    protected $table = 'numeros_operacion_pago';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'venta_id',
        'compra_id',
        'despliegue_pago_id',
        'numero_operacion',
        'monto',
        'sobrecargo_aplicado',
        'monto_total',
        'fecha_operacion',
        'user_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'sobrecargo_aplicado' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'fecha_operacion' => 'datetime',
    ];

    /**
     * Relación con el método de pago
     */
    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_pago_id');
    }

    /**
     * Relación con el usuario que registró
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con la venta (si aplica)
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /**
     * Calcular el sobrecargo basado en el método de pago
     */
    public static function calcularSobrecargo(DespliegueDePago $metodoPago, float $monto): float
    {
        if ($metodoPago->tipo_sobrecargo === 'porcentaje') {
            return round($monto * ($metodoPago->sobrecargo_porcentaje / 100), 2);
        } elseif ($metodoPago->tipo_sobrecargo === 'monto_fijo') {
            return (float) $metodoPago->adicional;
        }
        
        return 0.00;
    }

    /**
     * Calcular el monto total con sobrecargo
     */
    public static function calcularMontoTotal(DespliegueDePago $metodoPago, float $monto): float
    {
        $sobrecargo = self::calcularSobrecargo($metodoPago, $monto);
        return round($monto + $sobrecargo, 2);
    }
}
