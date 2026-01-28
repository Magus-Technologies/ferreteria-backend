<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = "user"; // Tabla en singular
    protected $keyType = "string"; // ID es string (CUID de Prisma)
    public $incrementing = false; // ID no es autoincremental

    // Prisma usa camelCase para timestamps
    const CREATED_AT = "createdAt";
    const UPDATED_AT = "updatedAt";

    protected $fillable = [
        "id", // ← IMPORTANTE: Permitir asignar ID manualmente
        "name",
        "email",
        "password",
        "email_verified",
        "image",
        "empresa_id",
        "efectivo",

        // nuevos campos , info personal
        "tipo_documento",
        "numero_documento",
        "telefono",
        "celular",
        "genero",
        "estado_civil",
        "email_corporativo",

        // direcciones
        "direccion_linea1",
        "direccion_linea2",
        "ciudad",
        "nacionalidad",
        "fecha_nacimiento",

        // Información de Contrato
        "cargo",
        "fecha_inicio",
        "fecha_baja",
        "vacaciones_dias",
        "sueldo_boleta",
        "rol_sistema",

        "estado",
    ];

    protected $hidden = ["password"];

    protected function casts(): array
    {
        return [
            "email_verified" => "datetime",
            "password" => "hashed",
            "efectivo" => "decimal:2",
            "fecha_nacimiento" => "date",
            "fecha_inicio" => "date",
            "fecha_baja" => "date",
            "vacaciones_dias" => "integer",
            "sueldo_boleta" => "decimal:2",
            "estado" => "boolean",
            "created_at" => "datetime",
            "updated_at" => "datetime",
        ];
    }

    // Relaciones
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function ingresosSalidas(): HasMany
    {
        return $this->hasMany(IngresoSalida::class);
    }

    public function recepcionesAlmacen(): HasMany
    {
        return $this->hasMany(RecepcionAlmacen::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(AperturaYCierreCaja::class);
    }

    public function egresosDinero(): HasMany
    {
        return $this->hasMany(EgresoDinero::class);
    }

    public function ingresosDinero(): HasMany
    {
        return $this->hasMany(IngresoDinero::class);
    }

    public function entregasProducto(): HasMany
    {
        return $this->hasMany(EntregaProducto::class, "user_id");
    }

    public function entregasChofer(): HasMany
    {
        return $this->hasMany(EntregaProducto::class, "chofer_id");
    }

    public function cajaPrincipal()
    {
        return $this->hasOne(CajaPrincipal::class, "user_id");
    }

    // ==================== SISTEMA DE RESTRICCIONES (lista negra) ====================

    /**
     * Roles del usuario
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, "_roletouser", "B", "A");
    }

    /**
     * Restricciones directas del usuario
     */
    public function restrictions(): BelongsToMany
    {
        return $this->belongsToMany(
            Restriction::class,
            "_restrictiontouser",
            "b",
            "a",
        );
    }

    /**
     * Obtener todas las restricciones (directas + de roles)
     */
    public function getAllRestrictionsAttribute(): array
    {
        $directRestrictions = $this->restrictions->pluck("name")->toArray();
        $roleRestrictions = $this->roles->flatMap->restrictions
            ->pluck("name")
            ->toArray();

        return array_unique(
            array_merge($directRestrictions, $roleRestrictions),
        );
    }

    /**
     * Verificar si el usuario tiene una restricción específica
     *
     * @param string $restriction Nombre de la restricción (ej: "venta.create")
     * @return bool true si está BLOQUEADO, false si tiene ACCESO
     */
    public function isRestricted(string $restriction): bool
    {
        return in_array($restriction, $this->all_restrictions);
    }

    /**
     * Verificar si el usuario tiene acceso (inverso de isRestricted)
     *
     * @param string $feature Nombre de la funcionalidad
     * @return bool true si tiene ACCESO, false si está BLOQUEADO
     */
    public function hasAccess(string $feature): bool
    {
        return !$this->isRestricted($feature);
    }

    public function getTipoDocumentoNombreAttribute(): string
    {
        $tipos = [
            "DNI" => "Documento Nacional de Identidad",
            "RUC" => "Registro Único de Contribuyentes",
            "CE" => "Carnet de Extranjería",
            "PASAPORTE" => "Pasaporte",
        ];

        return $tipos[$this->tipo_documento] ?? $this->tipo_documento;
    }

    /**
     * Obtener nombre completo del género
     */
    public function getGeneroNombreAttribute(): ?string
    {
        $generos = [
            "M" => "Masculino",
            "F" => "Femenino",
            "O" => "Otro",
        ];

        return $generos[$this->genero] ?? null;
    }

    /**
     * Obtener edad del usuario
     */
    public function getEdadAttribute(): ?int
    {
        if (!$this->fecha_nacimiento) {
            return null;
        }

        return \Carbon\Carbon::parse($this->fecha_nacimiento)->age;
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActivos($query)
    {
        return $query->where("estado", true);
    }

    /**
     * Scope para usuarios inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where("estado", false);
    }
}
