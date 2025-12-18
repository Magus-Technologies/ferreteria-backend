<?php

namespace App\Models;

use App\Enums\EstadoDeCompra;
use App\Enums\FormaDePago;
use App\Enums\TipoDocumento;
use App\Enums\TipoMoneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compra';
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
        'percepcion',
        'numero_dias',
        'fecha_vencimiento',
        'fecha',
        'guia',
        'estado_de_compra',
        'egreso_dinero_id',
        'despliegue_de_pago_id',
        'user_id',
        'almacen_id',
        'proveedor_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'forma_de_pago' => FormaDePago::class,
            'tipo_moneda' => TipoMoneda::class,
            'estado_de_compra' => EstadoDeCompra::class,
            'tipo_de_cambio' => 'decimal:4',
            'percepcion' => 'decimal:4',
            'fecha' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'numero_dias' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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
        return $this->hasMany(ProductoAlmacenCompra::class);
    }

    public function pagosDeCompras(): HasMany
    {
        return $this->hasMany(PagoDeCompra::class);
    }

    public function recepcionesAlmacen(): HasMany
    {
        return $this->hasMany(RecepcionAlmacen::class);
    }
}
