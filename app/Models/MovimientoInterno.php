<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInterno extends Model
{
    protected $table = 'movimientos_internos';

    protected $fillable = [
        'id',
        'sub_caja_origen_id',
        'sub_caja_destino_id',
        'monto',
        'saldo_origen_anterior',
        'saldo_origen_actual',
        'saldo_destino_anterior',
        'saldo_destino_actual',
        'despliegue_de_pago_origen_id',
        'despliegue_de_pago_destino_id',
        'concepto',
        'justificacion',
        'comprobante',
        'numero_operacion',
        'user_id',
        // Solo en TRASLADO DE EFECTIVO: usuario a cuya SESIÓN ABIERTA se acreditó
        // el dinero. Su presencia es lo que marca el movimiento como
        // "cerrado → sesión" (deja de ser saldo movible) en vez de un simple
        // "cajón → cajón". Ver esTrasladoASesion().
        'destino_user_id',
        'fecha',
        'estado',
        'fecha_anulacion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_origen_anterior' => 'float',
        'saldo_origen_actual' => 'float',
        'saldo_destino_anterior' => 'float',
        'saldo_destino_actual' => 'float',
        'fecha' => 'datetime',
        'fecha_anulacion' => 'datetime',
    ];

    // Accessor para mantener compatibilidad con código que usa fecha_movimiento
    public function getFechaMovimientoAttribute()
    {
        return $this->fecha;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    /**
     * ¿Este movimiento mandó dinero CERRADO a la SESIÓN ABIERTA de un usuario?
     * De ser así el monto deja de ser movible: recién se podrá volver a mover
     * cuando ese usuario cierre su caja.
     */
    public function esTrasladoASesion(): bool
    {
        return !empty($this->destino_user_id);
    }

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    // Relaciones
    public function subCajaOrigen(): BelongsTo
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_origen_id');
    }

    public function subCajaDestino(): BelongsTo
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_destino_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function desplieguePagoOrigen(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_origen_id');
    }

    public function desplieguePagoDestino(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_destino_id');
    }
}
