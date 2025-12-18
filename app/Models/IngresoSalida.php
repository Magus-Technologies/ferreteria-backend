<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngresoSalida extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'ingresosalida';

    /**
     * Timestamps en camelCase (convención Prisma)
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'fecha',
        'tipo_documento',
        'serie',
        'numero',
        'descripcion',
        'estado',
        'almacen_id',
        'tipo_ingreso_id',
        'proveedor_id',
        'user_id',
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
     * Relación: Pertenece a un almacén
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * Relación: Pertenece a un tipo de ingreso/salida
     */
    public function tipoIngreso(): BelongsTo
    {
        return $this->belongsTo(TipoIngresoSalida::class, 'tipo_ingreso_id');
    }

    /**
     * Relación: Pertenece a un proveedor (nullable)
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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
        return $this->hasMany(ProductoAlmacenIngresoSalida::class, 'ingreso_id');
    }
}
