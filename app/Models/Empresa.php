<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresa'; // Tabla en singular
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
        // Logo
        'logo',
        // Gerente o Administrador
        'gerente_nombre',
        'gerente_email',
        'gerente_celular',
        // Facturación
        'facturacion_nombre',
        'facturacion_email',
        'facturacion_celular',
        // Contabilidad
        'contabilidad_nombre',
        'contabilidad_email',
        'contabilidad_celular',
        // Términos de impresión
        'terminos_comprobantes_ventas',
        'terminos_letras_cambio',
        'terminos_guias_remision',
        'terminos_cotizaciones',
        'terminos_ordenes_compras',
        'imprimir_impuestos_boleta',
    ];

    protected function casts(): array
    {
        return [
            'serie_ingreso' => 'integer',
            'serie_salida' => 'integer',
            'serie_recepcion_almacen' => 'integer',
            'imprimir_impuestos_boleta' => 'boolean',
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
}
