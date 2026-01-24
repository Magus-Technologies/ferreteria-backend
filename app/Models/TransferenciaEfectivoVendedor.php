<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransferenciaEfectivoVendedor extends Model
{
    protected $table = 'transferencias_efectivo_vendedores';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'solicitud_id',
        'apertura_cierre_caja_id',
        'vendedor_origen_id',
        'vendedor_destino_id',
        'monto',
        'fecha_transferencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_transferencia' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->fecha_transferencia)) {
                $model->fecha_transferencia = now();
            }
        });
    }

    // Relaciones
    public function solicitud()
    {
        return $this->belongsTo(SolicitudEfectivoVendedor::class, 'solicitud_id');
    }

    public function aperturaCierreCaja()
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_caja_id');
    }

    public function vendedorOrigen()
    {
        return $this->belongsTo(User::class, 'vendedor_origen_id');
    }

    public function vendedorDestino()
    {
        return $this->belongsTo(User::class, 'vendedor_destino_id');
    }
}
