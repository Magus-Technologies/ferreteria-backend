<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * DetalleEntregaProducto — línea SOLICITADA dentro de una orden lógica
 * de entrega (`EntregaProducto`).
 *
 * `cantidad_solicitada`: total que debe cubrirse para esta línea.
 * `cantidad_entregada` (accessor): SUM de eventos en estado 'en' asociados
 *   — calculado en tiempo real desde `detalleentregaevento`.
 */
class DetalleEntregaProducto extends Model
{
    protected $table = 'detalleentregaproducto';

    public $timestamps = false;

    protected $fillable = [
        'entrega_producto_id',
        'unidad_derivada_venta_id',
        // Compatibilidad: aún llegan payloads legacy con `cantidad_entregada`.
        // Ambos setters terminan persistiendo en la columna disponible.
        'cantidad_solicitada',
        'cantidad_entregada',
        'ubicacion',
    ];

    private static ?bool $hasCantidadSolicitadaColumn = null;
    private static ?bool $hasCantidadEntregadaColumn = null;

    /**
     * Atributos calculados que se incluyen en toArray/toJson — para que el
     * frontend reciba `cantidad_entregada` y `cantidad_pendiente_detalle`
     * sin tener que cambiar todas las vistas a la vez.
     */
    protected $appends = [
        'cantidad_entregada',
        'cantidad_en_camino',
        'cantidad_programada',
        'cantidad_pendiente_detalle',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:3',
        ];
    }

    private static function hasCantidadSolicitadaColumn(): bool
    {
        if (self::$hasCantidadSolicitadaColumn === null) {
            self::$hasCantidadSolicitadaColumn = Schema::hasColumn('detalleentregaproducto', 'cantidad_solicitada');
        }

        return self::$hasCantidadSolicitadaColumn;
    }

    private static function hasCantidadEntregadaColumn(): bool
    {
        if (self::$hasCantidadEntregadaColumn === null) {
            self::$hasCantidadEntregadaColumn = Schema::hasColumn('detalleentregaproducto', 'cantidad_entregada');
        }

        return self::$hasCantidadEntregadaColumn;
    }

    public function setCantidadSolicitadaAttribute($value): void
    {
        $cantidad = (float) $value;

        if (self::hasCantidadSolicitadaColumn()) {
            $this->attributes['cantidad_solicitada'] = $cantidad;
            return;
        }

        if (self::hasCantidadEntregadaColumn()) {
            $this->attributes['cantidad_entregada'] = $cantidad;
        }
    }

    /**
     * NO-OP defensivo.
     *
     * Antes este setter sobreescribía `cantidad_solicitada` con la cantidad
     * "entregada" que viene en el payload legacy — bug destructivo: si el
     * frontend mandaba cantidad_entregada=5 sobre una entrega solicitada de
     * 10, el total se reducía a 5 perdiendo la referencia del pedido original.
     *
     * En el modelo "1 orden + N eventos" la cantidad entregada se calcula
     * sumando los `DetalleEntregaEvento` con `entregaevento.estado='en'`. Por
     * eso este setter ya NO escribe en BD. Si llega cantidad_entregada en el
     * payload se ignora silenciosamente — el camino correcto es crear un
     * `EntregaEvento` vía `POST /entregas-productos/{id}/eventos`.
     */
    public function setCantidadEntregadaAttribute($value): void
    {
        // Intencionalmente vacío — ver docblock.
    }

    public function getCantidadSolicitadaAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        $legacy = $this->attributes['cantidad_entregada'] ?? null;

        return $legacy !== null ? (float) $legacy : 0.0;
    }

    public function entregaProducto(): BelongsTo
    {
        return $this->belongsTo(EntregaProducto::class);
    }

    public function unidadDerivadaVenta(): BelongsTo
    {
        return $this->belongsTo(UnidadDerivadaInmutableVenta::class, 'unidad_derivada_venta_id');
    }

    /** Eventos físicos asociados a esta línea solicitada */
    public function eventosDetalle(): HasMany
    {
        return $this->hasMany(DetalleEntregaEvento::class, 'detalle_entrega_producto_id');
    }

    /**
     * Cantidad realmente entregada (suma de eventos en estado 'en').
     *
     * Usa la relación pre-cargada si está disponible — si no, hace una
     * consulta on-the-fly. Recomendado eager-load: `eventosDetalle.entregaEvento`.
     */
    public function getCantidadEntregadaAttribute(): float
    {
        if ($this->relationLoaded('eventosDetalle')) {
            $tieneEventos = $this->eventosDetalle->isNotEmpty();
            if (! $tieneEventos) {
                // Compatibilidad legacy: sin eventos, el valor persistido
                // representa la cantidad de esta entrega.
                return (float) $this->cantidad_solicitada;
            }

            return (float) $this->eventosDetalle
                ->filter(fn ($d) => optional($d->entregaEvento)->estado === 'en')
                ->sum('cantidad');
        }

        $baseQuery = DetalleEntregaEvento::query()
            ->where('detalleentregaevento.detalle_entrega_producto_id', $this->id);

        if (! (clone $baseQuery)->exists()) {
            return (float) $this->cantidad_solicitada;
        }

        return (float) $baseQuery
            ->join('entregaevento', 'entregaevento.id', '=', 'detalleentregaevento.entrega_evento_id')
            ->where('entregaevento.estado', 'en')
            ->sum('detalleentregaevento.cantidad');
    }

    /** Cantidad en eventos 'en camino' (estado 'ec') — reservada/comprometida */
    public function getCantidadEnCaminoAttribute(): float
    {
        if ($this->relationLoaded('eventosDetalle')) {
            return (float) $this->eventosDetalle
                ->filter(fn ($d) => optional($d->entregaEvento)->estado === 'ec')
                ->sum('cantidad');
        }

        return (float) DetalleEntregaEvento::query()
            ->join('entregaevento', 'entregaevento.id', '=', 'detalleentregaevento.entrega_evento_id')
            ->where('detalleentregaevento.detalle_entrega_producto_id', $this->id)
            ->where('entregaevento.estado', 'ec')
            ->sum('detalleentregaevento.cantidad');
    }

    /** Cantidad reservada como evento PROGRAMADO (estado 'pr') — aún no en camino */
    public function getCantidadProgramadaAttribute(): float
    {
        if ($this->relationLoaded('eventosDetalle')) {
            return (float) $this->eventosDetalle
                ->filter(fn ($d) => optional($d->entregaEvento)->estado === 'pr')
                ->sum('cantidad');
        }

        return (float) DetalleEntregaEvento::query()
            ->join('entregaevento', 'entregaevento.id', '=', 'detalleentregaevento.entrega_evento_id')
            ->where('detalleentregaevento.detalle_entrega_producto_id', $this->id)
            ->where('entregaevento.estado', 'pr')
            ->sum('detalleentregaevento.cantidad');
    }

    /**
     * Pendiente SIN ASIGNAR para esta línea:
     *   solicitada − entregada − en_camino − programada
     *
     * Incluye 'pr' (programado) porque ya está reservado para un evento
     * futuro y NO debe contarse como "libre para configurar".
     */
    public function getCantidadPendienteDetalleAttribute(): float
    {
        return max(
            0.0,
            (float) $this->cantidad_solicitada
                - $this->cantidad_entregada
                - $this->cantidad_en_camino
                - $this->cantidad_programada,
        );
    }
}
