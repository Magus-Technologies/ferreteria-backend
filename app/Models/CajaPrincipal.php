<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaPrincipal extends Model
{
    protected $table = 'cajas_principales';

    protected $fillable = [
        'codigo',
        'nombre',
        'user_id',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subCajas()
    {
        return $this->hasMany(SubCaja::class, 'caja_principal_id');
    }

    public function cajaChica()
    {
        return $this->hasOne(SubCaja::class, 'caja_principal_id')
            ->where('tipo_caja', 'CC');
    }

    /**
     * Obtener saldo total de todas las sub-cajas
     */
    public function getSaldoTotalAttribute()
    {
        return $this->subCajas->sum('saldo_actual');
    }

    /**
     * Obtener total de sub-cajas
     */
    public function getTotalSubCajasAttribute()
    {
        return $this->subCajas->count();
    }
}
