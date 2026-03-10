<?php

namespace App\Models;

use App\Enums\EstadoDeCompra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'codigo',
        'requerimiento_id',
        'proveedor_id',
        'fecha',
        'tipo_moneda',
        'tipo_de_cambio',
        'ruc',
        'tipo_documento',
        'serie',
        'numero',
        'guia',
        'percepcion',
        'forma_de_pago',
        'numero_dias',
        'fecha_vencimiento',
        'egreso_dinero_id',
        'despliegue_de_pago_id',
        'estado',
        'user_id',
        'almacen_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'tipo_de_cambio' => 'decimal:4',
            'percepcion' => 'decimal:4',
            'numero_dias' => 'integer',
            'estado' => EstadoDeCompra::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ============= RELATIONSHIPS =============

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
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

    public function productos(): HasMany
    {
        return $this->hasMany(OrdenCompraProducto::class, 'orden_compra_id');
    }

    public function despliegueDePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_de_pago_id');
    }

    public function egresoDinero(): BelongsTo
    {
        return $this->belongsTo(EgresoDinero::class, 'egreso_dinero_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'orden_compra_id');
    }

    // ============= HELPERS =============

    /**
     * Generar código auto-incremental: OC-YYYY-NNN
     */
    public static function generarCodigo(): string
    {
        $year = date('Y');
        $prefix = "OC-{$year}-";

        $ultimo = static::where('codigo', 'like', "{$prefix}%")
            ->orderByDesc('codigo')
            ->value('codigo');

        if ($ultimo) {
            $num = (int) substr($ultimo, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}
