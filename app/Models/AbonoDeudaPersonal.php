<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonoDeudaPersonal extends Model
{
    protected $table = 'abonos_deuda_personal';
    
    protected $fillable = [
        'deuda_personal_id',
        'monto',
        'metodo_pago_id',
        'numero_operacion',
        'observaciones',
        'saldo_anterior',
        'saldo_despues',
        'registrado_por_user_id',
        'fecha_abono',
    ];
    
    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_despues' => 'decimal:2',
        'fecha_abono' => 'datetime',
    ];
    
    public function deudaPersonal(): BelongsTo
    {
        return $this->belongsTo(DeudaPersonal::class);
    }
    
    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoDePago::class);
    }
    
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
