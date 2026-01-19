<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoEntreCajas extends Model
{
    protected $table = 'prestamos_entre_cajas';

    protected $fillable = [
        'id',
        'sub_caja_origen_id',
        'sub_caja_destino_id',
        'monto',
        'despliegue_de_pago_id',
        'estado',
        'motivo',
        'user_presta_id',
        'user_recibe_id',
        'fecha_prestamo',
        'fecha_devolucion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_prestamo' => 'datetime',
        'fecha_devolucion' => 'datetime',
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

    public function userPresta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_presta_id');
    }

    public function userRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_recibe_id');
    }

    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }
}
