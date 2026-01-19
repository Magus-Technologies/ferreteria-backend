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
        'referencia_id',
        'referencia_tipo',
        'user_id',
        'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'fecha' => 'datetime',
        'created_at' => 'datetime',
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

    // Relaciones
    public function subCaja()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
