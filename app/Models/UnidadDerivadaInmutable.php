<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadDerivadaInmutable extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'unidadderivadainmutable';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Relación: Tiene muchas unidades derivadas en compras
     */
    public function unidadesEnCompras(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableCompra::class, 'unidad_derivada_inmutable_id');
    }

    /**
     * Relación: Tiene muchas unidades derivadas en ventas
     */
    public function unidadesEnVentas(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_inmutable_id');
    }

    /**
     * Relación: Tiene muchas unidades derivadas en cotizaciones
     */
    public function unidadesEnCotizaciones(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableCotizacion::class, 'unidad_derivada_inmutable_id');
    }

    /**
     * Relación: Tiene muchas unidades derivadas en ingresos/salidas
     */
    public function unidadesEnIngresos(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableIngresoSalida::class, 'unidad_derivada_inmutable_id');
    }

    /**
     * Relación: Tiene muchas unidades derivadas en recepciones
     */
    public function unidadesEnRecepciones(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableRecepcion::class, 'unidad_derivada_inmutable_id');
    }
}
