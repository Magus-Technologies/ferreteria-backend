<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IngresoExtra extends Model
{
    protected $table = 'ingresos_extras';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'monto',
        'concepto',
        'estado',
        'user_id',
        'despliegue_pago_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::ulid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_pago_id');
    }
}
