<?php

namespace App\Models;

use App\Enums\CodigoEstadoEntrega;
use App\Enums\CodigoTipoEntrega;
use App\Enums\CodigoTipoDespacho;
use App\Enums\CodigoQuienEntrega;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrega extends Model
{
    protected $table = 'entrega';

    protected $fillable = [
        'venta_id',
        'venta_entrega_secuencia',
        'tipo_entrega_id',
        'tipo_despacho_id',
        'estado_entrega_id',
        'quien_entrega_id',
        'almacen_salida_id',
        'chofer_id',
        'vehiculo_id',
        'tipo_pedido',
        'cargo_destino',
        'user_creador_id',
        'user_entregado_id',
        'user_anulacion_id',
        'fecha_creacion',
        'fecha_programada',
        'fecha_ejecutada',
        'fecha_anulacion',
        'hora_inicio',
        'hora_fin',
        'direccion_entrega',
        'referencia_entrega',
        'latitud',
        'longitud',
        'observaciones',
        'motivo_anulacion',
        'stock_aplicado',
        'entrega_legacy_id',
    ];

    protected $casts = [
        'stock_aplicado'  => 'boolean',
        'fecha_creacion'  => 'date',
        'fecha_programada' => 'date',
        'fecha_ejecutada' => 'datetime',
        'fecha_anulacion' => 'datetime',
    ];

    // ─── Relaciones con catálogos ───────────────────────────────

    public function tipoEntrega(): BelongsTo
    {
        return $this->belongsTo(TipoEntrega::class, 'tipo_entrega_id');
    }

    public function tipoDespacho(): BelongsTo
    {
        return $this->belongsTo(TipoDespacho::class, 'tipo_despacho_id');
    }

    public function estadoEntrega(): BelongsTo
    {
        return $this->belongsTo(EstadoEntrega::class, 'estado_entrega_id');
    }

    public function quienEntrega(): BelongsTo
    {
        return $this->belongsTo(QuienEntrega::class, 'quien_entrega_id');
    }

    // ─── Relaciones con entidades ───────────────────────────────

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function almacenSalida(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_salida_id');
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function entregaLegacy(): BelongsTo
    {
        return $this->belongsTo(EntregaProducto::class, 'entrega_legacy_id');
    }

    public function userCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_creador_id');
    }

    public function userEntregado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_entregado_id');
    }

    public function userAnulacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_anulacion_id');
    }

    // ─── Detalles ───────────────────────────────────────────────

    public function detalles(): HasMany
    {
        return $this->hasMany(EntregaDetalle::class, 'entrega_id');
    }

    // ─── Helpers de estado ──────────────────────────────────────

    public function esPendiente(): bool
    {
        return $this->estadoEntrega?->codigo === CodigoEstadoEntrega::Pendiente;
    }

    public function estaEnCamino(): bool
    {
        return $this->estadoEntrega?->codigo === CodigoEstadoEntrega::EnCamino;
    }

    public function estaEntregado(): bool
    {
        return $this->estadoEntrega?->codigo === CodigoEstadoEntrega::Entregado;
    }

    public function estaCancelado(): bool
    {
        return $this->estadoEntrega?->codigo === CodigoEstadoEntrega::Cancelado;
    }

    public function esFinal(): bool
    {
        return (bool) $this->estadoEntrega?->es_final;
    }

    // ─── Helpers de tipo ────────────────────────────────────────

    public function esDomicilio(): bool
    {
        return $this->tipoEntrega?->codigo === CodigoTipoEntrega::DespachoADomicilio;
    }

    public function esProgramado(): bool
    {
        return $this->tipoDespacho?->codigo === CodigoTipoDespacho::Programado;
    }
}
