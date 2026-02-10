<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComprobanteElectronico extends Model
{
    use SoftDeletes;

    protected $table = 'comprobantes_electronicos';
    
    // ✅ Usar autoincrement bigint según estructura real de producción
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'venta_id',
        'tipo_comprobante',
        'serie',
        'correlativo', // ⚠️ En la tabla se llama 'correlativo', no 'numero'
        'fecha_emision',
        'fecha_vencimiento',
        'hora_emision',
        'cliente_id',
        'cliente_tipo_documento',
        'cliente_numero_documento',
        'cliente_razon_social',
        'cliente_direccion',
        'cliente_email',
        'cliente_telefono',
        'moneda',
        'tipo_cambio',
        'operacion_gravada',
        'operacion_exonerada',
        'operacion_inafecta',
        'operacion_gratuita',
        'total_igv',
        'total_isc',
        'total_otros_tributos',
        'total_descuentos',
        'total_cargos',
        'total_anticipos',
        'importe_total',
        'monto_en_letras',
        'comprobante_referencia_id',
        'tipo_doc_referencia',
        'numero_doc_referencia',
        'codigo_motivo',
        'descripcion_motivo',
        'forma_pago',
        'observaciones',
        'terminos_condiciones',
        'estado_sunat',
        'fecha_envio_sunat',
        'fecha_respuesta_sunat',
        'codigo_respuesta_sunat',
        'mensaje_respuesta_sunat',
        'observaciones_sunat',
        'errores_sunat',
        'hash_cpe',
        'codigo_qr',
        'xml_sin_firmar',
        'xml_firmado',
        'xml_path',
        'cdr_xml',
        'cdr_path',
        'pdf_base64',
        'pdf_path',
        'user_id',
        'user_envio_id',
        'user_anulacion_id',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'hora_emision' => 'datetime',
        'fecha_envio_sunat' => 'datetime',
        'fecha_respuesta_sunat' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'tipo_cambio' => 'decimal:4',
        'operacion_gravada' => 'decimal:2',
        'operacion_exonerada' => 'decimal:2',
        'operacion_inafecta' => 'decimal:2',
        'operacion_gratuita' => 'decimal:2',
        'total_igv' => 'decimal:2',
        'total_isc' => 'decimal:2',
        'total_otros_tributos' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_cargos' => 'decimal:2',
        'total_anticipos' => 'decimal:2',
        'importe_total' => 'decimal:2',
        'observaciones_sunat' => 'array',
        'errores_sunat' => 'array',
    ];

    // Relationships
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userEnvio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_envio_id');
    }

    public function userAnulacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_anulacion_id');
    }

    public function comprobanteReferencia(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_referencia_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleComprobanteElectronico::class, 'comprobante_electronico_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function intentosEnvio(): HasMany
    {
        return $this->hasMany(IntentoEnvioSunat::class, 'comprobante_id')
            ->orderBy('fecha_intento', 'desc'); // ✅ Fixed: was created_at
    }

    // Scopes
    public function scopePorTipoDocumento($query, string $tipo)
    {
        return $query->where('tipo_comprobante', $tipo);
    }

    public function scopePorEstadoSunat($query, string $estado)
    {
        return $query->where('estado_sunat', $estado);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_emision', [$fechaInicio, $fechaFin]);
    }

    // Helper methods
    public function getNumeroCompletoAttribute(): string
    {
        return "{$this->serie}-" . str_pad($this->correlativo, 8, '0', STR_PAD_LEFT);
    }

    public function tieneXml(): bool
    {
        return !empty($this->xml_path) && file_exists(storage_path('app/' . $this->xml_path));
    }

    public function tieneCdr(): bool
    {
        return !empty($this->cdr_path) && file_exists(storage_path('app/' . $this->cdr_path));
    }

    public function estaAceptado(): bool
    {
        return in_array($this->estado_sunat, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES']);
    }

    public function estaRechazado(): bool
    {
        return $this->estado_sunat === 'RECHAZADO';
    }

    public function estaPendiente(): bool
    {
        return $this->estado_sunat === 'PENDIENTE';
    }

    public function fueEnviado(): bool
    {
        return !empty($this->fecha_envio_sunat);
    }

    public function estaAnulado(): bool
    {
        return $this->estado_sunat === 'ANULADO';
    }
}
