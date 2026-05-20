<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoMantenimiento extends Model
{
    protected $table = 'vehiculo_mantenimientos';

    protected $fillable = [
        'vehiculo_id',
        'requerimiento_id',
        'tipo',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function requerimiento()
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
    }
}
