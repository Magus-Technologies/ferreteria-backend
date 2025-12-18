<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'role';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'descripcion',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, '_roletouser', 'a', 'b');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, '_permissiontorole', 'b', 'a');
    }
}
