<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Entrega;

class Vehiculo extends Model
{
    protected $table = 'vehiculo';

    protected $fillable = [
        'name',
        'tipo',
        'marca_modelo',
        'placa',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'vehiculo_id');
    }
}
