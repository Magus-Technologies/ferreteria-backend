<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Choferes extends Model
{
    /**
     * Tabla asociada al modelo
     */
    protected $table = 'choferes';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'dni',
        'nombres',
        'apellidos',
        'licencia',
        'telefono',
        'email',
        'direccion',
        'estado',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Accessor: Nombre completo del chofer
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
