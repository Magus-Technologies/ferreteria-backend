<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoIngresoSalida extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'tipoingresosalida';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'name',
        'estado',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    /**
     * Relación: Un tipo de ingreso/salida tiene muchos ingresos/salidas
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(IngresoSalida::class, 'tipo_ingreso_id');
    }
}
