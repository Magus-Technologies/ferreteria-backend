<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RequerimientoInterno extends Model
{
    protected $table = 'requerimientos_internos';

    protected $fillable = [
        'codigo',
        'titulo',
        'cargo',
        'assigned_cargo_id',
        'fecha_requerida',
        'prioridad',
        'tipo_solicitud',
        'observaciones',
        'afecta_calendario',
        'estado',
        'duracion_cantidad',
        'duracion_unidad',
        'proveedor_sugerido_id',
        'user_id',
        'vehiculo_id',
        'approval_state',
        'approved_by',
        'approved_at',
        'approval_note',
    ];

    // Relaciones adicionales
    public function vehiculoMantenimiento(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VehiculoMantenimiento::class, 'requerimiento_id');
    }

    public function approvalHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'requerimiento_id');
    }

    public function assignedCargo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CatalogoCargo::class, 'assigned_cargo_id');
    }

    protected function casts(): array
    {
        return [
            'fecha_requerida' => 'date:Y-m-d',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ============= RELATIONSHIPS =============

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proveedorSugerido(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_sugerido_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(RequerimientoInternoProducto::class, 'requerimiento_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(RequerimientoInternoServicio::class, 'requerimiento_id');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'requerimiento_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    // ============= HELPERS =============

    /**
     * Generar código auto-incremental por tipo de solicitud: {TIPO}-YYYY-NNN
     *
     * Cada tipo de solicitud (SOC, OC, OS) mantiene su propia serie correlativa,
     * de modo que filtrar por un tipo muestra una secuencia sin huecos.
     */
    public static function generarCodigo(string $tipoSolicitud): string
    {
        $year = date('Y');
        $prefix = "{$tipoSolicitud}-{$year}-";

        $ultimo = static::where('codigo', 'like', "{$prefix}%")
            ->orderByDesc('codigo')
            ->value('codigo');

        if ($ultimo) {
            $num = (int) substr($ultimo, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}
