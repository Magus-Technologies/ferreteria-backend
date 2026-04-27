<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ComisionPagoVenta extends Model
{
    protected $table = 'comision_pago_venta';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'comision_pago_id',
        'unidad_derivada_venta_id',
        'monto_aplicado',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
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

    public function comisionPago()
    {
        return $this->belongsTo(ComisionPago::class, 'comision_pago_id');
    }

    public function unidadDerivadaVenta()
    {
        return $this->belongsTo(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_venta_id');
    }
}
