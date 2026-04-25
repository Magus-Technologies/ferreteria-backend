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
        'telefono',
        'celular',
        'horario_atencion',
        'fecha_nacimiento',
        'puntos',
        'centimos',
        'contacto_referencia',
        'email',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo_cliente' => TipoCliente::class,
            'estado' => 'boolean',
            'fecha_nacimiento' => 'date:Y-m-d',
            'puntos' => 'integer',
            'centimos' => 'integer',
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

    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionCliente::class);
    }

    public function direccionPrincipal(): HasMany
    {
        return $this->hasMany(DireccionCliente::class)->where('es_principal', true);
    }
}
