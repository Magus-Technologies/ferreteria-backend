<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenCotizacion extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmacencotizacion';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'cotizacion_id',
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
     * Relación: Pertenece a una cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
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
        return $this->hasMany(UnidadDerivadaInmutableCotizacion::class, 'producto_almacen_cotizacion_id');
    }
}
