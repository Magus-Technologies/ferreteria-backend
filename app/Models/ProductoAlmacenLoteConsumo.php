<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAlmacenLoteConsumo extends Model
{
    protected $table = 'productoalmacen_lote_consumo';

    protected $fillable = [
        'lote_id',
        'producto_almacen_id',
        'cantidad',
        'costo',
        'origen_tipo',
        'origen_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenLote::class, 'lote_id');
    }
}
