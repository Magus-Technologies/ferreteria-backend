<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialUnidadDerivadaInmutableIngresoSalida extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'historialunidadderivadainmutableingresosalida';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_ingreso_salida_id',
        'stock_anterior',
        'stock_nuevo',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'stock_anterior' => 'decimal:3',
            'stock_nuevo' => 'decimal:3',
        ];
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable ingreso/salida
     */
    public function unidadDerivadaInmutableIngresoSalida(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableIngresoSalida::class, 'unidad_derivada_inmutable_ingreso_salida_id');
    }
}
