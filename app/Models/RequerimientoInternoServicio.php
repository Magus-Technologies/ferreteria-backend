<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoInternoServicio extends Model
{
    protected $table = 'requerimiento_interno_servicios';
    public $timestamps = false;

    protected $fillable = [
        'requerimiento_id',
        'tipo_servicio',
        'descripcion_servicio',
        'lugar_ejecucion',
        'fecha_inicio_estimada',
        'hora_inicio',
        'hora_fin',
        'presupuesto_referencial',
        'detalles',
        'duracion_cantidad',
        'duracion_unidad',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_estimada' => 'datetime:Y-m-d H:i',
            'presupuesto_referencial' => 'decimal:2',
            'duracion_cantidad' => 'integer',
        ];
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
    }
}
