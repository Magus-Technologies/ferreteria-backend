<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AperturaCierreCaja extends Model
{
    protected $table = 'apertura_cierre_caja';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_principal_id',
        'sub_caja_id',
        'user_id',
        'monto_apertura',
        'conteo_apertura_billetes_monedas',
        'fecha_apertura',
        'monto_cierre',
        'fecha_cierre',
        'estado',
        'monto_cierre_efectivo',
        'monto_cierre_cuentas',
        'conteo_billetes_monedas',
        'conceptos_adicionales',
        'comentarios',
        'supervisor_id',
        'diferencia_efectivo',
        'diferencia_total',
        'forzar_cierre',
    ];

    protected $casts = [
        'monto_apertura' => 'decimal:2',
        'monto_cierre' => 'decimal:2',
        'monto_cierre_efectivo' => 'decimal:2',
        'monto_cierre_cuentas' => 'decimal:2',
        'diferencia_efectivo' => 'decimal:2',
        'diferencia_total' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'conteo_apertura_billetes_monedas' => 'array',
        'conteo_billetes_monedas' => 'array',
        'conceptos_adicionales' => 'array',
        'forzar_cierre' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    // Relaciones
    public function cajaPrincipal()
    {
        return $this->belongsTo(CajaPrincipal::class, 'caja_principal_id');
    }

    public function subCaja()
    {
        return $this->belongsTo(SubCaja::class, 'sub_caja_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function distribucionesVendedores()
    {
        return $this->hasMany(DistribucionEfectivoVendedor::class, 'apertura_cierre_caja_id');
    }

    // Métodos helper
    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    /**
     * Calcular el total del conteo de billetes/monedas
     */
    public static function calcularTotalConteo(?array $conteo): float
    {
        if (!$conteo) {
            return 0.00;
        }

        $total = 0;
        $total += ($conteo['billete_200'] ?? 0) * 200;
        $total += ($conteo['billete_100'] ?? 0) * 100;
        $total += ($conteo['billete_50'] ?? 0) * 50;
        $total += ($conteo['billete_20'] ?? 0) * 20;
        $total += ($conteo['billete_10'] ?? 0) * 10;
        $total += ($conteo['moneda_5'] ?? 0) * 5;
        $total += ($conteo['moneda_2'] ?? 0) * 2;
        $total += ($conteo['moneda_1'] ?? 0) * 1;
        $total += ($conteo['moneda_050'] ?? 0) * 0.5;
        $total += ($conteo['moneda_020'] ?? 0) * 0.2;
        $total += ($conteo['moneda_010'] ?? 0) * 0.1;

        return round($total, 2);
    }

    /**
     * Comparar conteo de apertura vs cierre
     */
    public function compararConteos(): array
    {
        $conteoApertura = $this->conteo_apertura_billetes_monedas ?? [];
        $conteoCierre = $this->conteo_billetes_monedas ?? [];

        $totalApertura = self::calcularTotalConteo($conteoApertura);
        $totalCierre = self::calcularTotalConteo($conteoCierre);

        return [
            'total_apertura' => $totalApertura,
            'total_cierre' => $totalCierre,
            'diferencia' => $totalCierre - $totalApertura,
            'conteo_apertura' => $conteoApertura,
            'conteo_cierre' => $conteoCierre,
        ];
    }
}
