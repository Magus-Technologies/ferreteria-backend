<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MovimientoCaja extends Model
{
    protected $table = 'movimiento_caja';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'apertura_cierre_id',
        'caja_principal_id',
        'sub_caja_id',
        'cajero_id',
        'fecha_hora',
        'tipo_movimiento',
        'concepto',
        'saldo_inicial',
        'ingreso',
        'salida',
        'saldo_final',
        'registradora',
        'estado_caja',
        'tipo_comprobante',
        'numero_comprobante',
        'metodo_pago_id',
        'referencia_id',
        'referencia_tipo',
        'caja_origen_id',
        'caja_destino_id',
        'monto_transferencia',
        'observaciones',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'saldo_inicial' => 'decimal:2',
        'ingreso' => 'decimal:2',
        'salida' => 'decimal:2',
        'saldo_final' => 'decimal:2',
        'monto_transferencia' => 'decimal:2',
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

    // Relaciones
    public function aperturaCierre()
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_id');
    }

    public function cajaPrincipal()
    {
        return $this->belongsTo(CajaPrincipal::class, 'caja_principal_id');
    }

    public function subCaja()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    public function cajero()
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(DespliegueDePago::class, 'metodo_pago_id');
    }

    public function cajaOrigen()
    {
        return $this->belongsTo(SubCaja::class, 'caja_origen_id');
    }

    public function cajaDestino()
    {
        return $this->belongsTo(SubCaja::class, 'caja_destino_id');
    }
}
