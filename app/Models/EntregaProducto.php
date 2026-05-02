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
        'referencia_entrega',
        'latitud',
        'longitud',
        'observaciones',
        'almacen_salida_id',
        'chofer_id',
        'vehiculo_id',
        'quien_entrega',
        'user_id',
        'user_entregado_id',
        'tipo_pedido',
        'cargo_destino',
        'aceptado_at',
        // Auditoría de anulación: cuando un usuario marca como entregada
        // por error y luego anula, se guarda el quién/cuándo/por qué.
        'fecha_anulacion',
        'motivo_anulacion',
        'user_anulacion_id',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'fecha_programada' => 'datetime',
            'aceptado_at' => 'datetime',
            'fecha_anulacion' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope: Pedidos externos pendientes por cargo (sin aceptar)
     */
    public function scopeExternosPendientesPorCargo($query, string $cargo)
    {
        return $query->where('tipo_pedido', 'externo')
                     ->where('cargo_destino', $cargo)
                     ->where('estado_entrega', 'pe')
                     ->whereNull('chofer_id');
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
     * Relación: Pertenece a un chofer (nullable) - LEGACY: Tabla choferes (proveedores)
     */
    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Choferes::class, 'chofer_id');
    }

    /**
     * Relación: Pertenece a un despachador (usuario con rol DESPACHADOR)
     * Usa la tabla 'user' en lugar de 'choferes'
     */
    public function despachador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_id');
    }

    /**
     * Relación: Pertenece a un vehículo (asignación rotativa)
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Relación: Pertenece a un usuario (quien CREÓ la entrega)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Usuario que marcó la entrega como ENTREGADO.
     * Distinto de `user` (creador). Se setea cuando estado pasa a 'en'.
     */
    public function userEntregado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_entregado_id');
    }

    /**
     * Relación: Tiene muchos productos entregados
     */
    public function productosEntregados(): HasMany
    {
        return $this->hasMany(DetalleEntregaProducto::class, 'entrega_producto_id');
    }

    /**
     * Relación: Usuario que anuló la entrega (cuando se deshizo un
     * "marcar entregada" hecho por error). Solo presente mientras la
     * entrega esté en `'pe'` con `fecha_anulacion` cargada — al volver
     * a marcarse como `'en'`, los 3 campos de anulación se limpian.
     */
    public function userAnulacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_anulacion_id');
    }
}
