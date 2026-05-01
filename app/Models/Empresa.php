<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresa';
    public $timestamps = false;

    protected $fillable = [
        'almacen_id',
        'marca_id',
        'serie_ingreso',
        'serie_salida',
        'serie_recepcion_almacen',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'ubigeo_id',
        'departamento',
        'provincia',
        'distrito',
        'regimen',
        'actividad_economica',
        'telefono',
        'celular',
        'email',
        'tipo_identificacion',
        'logo',
        'imprimir_impuestos_boleta',
        'sol_user',
        'sol_pass',
        'sunat_client_id',
        'sunat_secret_client',
        'sunat_modo',
    ];

    protected function casts(): array
    {
        return [
            'serie_ingreso' => 'integer',
            'serie_salida' => 'integer',
            'serie_recepcion_almacen' => 'integer',
            'imprimir_impuestos_boleta' => 'boolean',
            'sunat_modo' => 'string',
        ];
    }

    public function almacenPredeterminado(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function marcaPredeterminada(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function ubigeo(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'ubigeo_id', 'id_ubigeo');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoEmpresa::class, 'empresa_id');
    }

    public function terminos(): HasMany
    {
        return $this->hasMany(TerminoEmpresa::class, 'empresa_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionEmpresa::class, 'empresa_id');
    }
}
