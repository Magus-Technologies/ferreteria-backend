<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesion extends Model
{
    protected $table = 'profesion';

    protected $fillable = [
        'nombre',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'profesion_id');
    }
}
