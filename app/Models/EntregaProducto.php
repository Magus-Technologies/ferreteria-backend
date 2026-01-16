<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaProducto extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'entregaproducto';

    /**
     * Timestamps en snake_case
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'venta_id',
        'tipo_entrega',
        'tipo_despacho',
        'estado_entrega',
        'fecha_entrega',
        'fecha_programada',
        'hora_inicio',
        'hora_fin',
        'direccion_entrega',
        'observaciones',
        'almacen_salida_id',
        'chofer_id',
        'quien_entrega', // Nuevo: quién realiza la entrega física
        'user_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'fecha_programada' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Pertenece a una venta
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Relación: Pertenece a un almacén de salida
     */
    public function almacenSalida(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_salida_id');
    }

    /**
     * Relación: Pertenece a un chofer (usuario nullable)
     */
    public function chofer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_id');
    }

    /**
     * Relación: Pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Tiene muchos productos entregados
     */
    public function productosEntregados(): HasMany
    {
        return $this->hasMany(DetalleEntregaProducto::class, 'entrega_producto_id');
    }
}
