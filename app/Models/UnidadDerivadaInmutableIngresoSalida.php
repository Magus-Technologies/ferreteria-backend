<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadDerivadaInmutableIngresoSalida extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutableingresosalida';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_id',
        'producto_almacen_ingreso_salida_id',
        'factor',
        'cantidad',
        'cantidad_restante',
        'lote',
        'vencimiento',
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
        ];
    }

    /**
     * Relación: Pertenece a un producto almacén ingreso/salida
     */
    public function productoAlmacenIngresoSalida(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenIngresoSalida::class);
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
        return $this->hasMany(HistorialUnidadDerivadaInmutableIngresoSalida::class, 'unidad_derivada_inmutable_ingreso_salida_id');
    }
}
