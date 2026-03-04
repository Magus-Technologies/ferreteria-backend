<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoInternoProducto extends Model
{
    protected $table = 'requerimiento_interno_productos';
    public $timestamps = false;

    protected $fillable = [
        'requerimiento_id',
        'producto_id',
        'cantidad',
        'unidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
