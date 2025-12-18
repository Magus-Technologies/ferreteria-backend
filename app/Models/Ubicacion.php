<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    public $timestamps = false;

    protected $table = 'ubicacion';

    protected $fillable = [
        'name',
        'almacen_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function productosAlmacenes(): HasMany
    {
        return $this->hasMany(ProductoAlmacen::class);
    }
}
