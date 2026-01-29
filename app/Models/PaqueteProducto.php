<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaqueteProducto extends Model
{
    protected $table = 'paquete_producto';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'paquete_id',
        'producto_id',
        'unidad_derivada_id',
        'cantidad',
        'precio_sugerido',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_sugerido' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a un paquete
     */
    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class);
    }

    /**
     * Relación: Pertenece a un producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación: Pertenece a una unidad derivada
     */
    public function unidadDerivada(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivada::class);
    }
}

