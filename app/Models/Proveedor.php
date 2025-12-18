<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    public $timestamps = false;

    protected $table = 'proveedor';

    protected $fillable = [
        'numero_documento',
        'nombre',
        'direccion',
        'telefono',
        'email',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
