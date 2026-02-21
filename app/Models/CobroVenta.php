<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobroVenta extends Model
{
    /**
     * Tabla asociada al modelo
     */
    protected $table = 'cobroventa';

    /**
     * Clave primaria es string (ULID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Sin timestamps automáticos de Laravel (manejamos fecha manualmente)
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'id',
        'venta_id',
        'despliegue_de_pago_id',
        'monto',
        'fecha',
        'observacion',
        'numero_letra',
        'numero_operacion',
        'estado',
        'user_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado'  => 'boolean',
            'monto'   => 'decimal:2',
        ];
    }

    /**
     * Boot method para generar ULID automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    /**
     * Relación: Pertenece a una venta
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Relación: Pertenece a un despliegue de pago (método de pago)
     */
    public function despliegueDePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Pertenece a un usuario (quién registró el cobro)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
