<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeudaPersonal extends Model
{
    protected $fillable = [
        'user_id',
        'arqueo_diario_id',
        'monto',
        'estado',
        'observaciones',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function arqueoDiario()
    {
        return $this->belongsTo(ArqueoDiario::class);
    }
}
