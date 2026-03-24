<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;


class GastoExtra extends Model
{
    protected $table = 'gastos_extras';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'monto',
        'concepto',
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

    public function desplieguePago(): BelongsTo
    {
        return $this->belongsTo(DespliegueDePago::class, 'despliegue_pago_id');
    }

    public function compra(): HasOne
    {
        return $this->hasOne(Compra::class, 'gasto_extra_id');
    }
}
