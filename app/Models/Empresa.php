<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresa'; // Tabla en singular
    public $timestamps = false;

    protected $fillable = [
        'almacen_id',
        'marca_id',
        'serie_ingreso',
        'serie_salida',
        'serie_recepcion_almacen',
        'ruc',
        'razon_social',
        'direccion',
        'telefono',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'serie_ingreso' => 'integer',
            'serie_salida' => 'integer',
            'serie_recepcion_almacen' => 'integer',
        ];
    }

    public function almacenPredeterminado(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function marcaPredeterminada(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
