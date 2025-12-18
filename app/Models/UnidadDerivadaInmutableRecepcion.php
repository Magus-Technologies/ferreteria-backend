<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadDerivadaInmutableRecepcion extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutablerecepcion';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_id',
        'producto_almacen_recepcion_id',
        'factor',
        'cantidad',
        'cantidad_restante',
        'lote',
        'vencimiento',
        'flete',
        'bonificacion',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'cantidad_restante' => 'decimal:3',
            'vencimiento' => 'datetime',
            'flete' => 'decimal:4',
            'bonificacion' => 'boolean',
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén recepción
     */
    public function productoAlmacenRecepcion(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenRecepcion::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable
     */
    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }

    /**
     * Relación: Tiene muchos registros de historial
     */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialUnidadDerivadaInmutableRecepcion::class, 'unidad_derivada_inmutable_recepcion_id');
    }
}
