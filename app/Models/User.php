<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $table = 'user'; // Tabla en singular
    protected $keyType = 'string'; // ID es string (CUID de Prisma)
    public $incrementing = false; // ID no es autoincremental

    // Prisma usa camelCase para timestamps
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified',
        'image',
        'empresa_id',
        'efectivo',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'datetime',
            'password' => 'hashed',
            'efectivo' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
        return $this->hasMany(EntregaProducto::class, 'user_id');
    }

    public function entregasChofer(): HasMany
    {
        return $this->hasMany(EntregaProducto::class, 'chofer_id');
    }

    // Permisos directos (tabla intermedia de Prisma)
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, '_permissiontouser', 'B', 'A');
    }

    // Roles (tabla intermedia de Prisma)
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, '_roletouser', 'B', 'A');
    }

    // Método helper para obtener todos los permisos (directos + de roles)
    public function getAllPermissionsAttribute(): array
    {
        $directPermissions = $this->permissions->pluck('name')->toArray();
        $rolePermissions = $this->roles->flatMap->permissions->pluck('name')->toArray();

        return array_unique(array_merge($directPermissions, $rolePermissions));
    }
}
