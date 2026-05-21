<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalHistory extends Model
{
    protected $table = 'approval_history';

    protected $fillable = [
        'requerimiento_id',
        'from_cargo_id',
        'to_cargo_id',
        'user_id',
        'action',
        'reason',
    ];

    public function requerimiento()
    {
        return $this->belongsTo(RequerimientoInterno::class, 'requerimiento_id');
    }
}
