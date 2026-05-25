<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EntregaEvento — cada despacho físico parcial dentro de una orden lógica.
 *
 * Una orden lógica (`EntregaProducto`) puede tener N eventos: cada uno con
 * su propio chofer, vehículo, fecha programada y estado. Permite modelar
 * "vendí 10, el lunes despaché 5 con Juan, el miércoles 3 con Pedro,
 * el viernes 2 con María" sin duplicar la orden.
 */
class EntregaEvento extends Model
{
    protected $table = 'entregaevento';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'entrega_producto_id',
        'estado',                // pr | ec | en | an
        'fecha_programada',
        'fecha_ejecutada',
        'hora_inicio',
        'hora_fin',
        'chofer_id',
        'vehiculo_id',
        'quien_entrega',
        'tipo_pedido',           // interno | externo
        'cargo_destino',
        'direccion_entrega',
        'referencia_entrega',
        'latitud',
        'longitud',
        'observaciones',
        'user_id',
        'user_entregado_id',
        'aceptado_at',
        'fecha_anulacion',
        'motivo_anulacion',
        'user_anulacion_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'datetime',
            'fecha_ejecutada' => 'datetime',
            'aceptado_at' => 'datetime',
            'fecha_anulacion' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
        ];
    }

    public function entregaProducto(): BelongsTo
    {
        return $this->belongsTo(EntregaProducto::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleEntregaEvento::class, 'entrega_evento_id');
    }

    /** Despachador (User con rol DESPACHADOR) — FK a tabla `user` */
    public function despachador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userEntregado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_entregado_id');
    }

    public function userAnulacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_anulacion_id');
    }
}
