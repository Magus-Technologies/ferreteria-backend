<?php

namespace App\Models;

use App\Enums\EstadoCotizacion;
use App\Enums\TipoMoneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $table = 'cotizacion';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'numero',
        'fecha',
        'vigencia_dias',
        'fecha_vencimiento',
        'tipo_moneda',
        'tipo_de_cambio',
        'observaciones',
        'estado_cotizacion',
        'cliente_id',
        'user_id',
        'almacen_id',
        'venta_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo_moneda' => TipoMoneda::class,
            'estado_cotizacion' => EstadoCotizacion::class,
            'tipo_de_cambio' => 'decimal:4',
            'fecha' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'vigencia_dias' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function productosPorAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenCotizacion::class);
    }
}
