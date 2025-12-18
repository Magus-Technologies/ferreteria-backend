<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenIngresoSalida extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmaceningresosalida';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'ingreso_id',
        'costo',
        'producto_almacen_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'costo' => 'decimal:4',
        ];
    }

    /**
     * Relación: Pertenece a un ingreso/salida
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(IngresoSalida::class, 'ingreso_id');
    }

    /**
     * Relación: Pertenece a un producto almacén
     */
    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class);
    }

    /**
     * Relación: Tiene muchas unidades derivadas
     */
    public function unidadesDerivadas(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutableIngresoSalida::class, 'producto_almacen_ingreso_salida_id');
    }
}
