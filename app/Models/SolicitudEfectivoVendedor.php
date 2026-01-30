<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SolicitudEfectivoVendedor extends Model
{
    protected $table = 'solicitudes_efectivo_vendedores';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'apertura_cierre_caja_id',
        'vendedor_solicitante_id',
        'vendedor_prestamista_id',
        'monto_solicitado',
        'sub_caja_destino_id',
        'sub_caja_origen_id',
        'motivo',
        'estado',
        'fecha_solicitud',
        'fecha_respuesta',
        'comentario_respuesta',
    ];

    protected $casts = [
        'monto_solicitado' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_respuesta' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->fecha_solicitud)) {
                $model->fecha_solicitud = now();
            }
        });
    }

    // Relaciones
    public function aperturaCierreCaja()
    {
        return $this->belongsTo(AperturaCierreCaja::class, 'apertura_cierre_caja_id');
    }

    public function vendedorSolicitante()
    {
        return $this->belongsTo(User::class, 'vendedor_solicitante_id');
    }

    public function vendedorPrestamista()
    {
        return $this->belongsTo(User::class, 'vendedor_prestamista_id');
    }

    public function transferencia()
    {
        return $this->hasOne(TransferenciaEfectivoVendedor::class, 'solicitud_id');
    }

    public function subCajaDestino()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_destino_id');
    }

    public function subCajaOrigen()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_origen_id');
    }

    // Métodos helper
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'aprobada';
    }

    public function estaRechazada(): bool
    {
        return $this->estado === 'rechazada';
    }
}
