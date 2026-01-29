<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadDerivadaInmutableCotizacion extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutablecotizacion';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_id',
        'producto_almacen_cotizacion_id',
        'factor',
        'cantidad',
        'precio',
        'recargo',
        'descuento_tipo',
        'descuento',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'precio' => 'decimal:4',
            'recargo' => 'decimal:4',
            'descuento' => 'decimal:4',
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén cotización
     */
    public function productoAlmacenCotizacion(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenCotizacion::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable
     */
    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }
}
