<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenRecepcion extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmacenrecepcion';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'recepcion_id',
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
     * Relación: Pertenece a una recepción de almacén
     */
    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionAlmacen::class, 'recepcion_id');
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
        return $this->hasMany(UnidadDerivadaInmutableRecepcion::class, 'producto_almacen_recepcion_id');
    }
}
