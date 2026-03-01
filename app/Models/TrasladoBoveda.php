<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoBoveda extends Model
{
    use HasUlids;

    protected $table = 'traslados_boveda';

    protected $fillable = [
        'apertura_cierre_caja_id',
        'sub_caja_id',
        'despliegue_pago_id',
        'vendedor_id',
        'supervisor_id',
        'monto',
        'justificacion',
        'fecha_traslado',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_traslado' => 'datetime',
    ];

    /**
     * Relación con la apertura/cierre de caja
     */
    public function aperturaCierreCaja(): BelongsTo
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_caja_id');
    }

    /**
     * Relación con la sub-caja (Caja Chica) de donde sale el efectivo
     */
    public function subCaja(): BelongsTo
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    /**
     * Relación con el método de pago desplegado de donde sale el dinero
     */
    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_pago_id');
    }

    /**
     * Relación con el vendedor que realiza el traslado
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * Relación con el supervisor que autoriza
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
