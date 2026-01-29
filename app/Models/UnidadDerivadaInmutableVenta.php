<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadDerivadaInmutableVenta extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutableventa';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_id',
        'producto_almacen_venta_id',
        'factor',
        'cantidad',
        'cantidad_pendiente',
        'precio',
        'recargo',
        'descuento_tipo',
        'descuento',
        'comision',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'cantidad_pendiente' => 'decimal:3',
            'precio' => 'decimal:4',
            'recargo' => 'decimal:4',
            'descuento' => 'decimal:4',
            'comision' => 'decimal:4',
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén venta
     */
    public function productoAlmacenVenta(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenVenta::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable
     */
    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }

    /**
     * Relación: Tiene muchos detalles de entrega
     */
    public function detallesEntrega(): HasMany
    {
        return $this->hasMany(DetalleEntregaProducto::class, 'unidad_derivada_venta_id');
    }
}
