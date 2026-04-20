<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ComisionPago extends Model
{
    protected $table = 'comision_pago';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'pagado_por',
        'monto_pagado',
        'periodo_desde',
        'periodo_hasta',
        'fecha_pago',
        'metodo_pago',
        'observacion',
    ];

    protected $casts = [
        'monto_pagado' => 'decimal:2',
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
        'fecha_pago' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pagadoPor()
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }
}
