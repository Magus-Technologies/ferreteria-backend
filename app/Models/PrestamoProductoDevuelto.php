<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoProductoDevuelto extends Model
{
    protected $table = 'prestamo_producto_devuelto';
    public $timestamps = false;

    protected $fillable = [
        'prestamo_devolucion_id',
        'producto_almacen_prestamo_id',
        'unidad_derivada_inmutable_prestamo_id',
        'cantidad',
        'factor',
        'cantidad_fraccion',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'factor' => 'decimal:4',
            'cantidad_fraccion' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function prestamoDevolucion(): BelongsTo
    {
        return $this->belongsTo(PrestamoDevolucion::class, 'prestamo_devolucion_id');
    }

    public function productoAlmacenPrestamo(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenPrestamo::class, 'producto_almacen_prestamo_id');
    }
}