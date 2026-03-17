<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuiaRemision extends Model
{
    protected $table = 'guia_remision';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id',
        'venta_id',
        'tipo_guia',
        'serie',
        'numero',
        'fecha_emision',
        'fecha_traslado',
        'afecta_stock',
        'cliente_id',
        'comprador_id',
        'motivo_traslado_id',
        'modalidad_transporte',
        'vehiculo_placa',
        'chofer_id',
        'punto_partida',
        'punto_llegada',
        'almacen_origen_id',
        'almacen_destino_id',
        'referencia',
        'observaciones',
        'estado',
        'fecha_anulacion',
        'motivo_anulacion',
        'sunat_codigo_hash',
        'sunat_xml_path',
        'sunat_cdr_xml',
        'sunat_cdr_path',
        'sunat_codigo_qr',
        'sunat_fecha_envio',
        'sunat_estado',
        'sunat_mensaje',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_traslado' => 'date',
            'afecta_stock' => 'boolean',
            'fecha_anulacion' => 'datetime',
            'sunat_fecha_envio' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ============================================
    // RELACIONES
    // ============================================

    /**
     * Venta asociada (opcional)
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Cliente destinatario
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Cliente comprador (para motivos 03 y 14 - venta con entrega a terceros)
     */
    public function comprador(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'comprador_id');
    }

    /**
     * Motivo de traslado (Catálogo N° 20 SUNAT)
     */
    public function motivoTraslado(): BelongsTo
    {
        return $this->belongsTo(MotivoTraslado::class);
    }

    /**
     * Chofer (proveedor de transporte)
     */
    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    /**
     * Almacén de origen
     */
    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    /**
     * Almacén de destino (opcional)
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * Usuario que creó la guía
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Detalles de productos en la guía
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleGuiaRemision::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope para filtrar por estado
     */
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para filtrar por rango de fechas de emisión
     */
    public function scopeFechaEmisionEntre($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_emision', [$desde, $hasta]);
    }

    /**
     * Scope para filtrar por rango de fechas de traslado
     */
    public function scopeFechaTrasladoEntre($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_traslado', [$desde, $hasta]);
    }

    /**
     * Scope para filtrar por cliente
     */
    public function scopeCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    /**
     * Scope para filtrar por almacén de origen
     */
    public function scopeAlmacenOrigen($query, $almacenId)
    {
        return $query->where('almacen_origen_id', $almacenId);
    }

    /**
     * Scope para filtrar por tipo de guía
     */
    public function scopeTipoGuia($query, $tipo)
    {
        return $query->where('tipo_guia', $tipo);
    }

    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================

    /**
     * Verificar si la guía puede ser editada
     */
    public function puedeEditarse(): bool
    {
        return $this->estado === 'BORRADOR';
    }

    /**
     * Verificar si la guía puede ser anulada
     */
    public function puedeAnularse(): bool
    {
        return $this->estado === 'EMITIDA';
    }

    /**
     * Verificar si la guía está vinculada a una venta
     */
    public function tieneVenta(): bool
    {
        return !is_null($this->venta_id);
    }

    /**
     * Obtener el número completo de la guía (serie-número)
     */
    public function getNumeroCompletoAttribute(): string
    {
        if ($this->serie && $this->numero) {
            return "{$this->serie}-{$this->numero}";
        }
        return 'S/N';
    }
}
