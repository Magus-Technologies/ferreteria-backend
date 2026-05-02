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

    protected $appends = [
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'ubigeo_id',
        'ubigeo',
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

    public function getPrincipalAttribute(): ?DireccionEmpresa
    {
        return $this->direcciones->firstWhere('es_principal', true);
    }

    // Accessors para compatibilidad con código existente
    public function getDireccionAttribute(): ?string
    {
        return $this->principal?->direccion;
    }

    public function getDepartamentoAttribute(): ?string
    {
        return $this->principal?->departamento;
    }

    public function getProvinciaAttribute(): ?string
    {
        return $this->principal?->provincia;
    }

    public function getDistritoAttribute(): ?string
    {
        return $this->principal?->distrito;
    }

    public function getUbigeoIdAttribute(): ?int
    {
        return $this->principal?->ubigeo_id;
    }

    public function getUbigeoAttribute(): ?string
    {
        $dir = $this->principal;
        if (!$dir?->ubigeo_id) return null;
        $ubigeo = \App\Models\Distrito::find($dir->ubigeo_id);
        return $ubigeo
            ? $ubigeo->departamento . $ubigeo->provincia . $ubigeo->distrito
            : null;
    }

    /**
     * RUC del emisor para SUNAT. En modo `beta` SUNAT exige el RUC de pruebas
     * `20000000001` aunque la empresa tenga otro RUC; en `produccion` se usa
     * el RUC real de la tabla `empresa`. Si no hay empresa registrada, cae a
     * beta.
     *
     * Replica la lógica de `SunatApiService::getEmpresa()` para que cualquier
     * lugar que arme nombres de archivo (XML/CDR) o payloads SUNAT use el
     * mismo RUC sin tener que duplicarlo en `.env`.
     */
    public static function getRucEmisor(): string
    {
        $empresa = static::first();
        if (!$empresa) {
            return '20000000001';
        }
        $modo = $empresa->sunat_modo ?? 'beta';
        return $modo === 'beta' ? '20000000001' : (string) $empresa->ruc;
    }
}
