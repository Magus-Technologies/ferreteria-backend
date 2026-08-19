<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoDePago extends Model
{
    /**
     * Tabla asociada al modelo (singular sin guiones bajos)
     */
    protected $table = 'metododepago';

    /**
     * Clave primaria es string (CUID)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Sin timestamps
     */
    public $timestamps = false;

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'id',
        'name',
        'cuenta_bancaria',
        'nombre_titular',
        'monto',
        'monto_inicial',
        'subcaja_id',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_inicial' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * ¿Este método mueve EFECTIVO físico (billetes) y no dinero bancarizado?
     *
     * Es efectivo cuando no tiene cuenta bancaria real y su nombre lo dice
     * ("efectivo", "efectivo black", …). Los métodos sin cuenta se guardan de
     * varias formas según cómo se hayan creado: NULL, cadena vacía, el literal
     * 'SIN-CUENTA' que pone MetodoDePagoController, o un guion '-' escrito a mano
     * desde el formulario. Los cuatro significan lo mismo.
     *
     * Existe acá porque la regla estaba copiada en varios servicios con criterios
     * distintos: el cierre aceptaba el guion y el traslado a bóveda no. Con
     * `cuenta_bancaria = '-'` (así está "efectivo" en producción) el traslado no
     * reconocía la caja como efectivo, se saltaba el monto de apertura y ofrecía
     * un disponible menor al real — el vendedor no podía trasladar su propio
     * dinero de apertura.
     */
    public function esEfectivo(): bool
    {
        $cuenta = trim((string) $this->cuenta_bancaria);
        $sinCuenta = $cuenta === '' || $cuenta === '-' || strtoupper($cuenta) === 'SIN-CUENTA';

        return $sinCuenta && stripos((string) $this->name, 'efectivo') !== false;
    }

    /**
     * Relación: Pertenece a una subcaja
     */
    public function subcaja(): BelongsTo
    {
        return $this->belongsTo(SubCaja::class);
    }

    /**
     * Relación: Tiene muchos despliegues de pago
     */
    public function desplieguesDePagos(): HasMany
    {
        return $this->hasMany(DespliegueDePago::class, 'metodo_de_pago_id');
    }
}
