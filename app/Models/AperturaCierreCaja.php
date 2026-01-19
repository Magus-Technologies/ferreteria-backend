<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AperturaCierreCaja extends Model
{
    protected $table = 'apertura_cierre_caja';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_principal_id',
        'sub_caja_id',
        'user_id',
        'monto_apertura',
        'fecha_apertura',
        'monto_cierre',
        'fecha_cierre',
        'estado',
        'monto_cierre_efectivo',
        'monto_cierre_cuentas',
        'conteo_billetes_monedas',
        'conceptos_adicionales',
        'comentarios',
        'supervisor_id',
        'diferencia_efectivo',
        'diferencia_total',
        'forzar_cierre',
    ];

    protected $casts = [
        'monto_apertura' => 'decimal:2',
        'monto_cierre' => 'decimal:2',
        'monto_cierre_efectivo' => 'decimal:2',
        'monto_cierre_cuentas' => 'decimal:2',
        'diferencia_efectivo' => 'decimal:2',
        'diferencia_total' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'conteo_billetes_monedas' => 'array',
        'conceptos_adicionales' => 'array',
        'forzar_cierre' => 'boolean',
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
    public function cajaPrincipal()
    {
        return $this->belongsTo(CajaPrincipal::class, 'caja_principal_id');
    }

    public function subCaja()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // Métodos helper
    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }
}
