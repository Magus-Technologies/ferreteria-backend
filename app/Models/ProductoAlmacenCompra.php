<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenCompra extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'productoalmacencompra';

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'compra_id',
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
     * Relación: Pertenece a una compra
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
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
        return $this->hasMany(UnidadDerivadaInmutableCompra::class, 'producto_almacen_compra_id');
    }
}
