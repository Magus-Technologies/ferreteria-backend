<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialUnidadDerivadaInmutableRecepcion extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'historialunidadderivadainmutablerecepcion';

    /**
     * Solo tiene created_at (no updated_at)
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'unidad_derivada_inmutable_recepcion_id',
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
            'created_at' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a una unidad derivada inmutable recepción
     */
    public function unidadDerivadaInmutableRecepcion(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableRecepcion::class, 'unidad_derivada_inmutable_recepcion_id');
    }
}
