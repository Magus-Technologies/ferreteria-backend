<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadDerivadaInmutablePrestamo extends Model
{
    protected $table = 'unidadderivadainmutableprestamo';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'factor',
        'cantidad',
        'producto_almacen_prestamo_id',
        'unidad_derivada_id',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:4',
            'cantidad' => 'decimal:4',
        ];
    }

    public function productoAlmacenPrestamo(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacenPrestamo::class, 'producto_almacen_prestamo_id');
    }

    public function unidadDerivada(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivada::class, 'unidad_derivada_id');
    }
}
