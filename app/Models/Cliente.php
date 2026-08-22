<?php

namespace App\Models;

use App\Enums\TipoCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'cliente';
    public $timestamps = false;

    /**
     * ¿El documento del cliente es un RUC?
     *
     * Se deduce de la LONGITUD del número, porque la tabla `cliente` no tiene
     * columna `tipo_documento`. Varios lugares leían `$cliente->tipo_documento`
     * y comparaban contra 'ruc': esa propiedad no existe, siempre vale null, así
     * que todos los clientes quedaban clasificados como DNI. Eso hacía que el
     * envío a SUNAT rechazara TODA factura ("solo pueden emitirse a clientes con
     * RUC") aunque el cliente tuviera RUC, y que los XML salieran con
     * schemeID="1" (DNI) para números de 11 dígitos.
     */
    public function esRuc(): bool
    {
        return strlen((string) $this->numero_documento) === 11;
    }

    /**
     * Código de tipo de documento del cliente según el catálogo 06 de SUNAT:
     * '6' RUC, '1' DNI, '0' sin documento (venta a "cliente varios").
     *
     * Pasaporte ('7') y carnet de extranjería ('4') no se pueden distinguir sin
     * una columna que los identifique, así que caen en DNI como hasta ahora.
     */
    public function tipoDocumentoSunat(): string
    {
        $numero = (string) ($this->numero_documento ?? '');

        if ($numero === '' || str_starts_with($numero, 'SN-')) {
            return '0';
        }

        return $this->esRuc() ? '6' : '1';
    }

    protected $fillable = [
        'tipo_cliente',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'telefono',
        'profesion_id',
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

    public function calificaciones(): HasMany
    {
        return $this->hasMany(\App\Models\ClienteCalificacion::class);
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(Profesion::class, 'profesion_id');
    }

    public function direccionPrincipal(): HasMany
    {
        return $this->hasMany(DireccionCliente::class)->where('es_principal', true);
    }
}
