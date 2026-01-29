<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenVenta extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmacenventa';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'venta_id',
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
     * Relación: Pertenece a una venta
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
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
        return $this->hasMany(UnidadDerivadaInmutableVenta::class, 'producto_almacen_venta_id');
    }
}
