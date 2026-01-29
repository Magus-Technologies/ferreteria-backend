<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CierreCaja extends Model
{
    protected $table = 'cierre_caja';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sub_caja_id',
        'fecha_cierre',
        'saldo_sistema',
        'saldo_fisico',
        'diferencia',
        'observaciones',
        'user_id',
    ];

    protected $casts = [
        'saldo_sistema' => 'decimal:2',
        'saldo_fisico' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'fecha_cierre' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = 'cie_' . Str::random(20);
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
