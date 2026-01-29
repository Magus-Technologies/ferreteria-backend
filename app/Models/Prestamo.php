<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestamo extends Model
{
    protected $table = 'prestamos';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'numero',
        'fecha',
        'fecha_vencimiento',
        'tipo_operacion',
        'tipo_entidad',
        'cliente_id',
        'proveedor_id',
        'ruc_dni',
        'telefono',
        'direccion',
        'tipo_moneda',
        'tipo_de_cambio',
        'monto_total',
        'monto_pagado',
        'monto_pendiente',
        'tasa_interes',
        'tipo_interes',
        'dias_gracia',
        'garantia',
        'estado_prestamo',
        'observaciones',
        'user_id',
        'vendedor',
        'almacen_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'tipo_de_cambio' => 'decimal:4',
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'monto_pendiente' => 'decimal:2',
            'tasa_interes' => 'decimal:2',
            'dias_gracia' => 'integer',
            'cliente_id' => 'integer',
            'proveedor_id' => 'integer',
            'user_id' => 'integer',
            'almacen_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoPrestamo::class, 'prestamo_id', 'id');
    }

    public function productosPorAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenPrestamo::class, 'prestamo_id', 'id');
    }
}
