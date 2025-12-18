<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DespliegueDePago extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'desplieguedepago';

    /**
     * Clave primaria es string (CUID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'name',
        'adicional',
        'mostrar',
        'metodo_de_pago_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'adicional' => 'decimal:2',
            'mostrar' => 'boolean',
        ];
    }

    /**
     * Relación: Pertenece a un método de pago
     */
    public function metodoDePago(): BelongsTo
    {
        return $this->belongsTo(MetodoDePago::class, 'metodo_de_pago_id');
    }

    /**
     * Relación: Tiene muchos despliegues de pago en ventas
     */
    public function desplieguesDePagoVenta(): HasMany
    {
        return $this->hasMany(DespliegueDePagoVenta::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Tiene muchos pagos de compras
     */
    public function pagosDeCompras(): HasMany
    {
        return $this->hasMany(PagoDeCompra::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Tiene muchos egresos de dinero
     */
    public function egresosDinero(): HasMany
    {
        return $this->hasMany(EgresoDinero::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Tiene muchos ingresos de dinero
     */
    public function ingresosDinero(): HasMany
    {
        return $this->hasMany(IngresoDinero::class, 'despliegue_de_pago_id');
    }

    /**
     * Relación: Tiene muchas compras
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'despliegue_de_pago_id');
    }
}
