<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoAlmacenPrestamo extends Model
{
    protected $table = 'productoalmacenprestamo';
    public $timestamps = false;

    protected $fillable = [
        'prestamo_id',
        'costo',
        'producto_almacen_id',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:4',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id', 'id');
    }

    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class);
    }

    public function unidadesDerivadas(): HasMany
    {
        return $this->hasMany(UnidadDerivadaInmutablePrestamo::class, 'producto_almacen_prestamo_id');
    }
}
