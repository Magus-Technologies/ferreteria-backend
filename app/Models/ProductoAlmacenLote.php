<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAlmacenLote extends Model
{
    protected $table = 'productoalmacen_lote';

    protected $fillable = [
        'producto_almacen_id',
        'recepcion_id',
        'compra_id',
        'ingreso_salida_id',
        'costo',
        'cantidad_inicial',
        'cantidad_restante',
        'secuencia',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:4',
            'cantidad_inicial' => 'decimal:3',
            'cantidad_restante' => 'decimal:3',
            'secuencia' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_id');
    }
}
