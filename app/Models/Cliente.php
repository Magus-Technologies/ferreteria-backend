<?php

namespace App\Models;

use App\Enums\TipoCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'cliente';
    public $timestamps = false;

    protected $fillable = [
        'tipo_cliente',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'direccion',
        'direccion_2',
        'direccion_3',
        'direccion_4',
        'telefono',
        'email',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo_cliente' => TipoCliente::class,
            'estado' => 'boolean',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function ventasRecomendadas(): HasMany
    {
        return $this->hasMany(Venta::class, 'recomendado_por_id');
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }
}
