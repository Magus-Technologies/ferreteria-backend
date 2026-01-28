<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo Restriction
 *
 * Representa una RESTRICCIÓN de acceso (lista negra).
 * Por defecto, todos tienen acceso a todo.
 * Solo se guardan las restricciones específicas.
 */
class Restriction extends Model
{
    protected $table = 'restriction';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'descripcion',
    ];

    /**
     * Usuarios que tienen esta restricción
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, '_restrictiontouser', 'a', 'b');
    }

    /**
     * Roles que tienen esta restricción
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, '_restrictiontorole', 'a', 'b');
    }
}
