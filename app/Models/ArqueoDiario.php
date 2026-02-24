<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ArqueoDiario extends Model
{
    use HasUlids;

    protected $table = 'arqueos_diarios';

    protected $fillable = [
        'apertura_cierre_caja_id',
        'user_id',
        'fecha_arqueo',
        'monto_cierre',
        'monto_cierre_efectivo',
        'monto_cierre_cuentas',
        'conteo_billetes_monedas',
        'diferencia_efectivo',
        'diferencia_total',
        'comentarios',
        'supervisor_id_validador',
        'supervisor_validado',
        'estado_cierre',
        'email_reporte',
        'whatsapp_reporte',
        'resumen_snapshot',
    ];

    protected $casts = [
        'fecha_arqueo' => 'date',
        'monto_cierre' => 'decimal:2',
        'monto_cierre_efectivo' => 'decimal:2',
        'monto_cierre_cuentas' => 'decimal:2',
        'diferencia_efectivo' => 'decimal:2',
        'diferencia_total' => 'decimal:2',
        'conteo_billetes_monedas' => 'array',
        'supervisor_validado' => 'boolean',
        'resumen_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la apertura/cierre de caja
     */
    public function aperturaCierreCaja(): BelongsTo
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_caja_id');
    }

    /**
     * Relación con el usuario que realizó el arqueo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el supervisor que validó
     */
    public function supervisorValidador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id_validador');
    }

    /**
     * Relación con la deuda generada en este arqueo (si hubo faltante)
     */
    public function deudaPersonal(): HasOne
    {
        return $this->hasOne(DeudaPersonal::class, 'arqueo_diario_id');
    }
}
