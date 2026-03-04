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
        'area',
        'fecha_requerida',
        'prioridad',
        'tipo_solicitud',
        'observaciones',
        'estado',
        'proveedor_sugerido_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_requerida' => 'date',
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

    public function servicio(): HasOne
    {
        return $this->hasOne(RequerimientoInternoServicio::class, 'requerimiento_id');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'requerimiento_id');
    }

    // ============= HELPERS =============

    /**
     * Generar código auto-incremental: REQ-YYYY-NNN
     */
    public static function generarCodigo(): string
    {
        $year = date('Y');
        $prefix = "REQ-{$year}-";

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
