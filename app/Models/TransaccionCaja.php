<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransaccionCaja extends Model
{
    protected $table = 'transacciones_caja';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sub_caja_id',
        'tipo_transaccion',
        'monto',
        'saldo_anterior',
        'saldo_nuevo',
        'descripcion',
        'despliegue_pago_id',
        'referencia_id',
        'referencia_tipo',
        'user_id',
        'fecha',
        'conteo_billetes_monedas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'fecha' => 'datetime',
        'created_at' => 'datetime',
        'conteo_billetes_monedas' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = 'txn_' . Str::random(20);
            }
        });
    }

    /**
     * Filas que NO son dinero de la sesión de un vendedor y que por lo tanto
     * quedan fuera de todo cálculo de "cuánto tiene esta persona desde que aperturó":
     *
     *  · 'apertura'      → el monto con que abrió ya entra como base por su
     *    distribución; contarlo también acá lo duplicaba.
     *  · 'monto_inicial' → saldo con que arranca un BANCO. Queda a nombre de quien
     *    lo configuró (dato de auditoría correcto), pero no es plata que el usuario
     *    haya recibido en mano: sin esto, quien registró el bcp aparecía con sus
     *    50,000 como efectivo disponible.
     *
     * Esta condición estaba copiada en ocho servicios y controladores, y las copias
     * se fueron desincronizando —'monto_inicial' solo se había excluido en una—.
     * Vive acá para que la regla sea una sola.
     */
    public function scopeSinFilasBase($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('referencia_tipo')
                ->orWhereNotIn('referencia_tipo', ['apertura', 'monto_inicial']);
        });
    }

    // Relaciones
    public function subCaja()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function desplieguePago()
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_pago_id');
    }
}
