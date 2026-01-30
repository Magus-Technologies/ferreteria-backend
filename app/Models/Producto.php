<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'producto';

    // Prisma usa camelCase para timestamps
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'cod_producto',
        'cod_barra',
        'name',
        'name_ticket',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'accion_tecnica',
        'img',
        'ficha_tecnica',
        'stock_min',
        'stock_max',
        'unidades_contenidas',
        'estado',
        'permitido',
    ];

    protected function casts(): array
    {
        return [
            'stock_min' => 'decimal:3',
            'stock_max' => 'integer',
            'unidades_contenidas' => 'decimal:3',
            'estado' => 'boolean',
            'permitido' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function productoEnAlmacenes(): HasMany
    {
        return $this->hasMany(ProductoAlmacen::class);
    }
}
