<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'permission';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'descripcion',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, '_permissiontouser', 'a', 'b');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, '_permissiontorole', 'a', 'b');
    }
}
