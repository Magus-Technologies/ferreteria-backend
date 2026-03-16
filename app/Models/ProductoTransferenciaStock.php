<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoTransferenciaStock extends Model
{
    protected $table = 'producto_transferencia_stock';

    public $timestamps = false;

    protected $fillable = [
        'transferencia_stock_id',
        'producto_almacen_origen_id',
        'producto_almacen_destino_id',
        'unidad_derivada_inmutable_id',
        'factor',
        'cantidad',
        'costo',
        'stock_anterior_origen',
        'stock_nuevo_origen',
        'stock_anterior_destino',
        'stock_nuevo_destino',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'cantidad' => 'decimal:3',
            'costo' => 'decimal:4',
            'stock_anterior_origen' => 'decimal:3',
            'stock_nuevo_origen' => 'decimal:3',
            'stock_anterior_destino' => 'decimal:3',
            'stock_nuevo_destino' => 'decimal:3',
        ];
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaStock::class, 'transferencia_stock_id');
    }

    public function productoAlmacenOrigen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_origen_id');
    }

    public function productoAlmacenDestino(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_destino_id');
    }

    public function unidadDerivadaInmutable(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutable::class);
    }
}
