<?php

namespace App\Models;

use App\Enums\EstadoDeVenta;
use App\Enums\FormaDePago;
use App\Enums\TipoDocumento;
use App\Enums\TipoMoneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venta extends Model
{
    protected $table = 'venta';
    protected $keyType = 'string';
    public $incrementing = false;

    // Prisma usa camelCase para timestamps
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id',
        'tipo_documento',
        'serie',
        'numero',
        'descripcion',
        'forma_de_pago',
        'tipo_moneda',
        'tipo_de_cambio',
        'fecha',
        'estado_de_venta',
        'cliente_id',
        'direccion_seleccionada', // ✅ Agregar campo
        'recomendado_por_id',
        'user_id',
        'almacen_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'forma_de_pago' => FormaDePago::class,
            'tipo_moneda' => TipoMoneda::class,
            'estado_de_venta' => EstadoDeVenta::class,
            'tipo_de_cambio' => 'decimal:4',
            'fecha' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function recomendadoPor(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'recomendado_por_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function productosPorAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenVenta::class);
    }

    public function despliegueDePagoVentas(): HasMany
    {
        return $this->hasMany(DespliegueDePagoVenta::class);
    }

    public function entregasProductos(): HasMany
    {
        return $this->hasMany(EntregaProducto::class);
    }

    public function cotizacion(): HasOne
    {
        return $this->hasOne(Cotizacion::class);
    }
}
