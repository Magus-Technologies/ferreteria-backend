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
        'fecha_proforma',           // ✅ NUEVO
        'vigencia_dias',
        'fecha_vencimiento',
        'tipo_moneda',
        'tipo_de_cambio',
        'observaciones',
        'estado_cotizacion',
        'reservar_stock',           // ✅ NUEVO
        'cliente_id',
        'ruc_dni',                  // ✅ NUEVO
        'telefono',                 // ✅ NUEVO
        'direccion',                // ✅ NUEVO
        'tipo_documento',           // ✅ NUEVO
        'user_id',
        'vendedor',                 // ✅ NUEVO
        'forma_de_pago',            // ✅ NUEVO
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
            'fecha_proforma' => 'datetime',     // ✅ NUEVO
            'fecha_vencimiento' => 'datetime',
            'vigencia_dias' => 'integer',
            'reservar_stock' => 'boolean',      // ✅ NUEVO
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
