<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerieDocumento extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'seriedocumento';

    /**
     * Timestamps en snake_case
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'tipo_documento',
        'serie',
        'correlativo',
        'almacen_id',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a un almacén
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}
