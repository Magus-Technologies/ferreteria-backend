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
        'despliegue_de_pago_id',
        'justificacion',
        'comprobante',
        'user_id',
        'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'datetime',
    ];

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

    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }
}
