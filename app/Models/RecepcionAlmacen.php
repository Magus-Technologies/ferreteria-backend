<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecepcionAlmacen extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'recepcionalmacen';

    /**
     * Timestamps en snake_case
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'numero',
        'observaciones',
        'fecha',
        'transportista_razon_social',
        'transportista_ruc',
        'transportista_placa',
        'transportista_licencia',
        'transportista_dni',
        'transportista_name',
        'transportista_guia_remision',
        'estado',
        'user_id',
        'compra_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a una compra
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    /**
     * Relación: Pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Tiene muchos productos por almacén
     */
    public function productosPorAlmacen(): HasMany
    {
        return $this->hasMany(ProductoAlmacenRecepcion::class, 'recepcion_id');
    }
}
